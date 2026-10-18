<?php

namespace App\Support;

use App\Events\RealtimeUpdate;
use App\Models\CarRental;
use App\Models\MarketplaceOrder;
use App\Models\User;
use App\Services\FcmService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Push + in-app notifications for product purchases and rentals.
 *
 * Hooked at the model level (like RealtimeHooks) so orders placed or changed
 * from any path — app, seller panel, admin panel — notify both parties.
 * Set $model->actedBy before saving to skip notifying whoever made the change.
 */
class ShopNotifications
{
    /** Checkout batches whose buyer was already told — one push per cart, not per item. */
    private static array $notifiedBatches = [];

    public static function register(): void
    {
        MarketplaceOrder::created(fn (MarketplaceOrder $o) => self::later(fn () => self::orderPlaced($o)));
        MarketplaceOrder::updated(function (MarketplaceOrder $o) {
            if ($o->wasChanged('status')) self::later(fn () => self::orderStatusChanged($o));
        });

        CarRental::created(fn (CarRental $r) => self::later(fn () => self::rentalBooked($r)));
        CarRental::updated(function (CarRental $r) {
            if ($r->wasChanged('status')) self::later(fn () => self::rentalStatusChanged($r));
        });
    }

    // Sent after the transaction commits and (on web requests) after the
    // response is flushed, so FCM round-trips never slow down or roll back the
    // order itself. Console/queue workers never "terminate", so send inline there.
    private static function later(callable $send): void
    {
        DB::afterCommit(function () use ($send) {
            $safeSend = function () use ($send) {
                try {
                    $send();
                } catch (Throwable $e) {
                    Log::error('[ShopNotifications] failed', ['error' => $e->getMessage()]);
                }
            };

            app()->runningInConsole() ? $safeSend() : app()->terminating($safeSend);
        });
    }

    // ── Marketplace orders ────────────────────────────────────────────────────

    private static function orderPlaced(MarketplaceOrder $o): void
    {
        $o->loadMissing(['product', 'buyer', 'seller']);
        $title  = $o->product?->title ?? 'your item';
        $isRent = $o->order_type === 'rent';
        $data   = self::orderData($o, $isRent ? 'shop_rent_requested' : 'shop_order_placed');

        $batch = $o->checkout_batch_id;
        if (! $batch || ! isset(self::$notifiedBatches[$batch])) {
            if ($batch) self::$notifiedBatches[$batch] = true;
            self::toBuyer($o,
                $isRent ? 'Rental request sent' : 'Order placed 🛍️',
                $isRent
                    ? "Your request to rent {$title} ({$o->rent_start_date?->toDateString()} → {$o->rent_end_date?->toDateString()}) was sent. We'll let you know when it's confirmed."
                    : ($batch ? "Your order was sent to the sellers. We'll let you know when it's confirmed."
                              : "Your order for {$title} was sent to the seller. We'll let you know when it's confirmed."),
                $data);
        }

        self::toUser($o->seller,
            $isRent ? '🔑 New rental request' : '🛒 New order',
            $isRent
                ? "{$o->buyerName()} wants to rent {$title} from {$o->rent_start_date?->toDateString()} to {$o->rent_end_date?->toDateString()} — " . self::usd($o->total_price)
                : "{$o->buyerName()} ordered {$o->quantity}× {$title} — " . self::usd($o->total_price)
                    . ($o->fulfillment === 'delivery' ? " · Deliver to {$o->delivery_address}" : ' · Pick up'),
            self::orderData($o, $isRent ? 'shop_rent_new' : 'shop_order_new'));
    }

    private static function orderStatusChanged(MarketplaceOrder $o): void
    {
        $o->loadMissing(['product', 'buyer', 'seller']);
        $title = $o->product?->title ?? "order #{$o->id}";
        $noun  = $o->order_type === 'rent' ? 'rental' : 'order';
        $type  = 'shop_order_' . $o->status;

        switch ($o->status) {
            case 'confirmed':
                if ($o->actedBy !== 'buyer') {
                    self::toBuyer($o, ucfirst($noun) . ' confirmed ✅',
                        "The seller confirmed your {$noun} for {$title}.", self::orderData($o, $type));
                }
                break;

            case 'completed':
                if ($o->actedBy !== 'buyer') {
                    self::toBuyer($o, ucfirst($noun) . ' completed',
                        "Your {$noun} for {$title} is complete. Thanks for shopping with us!", self::orderData($o, $type));
                }
                if ($o->actedBy !== 'seller') {
                    self::toUser($o->seller, ucfirst($noun) . ' completed',
                        "{$o->buyerName()}'s {$noun} for {$title} is complete — " . self::usd($o->total_price), self::orderData($o, $type));
                }
                break;

            case 'cancelled':
                if ($o->actedBy !== 'buyer') {
                    self::toBuyer($o, ucfirst($noun) . ' cancelled',
                        $o->actedBy === 'seller'
                            ? "The seller cancelled your {$noun} for {$title}."
                            : "Your {$noun} for {$title} was cancelled.",
                        self::orderData($o, $type));
                }
                if ($o->actedBy !== 'seller') {
                    self::toUser($o->seller, ucfirst($noun) . ' cancelled',
                        "{$o->buyerName()} cancelled their {$noun} for {$title}.", self::orderData($o, $type));
                }
                break;
        }

        foreach (array_filter([$o->buyer_id, $o->seller_id]) as $userId) {
            RealtimeUpdate::toUser((int) $userId, 'shop_order.updated', ['order_id' => $o->id, 'status' => $o->status]);
        }
    }

    private static function orderData(MarketplaceOrder $o, string $type): array
    {
        return [
            'type'       => $type,
            'order_id'   => (string) $o->id,
            'order_type' => $o->order_type,
            'status'     => $o->status,
            'product_id' => (string) $o->product_id,
        ];
    }

    private static function toBuyer(MarketplaceOrder $o, string $title, string $body, array $data): void
    {
        if ($o->isGuest()) {
            if ($o->guest_fcm_token) app(FcmService::class)->sendToToken($o->guest_fcm_token, $title, $body, $data);
            return;
        }
        self::toUser($o->buyer, $title, $body, $data);
    }

    // ── Car rentals (renting a listed vehicle) ────────────────────────────────

    private static function rentalBooked(CarRental $r): void
    {
        $r->loadMissing(['user', 'marketplaceProduct.seller']);
        $title = $r->marketplaceProduct?->title ?? ucfirst(str_replace('_', ' ', $r->vehicle_type));
        $dates = "{$r->start_date->toDateString()} → {$r->end_date->toDateString()}";

        self::toRenter($r, 'Rental booked 🔑',
            "Your rental of {$title} ({$dates}) is pending confirmation. We'll notify you when it's confirmed.",
            self::rentalData($r, 'shop_rental_booked'));

        self::toUser($r->marketplaceProduct?->seller, '🔑 New rental request',
            "{$r->renterName()} wants to rent {$title} ({$dates}, {$r->total_days} day" . ($r->total_days === 1 ? '' : 's') . ').',
            self::rentalData($r, 'shop_rental_new'));
    }

    private static function rentalStatusChanged(CarRental $r): void
    {
        $r->loadMissing(['user', 'marketplaceProduct.seller']);
        $title = $r->marketplaceProduct?->title ?? 'your vehicle';
        $type  = 'shop_rental_' . $r->status;

        [$heading, $body] = match ($r->status) {
            'confirmed' => ['Rental confirmed ✅', "Your rental of {$title} from {$r->start_date->toDateString()} is confirmed."],
            'active'    => ['Rental started', "Your rental of {$title} has started. Drive safe!"],
            'completed' => ['Rental completed', "Your rental of {$title} is complete. Thanks for renting with us!"],
            'cancelled' => ['Rental cancelled', "Your rental of {$title} was cancelled."],
            default     => [null, null],
        };
        if (! $heading) return;

        if ($r->actedBy !== 'buyer') self::toRenter($r, $heading, $body, self::rentalData($r, $type));

        if ($r->status === 'cancelled' && $r->actedBy === 'buyer') {
            self::toUser($r->marketplaceProduct?->seller, 'Rental cancelled',
                "{$r->renterName()} cancelled their rental of {$title}.", self::rentalData($r, $type));
        }

        if ($r->user_id) {
            RealtimeUpdate::toUser((int) $r->user_id, 'shop_rental.updated', ['rental_id' => $r->id, 'status' => $r->status]);
        }
    }

    private static function rentalData(CarRental $r, string $type): array
    {
        return [
            'type'       => $type,
            'rental_id'  => (string) $r->id,
            'status'     => $r->status,
            'product_id' => (string) ($r->marketplace_product_id ?? ''),
        ];
    }

    private static function toRenter(CarRental $r, string $title, string $body, array $data): void
    {
        if ($r->isGuest()) {
            if ($r->guest_fcm_token) app(FcmService::class)->sendToToken($r->guest_fcm_token, $title, $body, $data);
            return;
        }
        self::toUser($r->user, $title, $body, $data);
    }

    // ── Shared ────────────────────────────────────────────────────────────────

    private static function toUser(?User $user, string $title, string $body, array $data): void
    {
        if ($user) app(FcmService::class)->notifyUser($user, $title, $body, $data);
    }

    private static function usd($amount): string
    {
        return '$' . number_format((float) $amount, 2);
    }
}

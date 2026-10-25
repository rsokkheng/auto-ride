<?php

namespace App\Support;

use App\Events\RealtimeUpdate;
use App\Models\CarRental;
use App\Models\MarketplaceOrder;
use App\Models\User;
use App\Services\FcmService;
use Illuminate\Database\Eloquent\Model;
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
    //
    // Text lives in lang/{en,km,zh}/push.php (push.shop.*); each person gets it
    // in their own language, product titles included.

    private static function orderPlaced(MarketplaceOrder $o): void
    {
        $o->loadMissing(['product', 'buyer', 'seller']);
        $title  = self::productTitle($o->product, LocalizedText::trans('push.shop.your_item'));
        $isRent = $o->order_type === 'rent';
        $data   = self::orderData($o, $isRent ? 'shop_rent_requested' : 'shop_order_placed');
        $start  = $o->rent_start_date?->toDateString();
        $end    = $o->rent_end_date?->toDateString();

        $batch = $o->checkout_batch_id;
        if (! $batch || ! isset(self::$notifiedBatches[$batch])) {
            if ($batch) self::$notifiedBatches[$batch] = true;
            self::toBuyer($o, ...match (true) {
                $isRent => self::msg('rent_requested', ['title' => $title, 'start' => $start, 'end' => $end]),
                (bool) $batch => self::msg('order_placed', [], 'body_batch'),
                default => self::msg('order_placed', ['title' => $title]),
            }, data: $data);
        }

        $replace = [
            'buyer' => $o->buyerName(), 'title' => $title, 'qty' => $o->quantity,
            'price' => self::usd($o->total_price), 'start' => $start, 'end' => $end,
            'address' => (string) $o->delivery_address,
        ];
        self::toUser($o->seller, ...($isRent
                ? self::msg('seller_new_rent', $replace)
                : self::msg('seller_new_order', $replace, $o->fulfillment === 'delivery' ? 'body_delivery' : 'body_pickup')),
            data: self::orderData($o, $isRent ? 'shop_rent_new' : 'shop_order_new'));
    }

    private static function orderStatusChanged(MarketplaceOrder $o): void
    {
        $o->loadMissing(['product', 'buyer', 'seller']);
        $title   = self::productTitle($o->product, LocalizedText::trans('push.shop.order_number', ['id' => $o->id]));
        $group   = $o->order_type === 'rent' ? 'rent_order' : 'order';
        $type    = 'shop_order_' . $o->status;
        $replace = ['title' => $title, 'buyer' => $o->buyerName(), 'price' => self::usd($o->total_price)];
        $msg     = fn (string $body) => self::msg("{$group}.{$o->status}", $replace, $body);

        switch ($o->status) {
            case 'confirmed':
                if ($o->actedBy !== 'buyer') {
                    self::toBuyer($o, ...$msg('body'), data: self::orderData($o, $type));
                }
                break;

            case 'completed':
                if ($o->actedBy !== 'buyer') {
                    self::toBuyer($o, ...$msg('body'), data: self::orderData($o, $type));
                }
                if ($o->actedBy !== 'seller') {
                    self::toUser($o->seller, ...$msg('seller_body'), data: self::orderData($o, $type));
                }
                break;

            case 'cancelled':
                if ($o->actedBy !== 'buyer') {
                    self::toBuyer($o, ...$msg($o->actedBy === 'seller' ? 'body_by_seller' : 'body'), data: self::orderData($o, $type));
                }
                if ($o->actedBy !== 'seller') {
                    self::toUser($o->seller, ...$msg('seller_body'), data: self::orderData($o, $type));
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

    private static function toBuyer(MarketplaceOrder $o, LocalizedText $title, LocalizedText $body, array $data): void
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
        $title = self::productTitle($r->marketplaceProduct,
            LocalizedText::plain(ucfirst(str_replace('_', ' ', (string) $r->vehicle_type))));
        $replace = [
            'title'  => $title,
            'dates'  => "{$r->start_date->toDateString()} → {$r->end_date->toDateString()}",
            'renter' => $r->renterName(),
            'days'   => $r->total_days,
        ];

        self::toRenter($r, ...self::msg('rental_booked', $replace), data: self::rentalData($r, 'shop_rental_booked'));

        self::toUser($r->marketplaceProduct?->seller,
            ...self::msg('rental_new', $replace, $r->total_days === 1 ? 'body_one' : 'body'),
            data: self::rentalData($r, 'shop_rental_new'));
    }

    private static function rentalStatusChanged(CarRental $r): void
    {
        $r->loadMissing(['user', 'marketplaceProduct.seller']);
        if (! in_array($r->status, ['confirmed', 'active', 'completed', 'cancelled'], true)) return;

        $title   = self::productTitle($r->marketplaceProduct, LocalizedText::trans('push.shop.your_vehicle'));
        $type    = 'shop_rental_' . $r->status;
        $replace = ['title' => $title, 'start' => $r->start_date->toDateString(), 'renter' => $r->renterName()];

        if ($r->actedBy !== 'buyer') {
            self::toRenter($r, ...self::msg("rental.{$r->status}", $replace), data: self::rentalData($r, $type));
        }

        if ($r->status === 'cancelled' && $r->actedBy === 'buyer') {
            self::toUser($r->marketplaceProduct?->seller,
                ...self::msg('rental.cancelled', $replace, 'seller_body'), data: self::rentalData($r, $type));
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

    private static function toRenter(CarRental $r, LocalizedText $title, LocalizedText $body, array $data): void
    {
        if ($r->isGuest()) {
            if ($r->guest_fcm_token) app(FcmService::class)->sendToToken($r->guest_fcm_token, $title, $body, $data);
            return;
        }
        self::toUser($r->user, $title, $body, $data);
    }

    // ── Shared ────────────────────────────────────────────────────────────────

    private static function toUser(?User $user, LocalizedText $title, LocalizedText $body, array $data): void
    {
        if ($user) app(FcmService::class)->notifyUser($user, $title, $body, $data);
    }

    /** [title, body] for push.shop.{key}. */
    private static function msg(string $key, array $replace = [], string $bodyKey = 'body'): array
    {
        return FcmService::message("shop.{$key}", $replace, $bodyKey);
    }

    private static function productTitle(?Model $product, LocalizedText $fallback): LocalizedText
    {
        return $product ? LocalizedText::fromModel($product, 'title') : $fallback;
    }

    private static function usd($amount): string
    {
        return '$' . number_format((float) $amount, 2);
    }
}

<?php

namespace App\Support;

use App\Events\RealtimeUpdate;
use App\Models\AdminNotification;
use App\Models\CarRental;
use App\Models\MarketplaceOrder;
use App\Models\User;
use App\Services\FcmService;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Spatie\Permission\Models\Permission;
use Throwable;

/**
 * Admin-panel alerts for shop purchases and rentals.
 *
 * Hooked at the model level (like ShopNotifications) so orders from any path —
 * app, guest checkout, seller panel — reach the admin bell. Each alert is
 * stored per admin (so it survives being offline) and pushed live over
 * Reverb on private-admin.{userId} as "admin_notification.created".
 *
 * Changes made by an admin ($model->actedBy === 'admin') don't alert admins.
 */
class AdminNotifications
{
    /** Admins need this permission to receive shop-order alerts (see RolePermissionSeeder). */
    public const PERMISSION = 'manage-deliveries';

    public static function register(): void
    {
        MarketplaceOrder::created(fn (MarketplaceOrder $o) => self::later(fn () => self::orderPlaced($o)));
        MarketplaceOrder::updated(function (MarketplaceOrder $o) {
            if ($o->actedBy === 'admin') return;
            // Read the dirty state now — it's gone by the time afterCommit runs.
            $actedBy = $o->actedBy;
            if ($o->wasChanged('status') && $o->status === 'cancelled') {
                self::later(fn () => self::orderCancelled($o, $actedBy));
            }
            if ($o->wasChanged('payment_status') && $o->payment_status === 'paid') {
                self::later(fn () => self::orderPaid($o));
            }
        });

        CarRental::created(fn (CarRental $r) => self::later(fn () => self::rentalBooked($r)));
        CarRental::updated(function (CarRental $r) {
            if ($r->actedBy === 'admin') return;
            $actedBy = $r->actedBy;
            if ($r->wasChanged('status') && $r->status === 'cancelled') {
                self::later(fn () => self::rentalCancelled($r, $actedBy));
            }
        });
    }

    // Stored after the order's transaction commits, so a rolled-back order
    // never leaves an alert behind. Failures are logged, never thrown — an
    // alert must not be able to break checkout.
    private static function later(callable $send): void
    {
        DB::afterCommit(function () use ($send) {
            try {
                $send();
            } catch (Throwable $e) {
                Log::error('[AdminNotifications] failed', ['error' => $e->getMessage()]);
            }
        });
    }

    // ── Marketplace orders ────────────────────────────────────────────────────

    private static function orderPlaced(MarketplaceOrder $o): void
    {
        $o->loadMissing(['product', 'buyer']);
        $isRent = $o->order_type === 'rent';

        self::notify(
            $isRent ? AdminNotification::RENTAL_ORDER : AdminNotification::PURCHASE_ORDER,
            $isRent ? 'rental' : 'purchase',
            AdminNotification::KIND_MARKETPLACE,
            $o->id,
            $isRent ? 'New Rental Order' : 'New Purchase Order',
            self::orderLabel($o),
            [
                'detail'   => $isRent
                    ? self::dateRange($o->rent_start_date, $o->rent_end_date) . ' · ' . self::usd($o->total_price)
                    : ($o->quantity > 1 ? "{$o->quantity}× · " : '') . self::usd($o->total_price),
                'customer' => $o->buyerName(),
                'amount'   => (string) $o->total_price,
            ],
        );
    }

    private static function orderPaid(MarketplaceOrder $o): void
    {
        $o->loadMissing(['product', 'buyer']);

        self::notify(
            AdminNotification::PAYMENT_SUCCESS, 'system', AdminNotification::KIND_MARKETPLACE, $o->id,
            'Payment Received',
            self::orderLabel($o),
            [
                'detail'   => self::usd($o->total_price) . ($o->payment_method ? ' · ' . strtoupper($o->payment_method) : ''),
                'customer' => $o->buyerName(),
                'amount'   => (string) $o->total_price,
            ],
        );
    }

    private static function orderCancelled(MarketplaceOrder $o, ?string $actedBy): void
    {
        $o->loadMissing(['product', 'buyer']);

        self::notify(
            AdminNotification::ORDER_CANCELLED, 'system', AdminNotification::KIND_MARKETPLACE, $o->id,
            $o->order_type === 'rent' ? 'Rental Order Cancelled' : 'Order Cancelled',
            self::orderLabel($o),
            ['detail' => self::cancelledBy($actedBy) . ' · ' . self::usd($o->total_price), 'customer' => $o->buyerName()],
        );
    }

    private static function orderLabel(MarketplaceOrder $o): string
    {
        $prefix = $o->order_type === 'rent' ? '#RE' : '#OD';
        return $prefix . str_pad((string) $o->id, 6, '0', STR_PAD_LEFT) . ' - ' . ($o->product?->title ?? 'Item');
    }

    // ── Car rentals ───────────────────────────────────────────────────────────

    private static function rentalBooked(CarRental $r): void
    {
        $r->loadMissing(['user', 'marketplaceProduct']);

        self::notify(
            AdminNotification::RENTAL_ORDER, 'rental', AdminNotification::KIND_CAR_RENTAL, $r->id,
            'New Rental Order',
            self::rentalLabel($r),
            [
                'detail'   => self::dateRange($r->start_date, $r->end_date) . ' · ' . self::khr($r->total_amount_khr),
                'customer' => $r->renterName(),
                'amount'   => (string) $r->total_amount_khr,
            ],
        );
    }

    private static function rentalCancelled(CarRental $r, ?string $actedBy): void
    {
        $r->loadMissing(['user', 'marketplaceProduct']);

        self::notify(
            AdminNotification::ORDER_CANCELLED, 'system', AdminNotification::KIND_CAR_RENTAL, $r->id,
            'Rental Cancelled',
            self::rentalLabel($r),
            ['detail' => self::cancelledBy($actedBy) . ' · ' . self::dateRange($r->start_date, $r->end_date), 'customer' => $r->renterName()],
        );
    }

    private static function rentalLabel(CarRental $r): string
    {
        $title = $r->marketplaceProduct?->title ?? ucfirst(str_replace('_', ' ', (string) $r->vehicle_type));
        return '#CR' . str_pad((string) $r->id, 6, '0', STR_PAD_LEFT) . ' - ' . $title;
    }

    // ── Shared ────────────────────────────────────────────────────────────────

    private static function notify(string $type, string $category, string $kind, int $orderId, string $title, string $message, array $data): void
    {
        $recipients = self::recipients();
        foreach ($recipients as $admin) {
            $n = AdminNotification::create([
                'user_id'    => $admin->id,
                'type'       => $type,
                'category'   => $category,
                'order_kind' => $kind,
                'order_id'   => $orderId,
                'title'      => $title,
                'message'    => $message,
                'data'       => $data,
            ]);

            RealtimeUpdate::dispatch("admin.{$admin->id}", 'admin_notification.created', [
                'notification' => $n->toPayload(),
                'counts'       => AdminNotification::unreadCounts($admin->id),
            ]);
        }

        self::pushToPhones($recipients, $title, $message, [
            'type'       => 'admin_alert',
            'alert_type' => $type,
            'order_kind' => $kind,
            'order_id'   => (string) $orderId,
        ]);
    }

    // Admins logged in on the mobile app get a phone push too. Sent after the
    // response on web requests so FCM round-trips never slow checkout down.
    private static function pushToPhones(Collection $admins, string $title, string $message, array $data): void
    {
        $admins = $admins->filter(fn (User $a) => $a->fcm_token)->values();
        if ($admins->isEmpty()) return;

        $send = function () use ($admins, $title, $message, $data) {
            $fcm = app(FcmService::class);
            foreach ($admins as $admin) {
                try {
                    $fcm->pushOnly($admin, $title, $message, $data);
                } catch (Throwable $e) {
                    Log::error('[AdminNotifications] push failed', ['admin_id' => $admin->id, 'error' => $e->getMessage()]);
                }
            }
        };

        app()->runningInConsole() ? $send() : app()->terminating($send);
    }

    /** Admins allowed to handle shop orders. Until the permission is seeded, every admin gets them. */
    private static function recipients(): Collection
    {
        $admins = User::where('role', 'admin')->get();

        if (! Permission::where('name', self::PERMISSION)->where('guard_name', 'web')->exists()) {
            return $admins;
        }

        return $admins->filter(fn (User $u) => $u->checkPermissionTo(self::PERMISSION))->values();
    }

    private static function cancelledBy(?string $actedBy): string
    {
        return match ($actedBy) {
            'buyer'  => 'Cancelled by customer',
            'seller' => 'Cancelled by seller',
            default  => 'Cancelled',
        };
    }

    private static function dateRange(?CarbonInterface $from, ?CarbonInterface $to): string
    {
        if (! $from || ! $to) return '—';
        return $from->format('M j') . ' – ' . $to->format('M j, Y');
    }

    private static function usd($amount): string
    {
        return '$' . number_format((float) $amount, 2);
    }

    private static function khr($amount): string
    {
        return number_format((int) $amount) . ' ៛';
    }
}

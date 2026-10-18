<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Admin-panel alert about a shop order (bell dropdown, dashboard banner,
 * /admin/notifications). Created by App\Support\AdminNotifications.
 */
class AdminNotification extends Model
{
    use MassPrunable;

    public const PURCHASE_ORDER  = 'PURCHASE_ORDER';
    public const RENTAL_ORDER    = 'RENTAL_ORDER';
    public const PAYMENT_SUCCESS = 'PAYMENT_SUCCESS';
    public const ORDER_CANCELLED = 'ORDER_CANCELLED';

    public const CATEGORIES = ['purchase', 'rental', 'system'];

    /** type => [Font Awesome icon, tone] — tone maps to a colour class in the bell partial. */
    public const ICONS = [
        self::PURCHASE_ORDER  => ['fa-cart-shopping', 'green'],
        self::RENTAL_ORDER    => ['fa-calendar-days', 'red'],
        self::PAYMENT_SUCCESS => ['fa-circle-dollar-to-slot', 'blue'],
        self::ORDER_CANCELLED => ['fa-ban', 'amber'],
    ];

    public const KIND_MARKETPLACE = 'marketplace_order';
    public const KIND_CAR_RENTAL  = 'car_rental';

    protected $fillable = [
        'user_id',
        'type',
        'category',
        'order_kind',
        'order_id',
        'title',
        'message',
        'data',
        'read_at',
    ];

    protected $casts = [
        'data'    => 'array',
        'read_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeForUser(Builder $q, int $userId): Builder
    {
        return $q->where('user_id', $userId);
    }

    public function scopeUnread(Builder $q): Builder
    {
        return $q->whereNull('read_at');
    }

    /** Read alerts are kept 90 days so admins can look back; unread ones are never pruned. */
    public function prunable(): Builder
    {
        return static::whereNotNull('read_at')->where('created_at', '<', now()->subDays(90));
    }

    /** The admin page where this order can be confirmed / rejected / handled. */
    public function orderUrl(): string
    {
        return $this->order_kind === self::KIND_CAR_RENTAL
            ? route('admin.car-rentals', ['id' => $this->order_id])
            : route('admin.marketplace-orders', ['id' => $this->order_id]);
    }

    /** Unread counts per dropdown tab, plus 'all'. */
    public static function unreadCounts(int $userId): array
    {
        $counts = static::forUser($userId)->unread()
            ->selectRaw('category, count(*) as total')
            ->groupBy('category')
            ->pluck('total', 'category');

        $out = ['all' => (int) $counts->sum()];
        foreach (self::CATEGORIES as $c) {
            $out[$c] = (int) ($counts[$c] ?? 0);
        }
        return $out;
    }

    /** Shape shared by the JSON feed and the realtime broadcast. */
    public function toPayload(): array
    {
        [$icon, $tone] = self::ICONS[$this->type] ?? ['fa-bell', 'blue'];

        return [
            'id'         => $this->id,
            'type'       => $this->type,
            'category'   => $this->category,
            'title'      => $this->title,
            'message'    => $this->message,
            'detail'     => $this->data['detail'] ?? null,
            'icon'       => $icon,
            'tone'       => $tone,
            'url'        => route('admin.notifications.open', $this),
            'read'       => $this->read_at !== null,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}

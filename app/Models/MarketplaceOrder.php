<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MarketplaceOrder extends Model
{
    protected $fillable = [
        'checkout_batch_id',
        'product_id',
        'buyer_id',
        'entry_type',
        'guest_name',
        'guest_phone',
        'guest_token',
        'guest_fcm_token',
        'seller_id',
        'order_type',
        'fulfillment',
        'delivery_address',
        'delivery_lat',
        'delivery_lng',
        'quantity',
        'unit_price',
        'total_price',
        'accessories_total',
        'rent_start_date',
        'rent_end_date',
        'status',
        'payment_method',
        'payment_status',
        'notes',
    ];

    protected $hidden = ['guest_token', 'guest_fcm_token'];

    /** Not persisted: 'buyer' | 'seller' | 'admin' — who triggered the current save, so they aren't notified about their own action. */
    public ?string $actedBy = null;

    protected $casts = [
        'unit_price'         => 'decimal:2',
        'total_price'        => 'decimal:2',
        'accessories_total'  => 'decimal:2',
        'quantity'           => 'integer',
        'rent_start_date'    => 'date',
        'rent_end_date'      => 'date',
        'delivery_lat'       => 'float',
        'delivery_lng'       => 'float',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(MarketplaceProduct::class, 'product_id');
    }

    public function accessories(): HasMany
    {
        return $this->hasMany(MarketplaceOrderAccessory::class, 'order_id');
    }

    public function isGuest(): bool
    {
        return $this->entry_type === 'guest';
    }

    public function buyerName(): string
    {
        return $this->isGuest() ? ($this->guest_name ?? 'Guest') : ($this->buyer?->name ?? '—');
    }

    public function buyerPhone(): ?string
    {
        return $this->isGuest() ? $this->guest_phone : $this->buyer?->phone;
    }

    public function buyer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'buyer_id');
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seller_id');
    }
}

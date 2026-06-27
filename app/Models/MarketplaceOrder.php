<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketplaceOrder extends Model
{
    protected $fillable = [
        'product_id',
        'buyer_id',
        'entry_type',
        'guest_name',
        'guest_phone',
        'seller_id',
        'order_type',
        'quantity',
        'unit_price',
        'total_price',
        'rent_start_date',
        'rent_end_date',
        'status',
        'payment_method',
        'payment_status',
        'notes',
    ];

    protected $casts = [
        'unit_price'      => 'integer',
        'total_price'     => 'integer',
        'quantity'        => 'integer',
        'rent_start_date' => 'date',
        'rent_end_date'   => 'date',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(MarketplaceProduct::class, 'product_id');
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

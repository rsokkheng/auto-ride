<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MarketplaceItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'seller_id',
        'entry_type',
        'guest_name',
        'guest_phone',
        'vehicle_id',
        'title',
        'description',
        'type',
        'price',
        'rent_rate',
        'available',
        'condition',
    ];

    protected $casts = [
        'price'     => 'decimal:2',
        'rent_rate' => 'decimal:2',
        'available' => 'boolean',
    ];

    public function isGuest(): bool
    {
        return $this->entry_type === 'guest';
    }

    public function sellerName(): string
    {
        return $this->isGuest() ? ($this->guest_name ?? 'Guest') : ($this->seller?->name ?? '—');
    }

    public function sellerPhone(): ?string
    {
        return $this->isGuest() ? $this->guest_phone : $this->seller?->phone;
    }

    public function images(): HasMany
    {
        return $this->hasMany(MarketplaceItemImage::class)->orderBy('sort_order');
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }
}

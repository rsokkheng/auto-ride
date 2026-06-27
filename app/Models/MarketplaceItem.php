<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
        'price'     => 'integer',
        'rent_rate' => 'integer',
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

    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }
}

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
        'price' => 'decimal:2',
        'rent_rate' => 'decimal:2',
        'available' => 'boolean',
    ];

    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }
}

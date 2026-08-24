<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketplaceOrderAccessory extends Model
{
    protected $fillable = [
        'order_id',
        'accessory_id',
        'name_en',
        'name_km',
        'price',
    ];

    protected $casts = [
        'price' => 'decimal:2',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(MarketplaceOrder::class, 'order_id');
    }

    public function accessory(): BelongsTo
    {
        return $this->belongsTo(MarketplaceProductAccessory::class, 'accessory_id');
    }
}

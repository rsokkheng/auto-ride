<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MarketplaceVehicleSize extends Model
{
    protected $fillable = ['label', 'value_meters', 'sort_order', 'active'];

    protected $casts = [
        'value_meters' => 'decimal:2',
        'sort_order'   => 'integer',
        'active'       => 'boolean',
    ];

    public function scopeActive($query)
    {
        return $query->where('active', true);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MarketplaceVehicleColor extends Model
{
    protected $fillable = ['name_en', 'name_km', 'code', 'sort_order', 'active'];

    protected $casts = [
        'active'     => 'boolean',
        'sort_order' => 'integer',
    ];

    public function scopeActive($query)
    {
        return $query->where('active', true);
    }
}

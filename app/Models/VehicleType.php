<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VehicleType extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'icon',
        'description',
        'capacity',
        'base_fare',
        'per_km_fare',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'is_active'    => 'boolean',
        'capacity'     => 'integer',
        'base_fare'    => 'float',
        'per_km_fare'  => 'float',
        'sort_order'   => 'integer',
    ];

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}

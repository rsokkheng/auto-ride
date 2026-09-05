<?php

namespace App\Models;

use App\Traits\HasLocalizedName;
use Illuminate\Database\Eloquent\Model;

class VehicleType extends Model
{
    use HasLocalizedName;

    protected $fillable = [
        'name',
        'name_en',
        'name_kh',
        'name_zh',
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

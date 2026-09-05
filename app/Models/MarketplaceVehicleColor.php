<?php

namespace App\Models;

use App\Traits\HasLocalizedName;
use Illuminate\Database\Eloquent\Model;

class MarketplaceVehicleColor extends Model
{
    use HasLocalizedName;

    protected $fillable = ['name_en', 'name_kh', 'name_zh', 'code', 'sort_order', 'active'];

    protected $casts = [
        'active'     => 'boolean',
        'sort_order' => 'integer',
    ];

    public function scopeActive($query)
    {
        return $query->where('active', true);
    }
}

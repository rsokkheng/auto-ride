<?php

namespace App\Models;

use App\Traits\HasLocalizedName;
use Illuminate\Database\Eloquent\Model;

class MarketplaceVehicleSize extends Model
{
    use HasLocalizedName;

    protected $fillable = ['label', 'label_en', 'label_kh', 'label_zh', 'value_meters', 'sort_order', 'active'];

    protected $casts = [
        'value_meters' => 'decimal:2',
        'sort_order'   => 'integer',
        'active'       => 'boolean',
    ];

    protected function localizedNameColumn(): string
    {
        return 'label';
    }

    public function getLabelAttribute(): ?string
    {
        return $this->resolveLocalizedName('label');
    }

    public function scopeActive($query)
    {
        return $query->where('active', true);
    }
}

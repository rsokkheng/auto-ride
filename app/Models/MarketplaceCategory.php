<?php

namespace App\Models;

use App\Traits\HasLocalizedName;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MarketplaceCategory extends Model
{
    use HasLocalizedName;

    protected $fillable = ['parent_id', 'name', 'name_en', 'name_kh', 'name_zh', 'slug', 'icon', 'sort_order', 'active'];

    protected $casts = ['active' => 'boolean'];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(MarketplaceCategory::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(MarketplaceCategory::class, 'parent_id');
    }

    public function products(): HasMany
    {
        return $this->hasMany(MarketplaceProduct::class, 'category_id');
    }
}

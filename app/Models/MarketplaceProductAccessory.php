<?php

namespace App\Models;

use App\Traits\HasLocalizedName;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketplaceProductAccessory extends Model
{
    use HasLocalizedName;

    protected $fillable = [
        'product_id',
        'name_en',
        'name_kh',
        'name_zh',
        'price',
        'sort_order',
        'active',
    ];

    protected $casts = [
        'price'      => 'decimal:2',
        'sort_order' => 'integer',
        'active'     => 'boolean',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(MarketplaceProduct::class, 'product_id');
    }
}

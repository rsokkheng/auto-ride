<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class MarketplaceProductImage extends Model
{
    protected $fillable = ['product_id', 'url', 'disk', 'sort_order'];

    protected $appends = ['full_url'];

    public function product(): BelongsTo
    {
        return $this->belongsTo(MarketplaceProduct::class, 'product_id');
    }

    public function getFullUrlAttribute(): string
    {
        if (str_starts_with($this->url, 'http')) {
            return $this->url;
        }
        return Storage::disk($this->disk ?? 'public')->url($this->url);
    }
}

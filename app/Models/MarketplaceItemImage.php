<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class MarketplaceItemImage extends Model
{
    protected $fillable = ['marketplace_item_id', 'path', 'disk', 'sort_order'];

    public function item(): BelongsTo
    {
        return $this->belongsTo(MarketplaceItem::class, 'marketplace_item_id');
    }

    public function url(): string
    {
        return Storage::disk($this->disk)->url($this->path);
    }
}

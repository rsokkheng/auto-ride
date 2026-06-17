<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Banner extends Model
{
    protected $fillable = [
        'title', 'image', 'deeplink', 'target_role',
        'sort_order', 'active', 'valid_from', 'valid_until',
    ];

    protected $casts = [
        'active'     => 'boolean',
        'sort_order' => 'integer',
        'valid_from' => 'datetime',
        'valid_until'=> 'datetime',
    ];

    protected $appends = ['image_url'];

    public function getImageUrlAttribute(): string
    {
        return asset('storage/' . $this->image);
    }
}

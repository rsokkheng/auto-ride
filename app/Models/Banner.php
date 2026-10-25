<?php

namespace App\Models;

use App\Traits\HasLocalizedName;
use Illuminate\Database\Eloquent\Model;

class Banner extends Model
{
    use HasLocalizedName;

    /** Columns with _en / _kh / _zh variants (see HasLocalizedName). */
    protected array $localized = ['title'];
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

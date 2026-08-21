<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PromoEvent extends Model
{
    protected $fillable = [
        'title', 'body', 'image', 'target_role', 'active', 'created_by', 'sent_at',
    ];

    protected $casts = [
        'active'  => 'boolean',
        'sent_at' => 'datetime',
    ];

    protected $appends = ['image_url'];

    public function getImageUrlAttribute(): ?string
    {
        return $this->image ? asset('storage/' . $this->image) : null;
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}

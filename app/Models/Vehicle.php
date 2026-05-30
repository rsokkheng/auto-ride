<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Vehicle extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'license_plate',
        'make',
        'model',
        'year',
        'type',
        'status',
        'capacity',
        'details',
        'images',
    ];

    protected $casts = [
        'images' => 'array',
    ];

    /** Full public URLs for all vehicle images. */
    public function getImageUrlsAttribute(): array
    {
        return collect($this->images ?? [])
            ->map(fn(string $path) => asset('storage/' . $path))
            ->values()
            ->all();
    }

    /** URL of the first (primary) image, or null. */
    public function getPrimaryImageUrlAttribute(): ?string
    {
        $first = ($this->images ?? [])[0] ?? null;
        return $first ? asset('storage/' . $first) : null;
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}

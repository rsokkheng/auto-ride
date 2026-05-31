<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SurgeZone extends Model
{
    protected $fillable = [
        'name',
        'description',
        'center_lat',
        'center_lng',
        'radius_km',
        'multiplier',
        'type',
        'active',
        'starts_at',
        'ends_at',
    ];

    protected $casts = [
        'center_lat' => 'float',
        'center_lng' => 'float',
        'radius_km'  => 'float',
        'multiplier' => 'float',
        'active'     => 'boolean',
        'starts_at'  => 'datetime',
        'ends_at'    => 'datetime',
    ];

    public function isActiveNow(): bool
    {
        if (! $this->active) return false;
        $now = now();
        if ($this->starts_at && $now->isBefore($this->starts_at)) return false;
        if ($this->ends_at   && $now->isAfter($this->ends_at))   return false;
        return true;
    }

    public function getMultiplierLabelAttribute(): string
    {
        return 'x' . number_format($this->multiplier, 2) . ' (' . round(($this->multiplier - 1) * 100) . '% surge)';
    }
}

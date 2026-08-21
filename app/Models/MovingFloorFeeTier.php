<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MovingFloorFeeTier extends Model
{
    protected $fillable = ['max_floor', 'fee'];

    protected $casts = [
        'max_floor' => 'integer',
        'fee'       => 'integer',
    ];

    /** Tiers ordered lowest floor-threshold first, with the open-ended ("N+") tier last. */
    public static function ordered()
    {
        return static::orderByRaw('max_floor IS NULL, max_floor ASC')->get();
    }
}

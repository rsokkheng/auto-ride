<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class HolidayPricing extends Model
{
    protected $table = 'holiday_pricing';

    protected $fillable = [
        'date',
        'label',
        'surcharge_rate',
        'active',
    ];

    protected $casts = [
        'date'           => 'date',
        'surcharge_rate' => 'float',
        'active'         => 'boolean',
    ];

    /** The active holiday row for the given date, if any (today by default). */
    public static function forDate(?Carbon $date = null): ?self
    {
        return static::where('active', true)
            ->whereDate('date', ($date ?? now())->toDateString())
            ->first();
    }
}

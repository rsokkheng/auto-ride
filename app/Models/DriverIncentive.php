<?php

namespace App\Models;

use App\Traits\HasLocalizedName;
use Illuminate\Database\Eloquent\Model;

class DriverIncentive extends Model
{
    use HasLocalizedName;

    /** Columns with _en / _kh / _zh variants (see HasLocalizedName). */
    protected array $localized = ['title', 'description'];
    protected $fillable = [
        'driver_id', 'title', 'description', 'target_trips', 'bonus_amount',
        'period_start', 'period_end', 'status', 'earned_at',
    ];

    protected $casts = [
        'period_start' => 'datetime',
        'period_end'   => 'datetime',
        'earned_at'    => 'datetime',
    ];

    public function driver()
    {
        return $this->belongsTo(User::class, 'driver_id');
    }

    public function currentProgress(): int
    {
        return Ride::where('driver_id', $this->driver_id)
            ->where('status', 'completed')
            ->whereBetween('completed_at', [$this->period_start, $this->period_end])
            ->count();
    }
}

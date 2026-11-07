<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** The day's Partner-package process confirmed finished by the accountant. */
class DailyClosing extends Model
{
    protected $fillable = ['business_date', 'drivers_closed', 'partners_closed', 'totals', 'note', 'closed_by', 'closed_at'];

    protected $casts = ['business_date' => 'date:Y-m-d', 'closed_at' => 'datetime', 'totals' => 'array'];

    public function closer(): BelongsTo { return $this->belongsTo(User::class, 'closed_by'); }

    public static function isClosed(string $date): bool
    {
        return static::whereDate('business_date', $date)->exists();
    }
}

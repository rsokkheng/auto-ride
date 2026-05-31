<?php

namespace App\Models;

use Carbon\Carbon;
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
        'schedule_days',
        'schedule_start_time',
        'schedule_end_time',
    ];

    protected $casts = [
        'center_lat'    => 'float',
        'center_lng'    => 'float',
        'radius_km'     => 'float',
        'multiplier'    => 'float',
        'active'        => 'boolean',
        'starts_at'     => 'datetime',
        'ends_at'       => 'datetime',
        'schedule_days' => 'array',   // [0,1,2,3,4,5,6]  null = every day
    ];

    /**
     * Whether this zone is active at the given moment.
     *
     * Checks (in order):
     *  1. active flag
     *  2. one-time date window (starts_at / ends_at)
     *  3. recurring day-of-week filter (schedule_days)
     *  4. recurring time-of-day window (schedule_start_time / schedule_end_time)
     */
    public function isActiveNow(?Carbon $at = null): bool
    {
        if (! $this->active) return false;

        $now = $at ?? now();

        // ── One-time window ──────────────────────────────────────────────────
        if ($this->starts_at && $now->isBefore($this->starts_at)) return false;
        if ($this->ends_at   && $now->isAfter($this->ends_at))   return false;

        // ── Recurring day-of-week ────────────────────────────────────────────
        // Carbon: dayOfWeek — 0 = Sunday … 6 = Saturday (matches JS Date.getDay())
        if (! empty($this->schedule_days) && ! in_array($now->dayOfWeek, $this->schedule_days, true)) {
            return false;
        }

        // ── Recurring time window ────────────────────────────────────────────
        if ($this->schedule_start_time || $this->schedule_end_time) {
            $currentTime = $now->format('H:i:s');

            if ($this->schedule_start_time && $currentTime < $this->schedule_start_time) return false;
            if ($this->schedule_end_time   && $currentTime > $this->schedule_end_time)   return false;
        }

        return true;
    }

    /** Human-readable schedule label for the admin table. */
    public function getScheduleLabelAttribute(): string
    {
        $parts = [];

        if ($this->starts_at || $this->ends_at) {
            $from  = $this->starts_at?->format('Y-m-d') ?? '—';
            $until = $this->ends_at?->format('Y-m-d')   ?? '∞';
            $parts[] = "{$from} → {$until}";
        }

        if (! empty($this->schedule_days)) {
            $names = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
            $days  = collect($this->schedule_days)->sort()->map(fn($d) => $names[$d] ?? '?')->join(', ');
            $parts[] = $days;
        }

        if ($this->schedule_start_time || $this->schedule_end_time) {
            $from  = $this->schedule_start_time ? substr($this->schedule_start_time, 0, 5) : '00:00';
            $until = $this->schedule_end_time   ? substr($this->schedule_end_time, 0, 5)   : '24:00';
            $parts[] = "{$from} – {$until}";
        }

        return $parts ? implode(' · ', $parts) : 'Always';
    }

    public function getMultiplierLabelAttribute(): string
    {
        return 'x' . number_format($this->multiplier, 2) . ' (+' . round(($this->multiplier - 1) * 100) . '%)';
    }
}

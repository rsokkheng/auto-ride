<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DriverSession extends Model
{
    protected $fillable = ['driver_id', 'started_at', 'ended_at'];

    protected $casts = [
        'started_at' => 'datetime',
        'ended_at'   => 'datetime',
    ];

    public function driver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'driver_id');
    }

    /** Duration in minutes. Returns null if session is still open. */
    public function durationMinutes(): ?int
    {
        if (! $this->ended_at) return null;
        return (int) $this->started_at->diffInMinutes($this->ended_at);
    }
}

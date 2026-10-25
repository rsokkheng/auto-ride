<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A request offered to a driver that they declined or let time out. */
class DriverMissedRequest extends Model
{
    public const TYPES   = ['ride', 'delivery', 'rental'];
    public const REASONS = ['declined', 'missed'];

    protected $fillable = ['driver_id', 'request_type', 'request_id', 'reason', 'summary'];

    protected $casts = ['summary' => 'array'];

    public function driver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'driver_id');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RideDecline extends Model
{
    protected $fillable = ['driver_id', 'ride_id'];

    public function driver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'driver_id');
    }

    public function ride(): BelongsTo
    {
        return $this->belongsTo(Ride::class);
    }
}

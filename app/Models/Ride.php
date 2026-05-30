<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Ride extends Model
{
    use HasFactory;

    protected $fillable = [
        'passenger_id',
        'driver_id',
        'vehicle_id',
        'pickup_address',
        'dropoff_address',
        'scheduled_at',
        'status',
        'fare',
        'payment_method',
        'payment_status',
        'service_type',
        'notes',
        'rating',
        'rating_comment',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'fare'         => 'integer',
        'rating'       => 'float',
    ];

    public function passenger(): BelongsTo
    {
        return $this->belongsTo(User::class, 'passenger_id');
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'driver_id');
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function locations(): HasMany
    {
        return $this->hasMany(RideLocation::class);
    }
}

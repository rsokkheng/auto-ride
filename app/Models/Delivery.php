<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Delivery extends Model
{
    use HasFactory;

    protected $fillable = [
        'sender_id',
        'sender_name',
        'recipient_name',
        'recipient_phone',
        'package_size',
        'driver_id',
        'vehicle_id',
        'pickup_address',
        'dropoff_address',
        'scheduled_at',
        'status',
        'package_details',
        'fee',
        'notes',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'fee' => 'decimal:2',
    ];

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'driver_id');
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }
}

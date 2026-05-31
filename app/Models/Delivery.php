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
        'pickup_lat',
        'pickup_lng',
        'scheduled_at',
        'status',
        'package_details',
        'fee',
        'payment_by',
        'payment_method',
        'payment_status',
        'notes',
        'rating',
        'rating_comment',
        'assigned_at',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'assigned_at'  => 'datetime',
        'fee'          => 'integer',
        'rating'       => 'float',
        'pickup_lat'   => 'float',
        'pickup_lng'   => 'float',
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

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SafetyIncident extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'ride_id',
        'delivery_id',
        'incident_type',
        'description',
        'reported_at',
        'status',
        'resolution',
    ];

    protected $casts = [
        'reported_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function ride(): BelongsTo
    {
        return $this->belongsTo(Ride::class);
    }

    public function delivery(): BelongsTo
    {
        return $this->belongsTo(Delivery::class);
    }
}

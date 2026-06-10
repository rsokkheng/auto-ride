<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Ride extends Model
{
    use HasFactory;

    // ── Status constants ───────────────────────────────────────────────────────
    const STATUS_REQUESTED      = 'requested';
    const STATUS_ACCEPTED       = 'accepted';
    const STATUS_DRIVER_ARRIVED = 'driver_arrived';
    const STATUS_IN_PROGRESS    = 'in_progress';
    const STATUS_COMPLETED      = 'completed';
    const STATUS_CANCELLED      = 'cancelled';

    /** Statuses a driver can still accept. */
    const OPEN_STATUSES = [self::STATUS_REQUESTED, 'pending'];

    /** Statuses that allow cancellation. */
    const CANCELLABLE_STATUSES = [
        self::STATUS_REQUESTED,
        'pending',
        self::STATUS_ACCEPTED,
        self::STATUS_DRIVER_ARRIVED,
    ];

    protected $fillable = [
        'passenger_id',
        'driver_id',
        'vehicle_id',
        'pickup_address',
        'dropoff_address',
        'pickup_lat',
        'pickup_lng',
        'dropoff_lat',
        'dropoff_lng',
        'scheduled_at',
        'status',
        'fare',
        'surge_multiplier',
        'surge_zone_id',
        'surge_accepted',
        'payment_method',
        'payment_status',
        'service_type',
        'notes',
        'rating',
        'rating_comment',
        // Status timestamps
        'accepted_at',
        'driver_arrived_at',
        'started_at',
        'completed_at',
        'cancelled_at',
        // New feature columns
        'passenger_name',
        'passenger_phone',
        'cancellation_fee',
        'cancellation_reason',
        'share_token',
        'share_active',
        'pickup_timeout_at',
        'promo_code_id',
        'discount_amount',
    ];

    protected $casts = [
        'scheduled_at'      => 'datetime',
        'accepted_at'       => 'datetime',
        'driver_arrived_at' => 'datetime',
        'started_at'        => 'datetime',
        'completed_at'      => 'datetime',
        'cancelled_at'      => 'datetime',
        'pickup_timeout_at' => 'datetime',
        'fare'              => 'integer',
        'cancellation_fee'  => 'integer',
        'discount_amount'   => 'integer',
        'share_active'      => 'boolean',
        'rating'            => 'float',
        'pickup_lat'        => 'float',
        'pickup_lng'        => 'float',
        'dropoff_lat'       => 'float',
        'dropoff_lng'       => 'float',
        'surge_multiplier'  => 'float',
        'surge_accepted'    => 'boolean',
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

    public function stops(): HasMany
    {
        return $this->hasMany(RideStop::class)->orderBy('sort_order');
    }

    public function promoCode(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(PromoCode::class);
    }
}

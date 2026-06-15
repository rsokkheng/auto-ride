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
        'sender_phone',
        'recipient_name',
        'recipient_phone',
        'package_size',
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
        'package_details',
        'fee',
        'payment_by',
        'payment_method',
        'payment_status',
        'notes',
        'rating',
        'rating_comment',
        'assigned_at',
        'surge_multiplier',
        'surge_zone_id',
        // Service option: normal or express
        'service_option',
        // Moving service
        'service_type',
        'floor_pickup',
        'floor_dropoff',
        'has_elevator',
        'needs_stairs_carry',
        'heavy_items',
        'requires_helpers',
        'helper_type',
        'helper_fee',
        'floor_fee',
        'express_multiplier',
        // Payment model
        'payment_model',
        'split_pct_customer',
        'partner_reference',
        // New feature columns
        'proof_photo',
        'promo_code_id',
        'discount_amount',
        'cancellation_reason',
        'cancellation_fee',
    ];

    protected $casts = [
        'scheduled_at'    => 'datetime',
        'assigned_at'     => 'datetime',
        'surge_multiplier'=> 'float',
        'fee'              => 'integer',
        'rating'           => 'float',
        'pickup_lat'       => 'float',
        'pickup_lng'       => 'float',
        'dropoff_lat'      => 'float',
        'dropoff_lng'      => 'float',
        'express_multiplier' => 'float',
        'has_elevator'     => 'boolean',
        'needs_stairs_carry' => 'boolean',
        'heavy_items'      => 'boolean',
        'floor_pickup'     => 'integer',
        'floor_dropoff'    => 'integer',
        'requires_helpers' => 'integer',
        'helper_fee'          => 'integer',
        'floor_fee'           => 'integer',
        'split_pct_customer'  => 'integer',
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

    public function stops()
    {
        return $this->hasMany(DeliveryStop::class)->orderBy('sort_order');
    }

    public function promoCode(): BelongsTo
    {
        return $this->belongsTo(PromoCode::class);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Delivery extends Model
{
    use HasFactory;

    /*
    |--------------------------------------------------------------------------
    | Status vocabulary
    |--------------------------------------------------------------------------
    | Two flows write to this table and each has its own status names:
    |   Customer app : requested → accepted → in_progress → completed
    |   Partner / QR : created → assigned → accepted → picked_up → in_transit
    |                  → delivered
    | Guards must therefore compare against these groups rather than a single
    | literal, otherwise a partner order slips past a check written for the
    | customer flow (and vice versa).
    */

    /** Awaiting a driver — still assignable/editable. */
    public const OPEN_STATUSES = ['requested', 'pending', 'created'];

    /** Driver is committed but has not collected the package yet. */
    public const PRE_PICKUP_STATUSES = ['requested', 'pending', 'created', 'assigned', 'accepted'];

    /** Package is with the driver and moving. */
    public const IN_TRANSIT_STATUSES = ['in_progress', 'picked_up', 'in_transit'];

    /** Job finished successfully — payment due, rateable. */
    public const FINISHED_STATUSES = ['delivered', 'completed'];

    /** No further transitions allowed. */
    public const TERMINAL_STATUSES = ['delivered', 'completed', 'cancelled'];

    public function isTerminal(): bool
    {
        return in_array($this->status, self::TERMINAL_STATUSES, true);
    }

    public function isFinished(): bool
    {
        return in_array($this->status, self::FINISHED_STATUSES, true);
    }

    public function isInTransit(): bool
    {
        return in_array($this->status, self::IN_TRANSIT_STATUSES, true);
    }

    public function isPaid(): bool
    {
        return $this->payment_status === 'paid';
    }

    // ── Public tracking link ─────────────────────────────────────────────────

    /** Path segment of the public tracking page, shared by delivery and moving. */
    public const TRACK_PATH = 'track/delivery_moving';

    /**
     * Mint the share token if this booking does not have one yet, so older rows
     * (and partner/admin-created orders) also get a link the first time it is asked for.
     */
    public function ensureShareToken(): string
    {
        if (! $this->share_token) {
            $this->forceFill(['share_token' => Str::random(32)])->save();
        }

        return $this->share_token;
    }

    /** Public tracking URL, or null while no token has been minted. */
    public function getTrackingUrlAttribute(): ?string
    {
        return $this->share_token
            ? url(self::TRACK_PATH . '/' . $this->share_token)
            : null;
    }

    /** Live tracking is only meaningful while the driver is en route. */
    public function isTrackable(): bool
    {
        return (bool) $this->share_active
            && in_array($this->status, array_merge(self::PRE_PICKUP_STATUSES, self::IN_TRANSIT_STATUSES), true);
    }

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
        'package_amount',
        'payment_by',
        'payment_method',
        'payment_status',
        'notes',
        'rating',
        'rating_comment',
        'assigned_at',
        'started_at',
        'completed_at',
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
        // Partner delivery
        'partner_id',
        'qr_token',
        'share_token',
        'share_active',
        'pickup_scanned_at',
        'delivery_scanned_at',
        'assignment_type',
    ];

    protected $casts = [
        'scheduled_at'       => 'datetime',
        'assigned_at'        => 'datetime',
        'started_at'         => 'datetime',
        'completed_at'       => 'datetime',
        'pickup_scanned_at'  => 'datetime',
        'delivery_scanned_at'=> 'datetime',
        'surge_multiplier'=> 'float',
        'fee'              => 'integer',
        'package_amount'   => 'integer',
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
        'share_active'        => 'boolean',
    ];

    /** Expose the tracking link everywhere a delivery is serialised. */
    protected $appends = ['tracking_url'];

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function partner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'partner_id');
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

    public function transactions()
    {
        return $this->morphMany(TransactionRecord::class, 'reference');
    }
}

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
    public const IN_TRANSIT_STATUSES = ['in_progress', 'picked_up', 'in_transit', 'delivery_failed'];

    /** Job finished successfully — payment due, rateable. */
    public const FINISHED_STATUSES = ['delivered', 'completed'];

    /** No further transitions allowed. */
    public const TERMINAL_STATUSES = ['delivered', 'completed', 'cancelled', 'returned'];

    /** Why a driver could not hand the package over (delivery_failed). */
    public const FAILURE_REASONS = ['cannot_contact', 'receiver_refused', 'wrong_address', 'receiver_postponed', 'other'];

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
        'package_type',
        'package_weight',
        'package_photo',
        'failure_reason',
        'failure_note',
        'failed_at',
        'returned_at',
        'hub_returned_at',
        'confirmed_at',
        'leg',
        'pickup_driver_id',
        'office_received_at',
        'office_received_by',
        'office_dispatched_at',
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
        'cancelled_by',
        'cancelled_at',
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
        'cancelled_at' => 'datetime',
        'scheduled_at'       => 'datetime',
        'assigned_at'        => 'datetime',
        'confirmed_at'       => 'datetime',
        'failed_at'          => 'datetime',
        'returned_at'        => 'datetime',
        'hub_returned_at'    => 'datetime',
        'package_weight'     => 'float',
        'started_at'         => 'datetime',
        'completed_at'       => 'datetime',
        'pickup_scanned_at'  => 'datetime',
        'delivery_scanned_at'=> 'datetime',
        'office_received_at'   => 'datetime',
        'office_dispatched_at' => 'datetime',
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

    // ── Partner packages ────────────────────────────────────────────────────

    /** Partner-facing package statuses, in workflow order. */
    public const PACKAGE_STATUSES = ['pending', 'confirmed', 'driver_assigned', 'picked_up', 'at_office', 'in_transit', 'completed', 'failed', 'at_roteh', 'returned', 'cancelled'];

    /**
     * Partner packages travel in two legs (see the 2026_11_15 migration):
     * LEG_PICKUP shop → office, then LEG_DELIVERY office → customer.
     */
    public const LEG_PICKUP   = 'pickup';
    public const LEG_DELIVERY = 'delivery';

    /** Map a raw deliveries.status (and leg) onto the Partner package status. */
    public static function packageStatusFor(string $status, ?string $leg = null): string
    {
        // Delivery leg: waiting at the office for its driver, then on the way to the customer.
        if ($leg === self::LEG_DELIVERY && in_array($status, ['assigned', 'accepted'], true)) return 'at_office';
        if ($leg === self::LEG_DELIVERY && $status === 'picked_up') return 'in_transit';

        return match ($status) {
            'at_office'                          => 'at_office',
            'created'                            => 'pending',
            'requested', 'pending'               => 'confirmed',
            'assigned', 'accepted'               => 'driver_assigned',
            'picked_up'                          => 'picked_up',
            'in_progress', 'in_transit'          => 'in_transit',
            'delivered', 'completed'             => 'completed',
            'delivery_failed'                    => 'failed',      // driver bringing it back to ROTEH
            'at_roteh'                           => 'at_roteh',    // at ROTEH — to hand back to the shop
            'returned'                           => 'returned',    // handed back to the shop
            'cancelled'                          => 'cancelled',
            default                              => $status,
        };
    }

    public function packageStatus(): string
    {
        return self::packageStatusFor((string) $this->status, $this->leg);
    }

    /** The partner may cancel until the package leaves the shop (never once it reached the office). */
    public function partnerCanCancel(): bool
    {
        return in_array($this->status, self::PRE_PICKUP_STATUSES, true) && ! $this->office_received_at;
    }

    /** On the shop → office leg (picked up or not yet). */
    public function onPickupLeg(): bool
    {
        return $this->leg === self::LEG_PICKUP && in_array($this->status, ['assigned', 'accepted', 'picked_up'], true);
    }

    /**
     * Admin workflow stage for Partner Deliveries:
     * new → pickup → at_office → out → done, or problem (failed / back at ROTEH / returned / cancelled).
     */
    public function workflowStage(): string
    {
        return match (true) {
            in_array($this->status, ['created', 'requested', 'pending'], true)        => 'new',
            $this->onPickupLeg()                                                      => 'pickup',
            $this->status === 'at_office',
            $this->leg === self::LEG_DELIVERY && in_array($this->status, ['assigned', 'accepted'], true) => 'at_office',
            in_array($this->status, self::FINISHED_STATUSES, true)                    => 'done',
            in_array($this->status, ['delivery_failed', 'at_roteh', 'returned', 'cancelled'], true) => 'problem',
            default                                                                   => 'out',
        };
    }

    /** Query scope per workflowStage() value — keep the two in step. */
    public function scopeInStage($query, string $stage)
    {
        return match ($stage) {
            'new'       => $query->whereIn('status', ['created', 'requested', 'pending']),
            'pickup'    => $query->where('leg', self::LEG_PICKUP)->whereIn('status', ['assigned', 'accepted', 'picked_up']),
            'at_office' => $query->where(fn ($q) => $q->where('status', 'at_office')
                ->orWhere(fn ($q) => $q->where('leg', self::LEG_DELIVERY)->whereIn('status', ['assigned', 'accepted']))),
            'out'       => $query->whereIn('status', ['assigned', 'accepted', 'picked_up', 'in_progress', 'in_transit'])
                ->where(fn ($q) => $q->whereNull('leg')->orWhere('leg', self::LEG_DELIVERY))
                ->where(fn ($q) => $q->whereIn('status', ['picked_up', 'in_progress', 'in_transit'])->orWhereNull('leg')),
            'done'      => $query->whereIn('status', self::FINISHED_STATUSES),
            'problem'   => $query->whereIn('status', ['delivery_failed', 'at_roteh', 'returned', 'cancelled']),
            default     => $query,
        };
    }

    public function collection(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(DeliveryCollection::class);
    }

    public function trackingHistories(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(PackageTrackingHistory::class)->orderBy('id');
    }

    protected static function booted(): void
    {
        // Timeline for Partner packages: one row each time the visible status changes.
        $record = function (Delivery $d) {
            if (! $d->partner_id) return;
            $status = $d->packageStatus();
            $last = PackageTrackingHistory::where('delivery_id', $d->id)->latest('id')->value('status');
            if ($last === $status) return;
            PackageTrackingHistory::create([
                'delivery_id' => $d->id,
                'status'      => $status,
                'raw_status'  => (string) $d->status,
                'note'        => match ($status) {
                    'cancelled' => $d->cancellation_reason,
                    'failed', 'at_roteh', 'returned' => $d->failure_reason,
                    default     => null,
                },
            ]);
        };
        // Partner packages: ROTEH always charges the delivery fee to the Partner
        // (taken from the COD, or the Partner pays) — the receiver pays only the
        // item price. Enforced here so every path (app, website, API) agrees.
        static::saving(function (Delivery $d) {
            if ($d->partner_id && $d->payment_by !== 'sender') {
                $d->payment_by = 'sender';
            }
        });

        static::created($record);
        static::updated(function (Delivery $d) use ($record) {
            if ($d->wasChanged('status')) $record($d);
        });
    }

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

    /** Staff driver who brought a Partner package from the shop to the office. */
    public function pickupDriver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'pickup_driver_id');
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

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CarRental extends Model
{
    protected $fillable = [
        'user_id',
        'entry_type',
        'guest_name',
        'guest_phone',
        'guest_token',
        'guest_fcm_token',
        'vehicle_id',
        'marketplace_product_id',
        'vehicle_type',
        'pickup_location',
        'pickup_lat',
        'pickup_lng',
        'start_date',
        'end_date',
        'total_days',
        'daily_rate_khr',
        'total_amount_khr',
        'payment_method',
        'status',
        'notes',
        'confirmed_at',
        'cancelled_at',
    ];

    protected $hidden = ['guest_token', 'guest_fcm_token'];

    /** Not persisted: 'buyer' | 'seller' | 'admin' — who triggered the current save, so they aren't notified about their own action. */
    public ?string $actedBy = null;

    protected $casts = [
        'start_date'       => 'date',
        'end_date'         => 'date',
        'total_days'       => 'integer',
        'daily_rate_khr'   => 'integer',
        'total_amount_khr' => 'integer',
        'pickup_lat'       => 'float',
        'pickup_lng'       => 'float',
        'confirmed_at'     => 'datetime',
        'cancelled_at'     => 'datetime',
    ];

    public const VEHICLE_TYPES = ['motorcycle', 'tuk_tuk', 'electric', 'sedan', 'suv', 'van', 'truck'];

    public const VEHICLE_TYPE_LABELS = [
        'motorcycle' => 'Motorcycle', 'tuk_tuk' => 'Tuk-tuk', 'electric' => 'Electric',
        'sedan' => 'Sedan', 'suv' => 'SUV', 'van' => 'Van', 'truck' => 'Truck',
    ];

    /** KHR per USD used for rental pricing. */
    public const KHR_PER_USD = 4000;

    /** Generic daily rate (USD) for a booking with no listed product. */
    public static function genericDailyRateUsd(?string $vehicleType): float
    {
        return match ($vehicleType) {
            'motorcycle' => 5.00,
            'tuk_tuk'    => 7.00,
            'electric'   => 9.00,
            'sedan'      => 10.00,
            'suv'        => 15.00,
            'van'        => 18.00,
            'truck'      => 25.00,
            default      => 10.00,
        };
    }

    /**
     * Vehicle type for a listed product. Listings link their vehicle through
     * marketplace_vehicle_type_id (e.g. "Passenger Three-Wheeler"), rarely a
     * vehicle_id, so map the marketplace type onto the rental vocabulary.
     */
    public static function vehicleTypeForProduct(?MarketplaceProduct $product): ?string
    {
        if (! $product) return null;

        $own = $product->vehicle?->type;
        if (in_array($own, self::VEHICLE_TYPES, true)) return $own;

        $type = $product->marketplaceVehicleType;
        $key  = strtolower(($type?->slug ?? '') . ' ' . ($type?->getAttributes()['name_en'] ?? ''));

        return match (true) {
            $key === ' '                                                        => null,
            str_contains($key, 'three-wheel') || str_contains($key, 'three wheel')
                || str_contains($key, 'tuk') || str_contains($key, 'remork')    => 'tuk_tuk',
            str_contains($key, 'motor') || str_contains($key, 'scooter')
                || str_contains($key, 'bike')                                   => 'motorcycle',
            str_contains($key, 'truck') || str_contains($key, 'lorry')          => 'truck',
            str_contains($key, 'van')                                           => 'van',
            str_contains($key, 'suv')                                           => 'suv',
            str_contains($key, 'electric') || str_contains($key, 'ev ')         => 'electric',
            str_contains($key, 'sedan') || str_contains($key, 'car')            => 'sedan',
            default                                                             => null,
        };
    }

    /** Daily rate (USD) for a new booking: the listing's own rent price, else the generic rate. */
    public static function dailyRateUsdFor(?MarketplaceProduct $product, ?string $vehicleType): float
    {
        $listed = (float) ($product?->rent_price_per_day ?? 0);

        return $listed > 0 ? $listed : self::genericDailyRateUsd($vehicleType);
    }

    /** Daily rate (USD) this rental was booked at. */
    public function dailyRateUsd(): float
    {
        return $this->daily_rate_khr
            ? round($this->daily_rate_khr / self::KHR_PER_USD, 2)
            : self::genericDailyRateUsd($this->vehicle_type);
    }

    /** What to show as the vehicle: the listing's marketplace type, else the rental type. */
    public function vehicleLabel(): string
    {
        return $this->marketplaceProduct?->marketplaceVehicleType?->name
            ?? self::VEHICLE_TYPE_LABELS[$this->vehicle_type]
            ?? ucfirst(str_replace('_', ' ', (string) $this->vehicle_type));
    }

    public function isGuest(): bool
    {
        return $this->entry_type === 'guest';
    }

    public function renterName(): string
    {
        return $this->isGuest() ? ($this->guest_name ?? 'Guest') : ($this->user?->name ?? '—');
    }

    public function renterPhone(): ?string
    {
        return $this->isGuest() ? $this->guest_phone : $this->user?->phone;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function marketplaceProduct(): BelongsTo
    {
        return $this->belongsTo(MarketplaceProduct::class, 'marketplace_product_id');
    }
}

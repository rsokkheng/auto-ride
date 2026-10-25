<?php

namespace App\Models;

use App\Traits\HasLocalizedName;
use Illuminate\Database\Eloquent\Model;

class PromoCode extends Model
{
    use HasLocalizedName;

    /** Columns with _en / _kh / _zh variants (see HasLocalizedName). */
    protected array $localized = ['description'];
    /** service_type value => admin label */
    public const SERVICE_TYPES = [
        'all'              => 'All services',
        'rides'            => 'Rides only',
        'deliveries'       => 'Deliveries only',
        'moving'           => 'Moving only',
        'marketplace'      => 'Products — buy & rent',
        'product_purchase' => 'Products — buying only',
        'product_rent'     => 'Products — renting only',
    ];

    /** Coupon service_type => order contexts it also covers. */
    private const COVERS = [
        'marketplace' => ['product_purchase', 'product_rent'],
        'rentals'     => ['product_rent'],
    ];

    /** Order contexts a guest (no account) can apply a coupon to. */
    public const GUEST_CONTEXTS = ['product_purchase', 'product_rent'];

    protected $fillable = [
        'code', 'description', 'type', 'value', 'min_order', 'max_discount',
        'usage_limit', 'used_count', 'per_user_limit', 'service_type',
        'active', 'starts_at', 'expires_at',
    ];

    protected $casts = [
        'active'     => 'boolean',
        'starts_at'  => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function usages()
    {
        return $this->hasMany(PromoCodeUsage::class);
    }

    /** Maps the names older app builds send to the canonical order context. */
    public static function normalizeContext(string $serviceType): string
    {
        return match ($serviceType) {
            'ride'                      => 'rides',
            'delivery'                  => 'deliveries',
            'marketplace', 'purchase'   => 'product_purchase',
            'rental', 'rentals', 'rent' => 'product_rent',
            default                     => $serviceType,
        };
    }

    /** Every service_type value the promo check endpoints accept. */
    public static function contextNames(): array
    {
        return ['rides', 'deliveries', 'moving', 'product_purchase', 'product_rent',
                'ride', 'delivery', 'marketplace', 'purchase', 'rental', 'rentals', 'rent'];
    }

    public static function normalizePhone(?string $phone): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone);
        if ($digits === '') return null;
        if (str_starts_with($digits, '855')) $digits = '0' . substr($digits, 3);
        return $digits;
    }

    public function appliesTo(string $serviceType): bool
    {
        $context = self::normalizeContext($serviceType);
        if ($this->service_type === 'all' || $this->service_type === $context) return true;

        return in_array($context, self::COVERS[$this->service_type] ?? [], true);
    }

    public function isValid(string $serviceType, int $orderAmount, ?int $userId, ?string $guestPhone = null): bool
    {
        if (! $this->active) return false;
        if ($this->starts_at && now()->lt($this->starts_at)) return false;
        if ($this->expires_at && now()->gt($this->expires_at)) return false;
        if ($this->usage_limit && $this->used_count >= $this->usage_limit) return false;
        if ($orderAmount < $this->min_order) return false;
        if (! $this->appliesTo($serviceType)) return false;

        $guestPhone = self::normalizePhone($guestPhone);
        if ($userId) {
            $usages = $this->usages()->where('user_id', $userId)->count();
        } elseif ($guestPhone) {
            $usages = $this->usages()->where('guest_phone', $guestPhone)->count();
        } else {
            $usages = 0;
        }
        if ($usages >= $this->per_user_limit) return false;

        return true;
    }

    public function calculateDiscount(int $orderAmount): int
    {
        if ($this->type === 'percent') {
            $discount = (int) round($orderAmount * $this->value / 100);
            if ($this->max_discount) {
                $discount = min($discount, $this->max_discount);
            }
            return $discount;
        }
        return min($this->value, $orderAmount);
    }

    /**
     * Discount for a USD-priced order. Coupon amounts are riel, so the order is
     * converted before checking min_order / fixed values.
     *
     * @return array{0: float, 1: int}|null  [discount USD, discount KHR], null when the code doesn't apply
     */
    public function discountForUsd(string $serviceType, float $totalUsd, ?int $userId, ?string $guestPhone = null): ?array
    {
        $rate     = max(1, (int) config('ride.khr_per_usd', 4100));
        $totalKhr = (int) round($totalUsd * $rate);

        if (! $this->isValid($serviceType, $totalKhr, $userId, $guestPhone)) return null;

        $discountKhr = $this->calculateDiscount($totalKhr);
        $discountUsd = min($totalUsd, round($discountKhr / $rate, 2));

        return [$discountUsd, $discountKhr];
    }

    public function recordUsage(Model $bookable, int $discountKhr, ?int $userId, ?string $guestPhone = null): void
    {
        PromoCodeUsage::create([
            'promo_code_id'   => $this->id,
            'user_id'         => $userId,
            'guest_phone'     => $userId ? null : self::normalizePhone($guestPhone),
            'bookable_type'   => $bookable::class,
            'bookable_id'     => $bookable->getKey(),
            'discount_amount' => $discountKhr,
        ]);
        static::where('id', $this->id)->increment('used_count');
    }
}

<?php

namespace App\Services;

use App\Http\Controllers\Api\DeliveryController;
use App\Models\Delivery;
use App\Models\PricingSetting;
use App\Models\User;
use App\Events\RealtimeUpdate;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/** Offers an open delivery to nearby available drivers (realtime + push). */
class DeliveryOfferService
{
    public function __construct(private FcmService $fcm) {}

    /** @return Collection<int, User> drivers who were told */
    public function offer(Delivery $delivery): Collection
    {
        $drivers = $this->driversToOffer($delivery);

        // Instant in-app offer for drivers with the app open (FCM covers a
        // backgrounded app). Remember who was told, so they can be told again
        // the moment it's taken or cancelled (RealtimeHooks).
        foreach ($drivers as $driver) {
            RealtimeUpdate::toDriver($driver->id, 'delivery.offered', ['delivery_id' => $delivery->id]);
        }
        Cache::put(DeliveryController::offeredCacheKey($delivery->id), $drivers->pluck('id')->all(), 7200);

        $this->fcm->sendToUsers(
            $drivers->whereNotNull('fcm_token')->all(),
            ...FcmService::message('delivery_requested', [
                'pickup'  => $delivery->pickup_address,
                'dropoff' => $delivery->dropoff_address ?? 'Destination TBD',
            ]),
            data: ['type' => 'delivery_requested', 'delivery_id' => (string) $delivery->id]
        );

        return $drivers;
    }

    /**
     * Available drivers near the pickup (Redis GEO, nearest first, capped).
     * Without pickup coordinates, falls back to every available driver.
     */
    public function driversToOffer(Delivery $delivery): Collection
    {
        $service = $delivery->service_type ?? 'delivery';

        if ($delivery->pickup_lat === null || $delivery->pickup_lng === null) {
            return User::where('role', 'driver')->where('available', true)->withoutActiveTrip()
                ->acceptingService($service)->withMinBalance()->whereNotNull('fcm_token')->get();
        }

        $radiusKm = (float) PricingSetting::get('delivery_match_radius_km', config('delivery.match_radius_km', 30));
        $nearby   = app(DriverGeoService::class)->nearby((float) $delivery->pickup_lat, (float) $delivery->pickup_lng, $radiusKm, 50);

        return $nearby === []
            ? collect()
            : User::whereIn('id', array_keys($nearby))->where('role', 'driver')->where('available', true)->withoutActiveTrip()
                ->acceptingService($service)->withMinBalance()->get();
    }
}

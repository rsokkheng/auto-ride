<?php

namespace App\Http\Middleware;

use App\Services\FareService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Refuses to price or create a booking whose pickup, dropoff or any stop lies
 * outside the service area (config ride.service_area — Cambodia). Catches a
 * device reporting a simulator's default location (Cupertino / Mountain View)
 * or a stale GPS fix before it becomes a 17,000 km "trip".
 *
 * Only checks coordinates that are present; required-ness stays with each
 * controller's own validation.
 */
class EnsureServiceArea
{
    /** [field prefix => [lat key, lng key]] */
    private const POINTS = [
        'pickup'  => ['pickup_lat', 'pickup_lng'],
        'dropoff' => ['dropoff_lat', 'dropoff_lng'],
    ];

    private const MESSAGES = [
        'en' => 'Sorry, this location is outside our service area (Cambodia). Please choose a pickup and destination inside Cambodia.',
        'km' => 'សូមអភ័យទោស ទីតាំងនេះនៅក្រៅតំបន់សេវាកម្មរបស់យើង (កម្ពុជា)។ សូមជ្រើសរើសទីតាំងទទួល និងគោលដៅនៅក្នុងប្រទេសកម្ពុជា។',
        'zh' => '抱歉，该位置不在我们的服务范围内（柬埔寨）。请选择柬埔寨境内的上车点和目的地。',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $outside = [];

        foreach (self::POINTS as $name => [$latKey, $lngKey]) {
            if ($this->isOutside($request->input($latKey), $request->input($lngKey))) {
                $outside[$latKey] = $name;
            }
        }

        foreach ((array) $request->input('stops', []) as $i => $stop) {
            if (! is_array($stop)) continue;
            $lat = $stop['lat'] ?? $stop['latitude'] ?? null;
            $lng = $stop['lng'] ?? $stop['longitude'] ?? null;
            if ($this->isOutside($lat, $lng)) {
                $outside["stops.$i.lat"] = 'stop ' . ($i + 1);
            }
        }

        if ($outside === []) {
            return $next($request);
        }

        Log::info('booking_outside_service_area', [
            'path'   => $request->path(),
            'points' => array_map(fn ($k) => [$request->input($k), $request->input(str_replace('_lat', '_lng', $k))], array_keys($outside)),
        ]);

        $message = self::MESSAGES[app()->getLocale()] ?? self::MESSAGES['en'];

        return response()->json([
            'message' => $message,
            'code'    => 'outside_service_area',
            'errors'  => array_map(fn () => [$message], $outside),
        ], 422);
    }

    private function isOutside(mixed $lat, mixed $lng): bool
    {
        if (! is_numeric($lat) || ! is_numeric($lng)) {
            return false; // missing/invalid is the controller's validation's job
        }

        return ! FareService::inServiceArea((float) $lat, (float) $lng);
    }
}

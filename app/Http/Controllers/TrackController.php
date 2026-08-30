<?php

namespace App\Http\Controllers;

use App\Models\Delivery;
use App\Models\Ride;

class TrackController extends Controller
{
    /**
     * GET /track/delivery_moving/{token}
     *
     * Public tracking page for a delivery or moving booking — no auth. Shows the
     * driver's live position while the job is active, and the dropoff location.
     */
    public function showDelivery(string $token)
    {
        // Any status — a completed job still renders, so the link never 404s on the recipient.
        $delivery = Delivery::with(['driver', 'vehicle'])
            ->where('share_token', $token)
            ->first();

        if (! $delivery) {
            return view('track-delivery', ['delivery' => null, 'driver' => null]);
        }

        $isLive = $delivery->isTrackable() && $delivery->driver_id !== null;

        $driver = null;
        if ($delivery->driver) {
            $driver = [
                'name'   => $delivery->driver->name,
                'rating' => $delivery->driver->rating ?? 5.0,
                'lat'    => $isLive ? ($delivery->driver->current_latitude ?: null) : null,
                'lng'    => $isLive ? ($delivery->driver->current_longitude ?: null) : null,
            ];
        }

        return view('track-delivery', [
            'delivery' => [
                'id'              => $delivery->id,
                'status'          => $delivery->status,
                'service_type'    => $delivery->service_type ?? 'delivery',
                'pickup_address'  => $delivery->pickup_address,
                'dropoff_address' => $delivery->dropoff_address,
                'dropoff_lat'     => $delivery->dropoff_lat,
                'dropoff_lng'     => $delivery->dropoff_lng,
                'recipient_name'  => $delivery->recipient_name,
                'is_cancelled'    => $delivery->status === 'cancelled',
                'is_finished'     => $delivery->isFinished(),
            ],
            'driver'  => $driver,
            'is_live' => $isLive,
        ]);
    }

    public function show(string $token)
    {
        // Find by token — any status, no share_active check so completed trips still show
        $ride = Ride::with(['driver', 'vehicle'])
            ->where('share_token', $token)
            ->first();

        if (! $ride) {
            return view('track', ['ride' => null, 'driver' => null]);
        }

        $isLive = in_array($ride->status, ['accepted', 'driver_arrived', 'in_progress']);

        $driver = null;
        if ($ride->driver) {
            $lat = $ride->driver->current_latitude  ?: null;
            $lng = $ride->driver->current_longitude ?: null;
            $driver = [
                'name'   => $ride->driver->name,
                'rating' => $ride->driver->rating ?? 5.0,
                'lat'    => $isLive ? $lat : null,
                'lng'    => $isLive ? $lng : null,
            ];
        }

        return view('track', [
            'ride' => [
                'id'              => $ride->id,
                'status'          => $ride->status,
                'pickup_address'  => $ride->pickup_address,
                'dropoff_address' => $ride->dropoff_address,
            ],
            'driver'  => $driver,
            'is_live' => $isLive,
        ]);
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\Ride;

class TrackController extends Controller
{
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

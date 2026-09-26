<?php

namespace App\Events;

use App\Models\Ride;
use App\Models\RideLocation;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

// Queued so a slow/unreachable Reverb server never fails the driver's location POST.
class RideLocationUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /** Own queue + worker so long jobs on `default` (e.g. promo pushes) never delay realtime. */
    public string $broadcastQueue = 'broadcasts';

    public Ride $ride;
    public RideLocation $location;

    public function __construct(Ride $ride, RideLocation $location)
    {
        $this->ride = $ride;
        $this->location = $location;
    }

    public function broadcastOn(): Channel
    {
        return new PrivateChannel('ride.' . $this->ride->id);
    }

    public function broadcastWith(): array
    {
        return [
            'ride_id' => $this->ride->id,
            'latitude' => $this->location->latitude,
            'longitude' => $this->location->longitude,
            'speed' => $this->location->speed,
            'heading' => $this->location->heading,
            'status' => $this->location->status,
            'reported_at' => $this->location->created_at->toISOString(),
        ];
    }
}

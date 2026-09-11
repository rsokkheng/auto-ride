<?php

namespace App\Kafka\Producers;

use Illuminate\Support\Str;
use Junges\Kafka\Facades\Kafka;
use Junges\Kafka\Message\Message;

class RideAcceptedProducer
{
    public function publish(array $ride): void
    {
        $message = new Message(
            body: [
                'event_id' => (string) Str::uuid(),
                'event' => 'ride.accepted',
                'occurred_at' => now()->toISOString(),

                'ride_id' => $ride['ride_id'],
                'customer_id' => $ride['customer_id'] ?? null,
                'driver_id' => $ride['driver_id'],

                'pickup' => $ride['pickup'],
                'destination' => $ride['destination'],

                'status' => $ride['status'] ?? 'accepted',
            ]
        );

        Kafka::publish()
            ->onTopic('ride-accepted')
            ->withMessage($message)
            ->send();
    }
}

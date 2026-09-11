<?php

namespace App\Kafka\Producers;

use Illuminate\Support\Str;
use Junges\Kafka\Facades\Kafka;
use Junges\Kafka\Message\Message;

class RideCreatedProducer
{
    public function publish(array $ride): void
    {
        $message = new Message(
            body: [
                'event_id' => (string) Str::uuid(),
                'event' => 'ride.created',
                'occurred_at' => now()->toISOString(),

                'ride_id' => $ride['ride_id'],
                'customer_id' => $ride['customer_id'] ?? null,
                'driver_id' => $ride['driver_id'] ?? null,

                'pickup' => $ride['pickup'],
                'destination' => $ride['destination'],

                'status' => $ride['status'] ?? 'created',
            ]
        );

        Kafka::publish()
            ->onTopic('ride-created')
            ->withMessage($message)
            ->send();
    }
}
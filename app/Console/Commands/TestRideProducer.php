<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Kafka\Producers\RideCreatedProducer;

class TestRideProducer extends Command
{
    protected $signature = 'kafka:test-ride';

    protected $description = 'Test ride created Kafka event';

    public function handle(RideCreatedProducer $producer)
    {
        $producer->publish([
            'ride_id' => 1001,
            'customer_id' => 25,
            'driver_id' => null,
            'pickup' => 'Phnom Penh',
            'destination' => 'Airport',
            'status' => 'created',
        ]);

        $this->info('ride-created event published successfully.');
    }
}
<?php

namespace App\Console\Commands;

use App\Jobs\ProcessRideCreated;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Redis;
use Junges\Kafka\Facades\Kafka;
use Junges\Kafka\Contracts\ConsumerMessage;

class ConsumeRideCreated extends Command
{
    protected $signature = 'kafka:consume-rides';

    protected $description = 'Consume ride-created events from Kafka and queue dispatch';

    /** How long a processed event_id is remembered in Redis, guarding against re-delivery. */
    private const DEDUPE_TTL_SECONDS = 86400;

    public function handle(): void
    {
        $consumer = Kafka::consumer(
            ['ride-created'],
            'ride-service'
        )
            ->withHandler(function (ConsumerMessage $message) {

                $body = $message->getBody();

                $eventId = $body['event_id'] ?? null;
                $rideId  = $body['ride_id'] ?? null;

                if (! $rideId) {
                    $this->warn('Ride Created event missing ride_id, skipping.');
                    return;
                }

                // Redis-backed idempotency: only the first delivery of a given event_id
                // queues dispatch — Kafka's at-least-once delivery can redeliver.
                if ($eventId) {
                    $isNew = Redis::set(
                        "ride-created:event:{$eventId}",
                        1,
                        'EX', self::DEDUPE_TTL_SECONDS,
                        'NX',
                    );

                    if (! $isNew) {
                        $this->info("Ride Created event {$eventId} already processed, skipping.");
                        return;
                    }
                }

                ProcessRideCreated::dispatch((int) $rideId);

                $this->info("Queued dispatch for ride #{$rideId}.");
            })
            ->build();

        $this->info('Listening for ride-created events...');

        $consumer->consume();
    }
}
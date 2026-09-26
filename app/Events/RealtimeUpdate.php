<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Lightweight "something changed" signal pushed to the mobile app over Reverb.
 *
 * The app treats these as a cue to refetch through the normal REST endpoint
 * (which stays the source of truth), so payloads carry only ids/status —
 * never anything the REST call wouldn't also return to that user.
 *
 * Queued (ShouldBroadcast, not ShouldBroadcastNow) so a slow or unreachable
 * Reverb server never adds latency to, or fails, the API request itself.
 * Dispatched after the surrounding DB transaction commits, so the app's
 * refetch can never observe the pre-change row.
 *
 * Channels (authorized in routes/channels.php):
 *   private-driver.{userId}        ride offers for a driver
 *   private-user.{userId}          wallet / payment / support updates
 *   private-ride.{rideId}          ride status + driver location
 *   private-delivery.{deliveryId}  delivery status
 *   private-conversation.{id}      chat messages
 */
class RealtimeUpdate implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable, InteractsWithSockets;

    /** Own queue + worker so long jobs on `default` (e.g. promo pushes) never delay realtime. */
    public string $broadcastQueue = 'broadcasts';

    public function __construct(
        public string $channel,
        public string $event,
        public array  $payload = [],
    ) {}

    public static function toUser(int $userId, string $event, array $payload = []): void
    {
        static::dispatch("user.{$userId}", $event, $payload);
    }

    public static function toDriver(int $driverId, string $event, array $payload = []): void
    {
        static::dispatch("driver.{$driverId}", $event, $payload);
    }

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel($this->channel);
    }

    public function broadcastAs(): string
    {
        return $this->event;
    }

    public function broadcastWith(): array
    {
        return $this->payload;
    }
}

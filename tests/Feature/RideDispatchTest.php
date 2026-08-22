<?php

namespace Tests\Feature;

use App\Jobs\AdvanceRideDispatch;
use App\Jobs\CancelUnclaimedRide;
use App\Models\Ride;
use App\Models\User;
use App\Services\RideDispatchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class RideDispatchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // The test env's QUEUE_CONNECTION is 'sync', which runs a dispatched job
        // immediately in-process (ignoring ->delay()). That would collapse the whole
        // ranked-dispatch cascade (offer -> "timeout" -> offer next -> ...) into a
        // single call, making it impossible to test "driver accepts before timeout"
        // or "still mid-cascade" states. Faking the queue lets each test control
        // exactly when a timeout/expiry job actually runs.
        Queue::fake();
    }

    private function makeDriver(array $overrides = []): User
    {
        return User::factory()->create(array_merge([
            'role'              => 'driver',
            'available'         => true,
            'current_latitude'  => 11.5564,
            'current_longitude' => 104.9282,
            'rating'            => 5.0,
            'api_token'         => 'test-token-' . uniqid(),
        ], $overrides));
    }

    private function makePassenger(array $overrides = []): User
    {
        return User::factory()->create(array_merge([
            'role'      => 'passenger',
            'api_token' => 'test-token-' . uniqid(),
        ], $overrides));
    }

    private function makeRide(User $passenger): Ride
    {
        return Ride::create([
            'passenger_id'   => $passenger->id,
            'pickup_address' => 'Test Pickup',
            'pickup_lat'      => 11.5564,
            'pickup_lng'      => 104.9282,
            'service_type'    => 'motorcycle',
            'status'          => Ride::STATUS_REQUESTED,
            'fare'            => 5000,
        ]);
    }

    public function test_ranked_driver_can_accept_ride(): void
    {
        $driver     = $this->makeDriver();
        $passenger  = $this->makePassenger();
        $ride       = $this->makeRide($passenger);

        app(RideDispatchService::class)->start($ride->fresh());
        $ride->refresh();

        $this->assertSame([$driver->id], $ride->dispatch_queue);
        $this->assertSame(0, $ride->dispatch_position);
        $this->assertNull($ride->self_serve_expires_at);
        $this->assertNotNull($ride->dispatch_offered_at);

        $response = $this->withHeader('Authorization', 'Bearer ' . $driver->api_token)
            ->postJson("/api/v1/rides/{$ride->id}/accept");

        $response->assertOk();
        $ride->refresh();
        $this->assertSame(Ride::STATUS_ACCEPTED, $ride->status);
        $this->assertSame($driver->id, $ride->driver_id);
    }

    public function test_ranked_driver_reject_advances_to_next_driver(): void
    {
        $driver1   = $this->makeDriver();
        $driver2   = $this->makeDriver();
        $passenger = $this->makePassenger();
        $ride      = $this->makeRide($passenger);

        app(RideDispatchService::class)->start($ride->fresh());
        $ride->refresh();
        $this->assertSame(0, $ride->dispatch_position);

        $this->withHeader('Authorization', 'Bearer ' . $driver1->api_token)
            ->postJson("/api/v1/rides/{$ride->id}/reject")
            ->assertOk();

        $ride->refresh();
        $this->assertSame(1, $ride->dispatch_position, 'rejecting driver1 should advance the queue to driver2');
        $this->assertNull($ride->self_serve_expires_at, 'queue not exhausted yet — driver2 is still ranked');
    }

    public function test_offer_timeout_advances_to_next_driver(): void
    {
        $driver1   = $this->makeDriver();
        $driver2   = $this->makeDriver();
        $passenger = $this->makePassenger();
        $ride      = $this->makeRide($passenger);

        app(RideDispatchService::class)->start($ride->fresh());
        $ride->refresh();
        $this->assertSame(0, $ride->dispatch_position);

        // Simulate the offer window expiring without driver1 responding.
        (new AdvanceRideDispatch($ride->id, 0))->handle(app(RideDispatchService::class));

        $ride->refresh();
        $this->assertSame(1, $ride->dispatch_position);
    }

    public function test_widens_to_next_radius_tier_when_narrow_tier_has_no_candidates(): void
    {
        // ~3km north of the pickup point — outside the first tier (2km) but inside
        // the second (4km), per config/ride.php's default 'radius_tiers_km' => '2,4,6,8'.
        $farDriver = $this->makeDriver(['current_latitude' => 11.5564 + 0.027]);
        $passenger = $this->makePassenger();
        $ride      = $this->makeRide($passenger);

        app(RideDispatchService::class)->start($ride->fresh());
        $ride->refresh();

        $this->assertSame(1, $ride->dispatch_tier, 'tier 0 (2km) had no candidates, so it should have widened to tier 1 (4km)');
        $this->assertSame([$farDriver->id], $ride->dispatch_queue);
        $this->assertSame(0, $ride->dispatch_position);
        $this->assertNull($ride->self_serve_expires_at, 'a candidate was found in tier 1 — must not fall through to self-serve');
    }

    public function test_rejected_driver_is_not_reoffered_after_widening_to_next_tier(): void
    {
        // Both within 4km (so both are candidates once the search widens), but only
        // $near is within the first 2km tier — it should be offered first, and once
        // rejected must never reappear when the search widens to include $near again.
        $near      = $this->makeDriver(); // 0km — inside every tier
        $far       = $this->makeDriver(['current_latitude' => 11.5564 + 0.027]); // ~3km — tier 1 only
        $passenger = $this->makePassenger();
        $ride      = $this->makeRide($passenger);

        app(RideDispatchService::class)->start($ride->fresh());
        $ride->refresh();
        $this->assertSame(0, $ride->dispatch_tier);
        $this->assertSame([$near->id], $ride->dispatch_queue, 'only $near is within the 2km first tier');

        $this->withHeader('Authorization', 'Bearer ' . $near->api_token)
            ->postJson("/api/v1/rides/{$ride->id}/reject")
            ->assertOk();

        $ride->refresh();
        $this->assertSame(1, $ride->dispatch_tier, 'tier 0 exhausted after rejecting its only candidate — widened to tier 1');
        $this->assertSame([$far->id], $ride->dispatch_queue, '$near must not be re-offered even though it is still within the wider 4km tier');
        $this->assertContains($near->id, $ride->tried_driver_ids);
    }

    public function test_non_ranked_driver_can_self_serve_once_queue_is_exhausted(): void
    {
        // No available driver exists yet at dispatch time — DriverMatchingService
        // returns an empty ranked list, so start() exhausts the queue immediately
        // and opens the self-serve window.
        $this->makeDriver(['available' => false]);

        $passenger = $this->makePassenger();
        $ride      = $this->makeRide($passenger);

        app(RideDispatchService::class)->start($ride->fresh());

        // Only now does a driver come online — after the ranked list was already
        // computed, so they were never part of it and must self-serve instead.
        $outsideDriver = $this->makeDriver();

        $ride->refresh();
        $this->assertNotNull($ride->self_serve_expires_at, 'ranked queue exhausted — self-serve window should be open');
        $this->assertSame(Ride::STATUS_REQUESTED, $ride->status);

        // The outside driver should now see it in the self-serve fallback list.
        $listResponse = $this->withHeader('Authorization', 'Bearer ' . $outsideDriver->api_token)
            ->getJson('/api/v1/rides/available');
        $listResponse->assertOk();
        $ids = collect($listResponse->json('data.rides.data'))->pluck('id')->all();
        $this->assertContains($ride->id, $ids);

        // And can accept it even though never part of the ranked queue.
        $this->withHeader('Authorization', 'Bearer ' . $outsideDriver->api_token)
            ->postJson("/api/v1/rides/{$ride->id}/accept")
            ->assertOk();

        $ride->refresh();
        $this->assertSame(Ride::STATUS_ACCEPTED, $ride->status);
        $this->assertSame($outsideDriver->id, $ride->driver_id);
    }

    public function test_ride_ranked_only_not_visible_in_self_serve_list_yet(): void
    {
        $driver1        = $this->makeDriver();
        $outsideDriver  = $this->makeDriver();
        $passenger      = $this->makePassenger();
        $ride           = $this->makeRide($passenger);

        app(RideDispatchService::class)->start($ride->fresh());
        $ride->refresh();
        $this->assertNull($ride->self_serve_expires_at, 'still in ranked-only phase');

        $listResponse = $this->withHeader('Authorization', 'Bearer ' . $outsideDriver->api_token)
            ->getJson('/api/v1/rides/available');

        $ids = collect($listResponse->json('data.rides.data'))->pluck('id')->all();
        $this->assertNotContains($ride->id, $ids, 'ride still being offered sequentially — must not be snipeable yet');
    }

    public function test_auto_cancels_when_nobody_accepts_within_self_serve_window(): void
    {
        $passenger = $this->makePassenger();
        $ride      = $this->makeRide($passenger);

        // No available drivers anywhere — ranked queue is empty, exhausts immediately,
        // opening the self-serve window.
        app(RideDispatchService::class)->start($ride->fresh());
        $ride->refresh();
        $this->assertNotNull($ride->self_serve_expires_at);

        $this->travel(61)->seconds();

        (new CancelUnclaimedRide($ride->id))->handle(app(RideDispatchService::class));

        $ride->refresh();
        $this->assertSame(Ride::STATUS_CANCELLED, $ride->status);
        $this->assertSame('no_driver_available', $ride->cancellation_reason);
        $this->assertSame(0, $ride->cancellation_fee);
    }

    public function test_race_condition_accept_wins_over_concurrent_auto_cancel(): void
    {
        // Kept out of the ranked pool (available=false) so start() exhausts the queue
        // immediately and opens the self-serve window — this driver then self-serves it.
        $driver    = $this->makeDriver(['available' => false]);
        $passenger = $this->makePassenger();
        $ride      = $this->makeRide($passenger);

        app(RideDispatchService::class)->start($ride->fresh());
        $ride->refresh();
        $this->assertNotNull($ride->self_serve_expires_at);

        $this->travel(61)->seconds();

        // Driver accepts a split second before the expiry job runs.
        $this->withHeader('Authorization', 'Bearer ' . $driver->api_token)
            ->postJson("/api/v1/rides/{$ride->id}/accept")
            ->assertOk();

        // The expiry job then fires — it must NOT clobber the now-accepted ride.
        (new CancelUnclaimedRide($ride->id))->handle(app(RideDispatchService::class));

        $ride->refresh();
        $this->assertSame(Ride::STATUS_ACCEPTED, $ride->status, 'a concurrent auto-cancel must never overwrite a ride a driver just accepted');
        $this->assertSame($driver->id, $ride->driver_id);
    }

    public function test_race_condition_expired_ride_cannot_be_accepted_after_cancellation(): void
    {
        $driver    = $this->makeDriver(['available' => false]);
        $passenger = $this->makePassenger();
        $ride      = $this->makeRide($passenger);

        app(RideDispatchService::class)->start($ride->fresh());
        $ride->refresh();
        $this->assertNotNull($ride->self_serve_expires_at);

        $this->travel(61)->seconds();

        // Expiry job fires first and cancels the ride.
        (new CancelUnclaimedRide($ride->id))->handle(app(RideDispatchService::class));
        $ride->refresh();
        $this->assertSame(Ride::STATUS_CANCELLED, $ride->status);

        // Driver's accept arrives a split second later — must be rejected, not silently override cancellation.
        $response = $this->withHeader('Authorization', 'Bearer ' . $driver->api_token)
            ->postJson("/api/v1/rides/{$ride->id}/accept");

        $response->assertStatus(422);
        $ride->refresh();
        $this->assertSame(Ride::STATUS_CANCELLED, $ride->status);
        $this->assertNull($ride->driver_id);
    }
}

<?php

namespace Tests\Feature;

use App\Models\Delivery;
use App\Models\Ride;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Redis;
use Tests\TestCase;

/** A driver already on a ride or delivery must not get — or take — another job. */
class ActiveTripGuardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        Redis::del('drivers:geo');
    }

    private function user(string $role, array $attrs = []): User
    {
        return User::factory()->create($attrs + [
            'role'             => $role,
            'available'        => true,
            'api_token'        => 'test-token-' . uniqid(),
            'token_expires_at' => now()->addHour(),
        ]);
    }

    private function ride(User $passenger, array $attrs = []): Ride
    {
        return Ride::create($attrs + [
            'passenger_id'          => $passenger->id,
            'pickup_address'        => 'Wat Phnom',
            'pickup_lat'            => 11.5564,
            'pickup_lng'            => 104.9282,
            'service_type'          => 'tuk_tuk',
            'status'                => Ride::STATUS_REQUESTED,
            'fare'                  => 8000,
            'self_serve_expires_at' => now()->addMinutes(5),
        ]);
    }

    private function delivery(User $sender, array $attrs = []): Delivery
    {
        return Delivery::create($attrs + [
            'sender_id'       => $sender->id,
            'sender_name'     => 'Alice',
            'pickup_address'  => 'Point A',
            'dropoff_address' => 'Point B',
            'pickup_lat'      => 11.5683,
            'pickup_lng'      => 104.9220,
            'dropoff_lat'     => 11.5476,
            'dropoff_lng'     => 104.9348,
            'package_size'    => 'small',
            'fee'             => 6000,
            'status'          => 'requested',
            'service_type'    => 'delivery',
        ]);
    }

    public function test_driver_on_a_delivery_is_not_shown_or_allowed_to_take_a_ride(): void
    {
        $driver    = $this->user('driver');
        $passenger = $this->user('passenger');
        $this->delivery($passenger, ['driver_id' => $driver->id, 'status' => 'in_progress']);
        $ride = $this->ride($passenger);

        $this->withToken($driver->api_token)->getJson('/api/v1/rides/available')
            ->assertOk()
            ->assertJsonCount(0, 'data.rides.data');

        $this->withToken($driver->api_token)->postJson("/api/v1/rides/{$ride->id}/accept")
            ->assertUnprocessable();
        $this->assertNull($ride->fresh()->driver_id);
    }

    public function test_driver_on_a_ride_is_not_shown_or_allowed_to_take_a_delivery(): void
    {
        $driver    = $this->user('driver');
        $passenger = $this->user('passenger');
        $this->ride($passenger, ['driver_id' => $driver->id, 'status' => Ride::STATUS_IN_PROGRESS]);
        $delivery = $this->delivery($passenger);

        $this->withToken($driver->api_token)->getJson('/api/v1/deliveries/available')
            ->assertOk()
            ->assertJsonCount(0, 'data.deliveries.data');

        $this->withToken($driver->api_token)->postJson("/api/v1/deliveries/{$delivery->id}/accept")
            ->assertUnprocessable();
        $this->assertNull($delivery->fresh()->driver_id);
    }

    public function test_free_driver_still_sees_and_takes_jobs(): void
    {
        $driver    = $this->user('driver');
        $passenger = $this->user('passenger');
        $delivery  = $this->delivery($passenger);

        $this->withToken($driver->api_token)->getJson('/api/v1/deliveries/available')
            ->assertOk()
            ->assertJsonCount(1, 'data.deliveries.data');

        $this->withToken($driver->api_token)->postJson("/api/v1/deliveries/{$delivery->id}/accept")
            ->assertOk();
    }

    public function test_declined_ride_does_not_come_back(): void
    {
        $driver = $this->user('driver');
        $ride   = $this->ride($this->user('passenger'));

        $this->withToken($driver->api_token)->getJson('/api/v1/rides/available')
            ->assertJsonCount(1, 'data.rides.data');

        $this->withToken($driver->api_token)->postJson("/api/v1/driver/rides/{$ride->id}/decline")
            ->assertOk();

        $this->withToken($driver->api_token)->getJson('/api/v1/rides/available')
            ->assertJsonCount(0, 'data.rides.data');
    }

    public function test_declining_a_ranked_offer_passes_it_to_the_next_driver(): void
    {
        $first  = $this->user('driver');
        $second = $this->user('driver');
        $ride   = $this->ride($this->user('passenger'), [
            'self_serve_expires_at' => null,
            'dispatch_queue'        => [$first->id, $second->id],
            'dispatch_position'     => 0,
        ]);

        $this->withToken($first->api_token)->postJson("/api/v1/driver/rides/{$ride->id}/decline")
            ->assertOk();

        $this->assertSame(1, (int) $ride->fresh()->dispatch_position);
        $this->withToken($first->api_token)->getJson('/api/v1/rides/available')
            ->assertJsonCount(0, 'data.rides.data');
    }

    public function test_dismissed_delivery_does_not_come_back(): void
    {
        $driver   = $this->user('driver');
        $delivery = $this->delivery($this->user('passenger'));

        $this->withToken($driver->api_token)->postJson('/api/v1/driver/missed-requests', [
            'request_type' => 'delivery', 'request_id' => $delivery->id, 'reason' => 'declined',
        ])->assertCreated();

        $this->withToken($driver->api_token)->getJson('/api/v1/deliveries/available')
            ->assertJsonCount(0, 'data.deliveries.data');
    }

    public function test_stale_unavailable_flag_does_not_block_accepting(): void
    {
        // available=false but no actual trip — used to fail with "already have an active ride".
        $driver = $this->user('driver', ['available' => false]);
        $ride   = $this->ride($this->user('passenger'));

        $this->withToken($driver->api_token)->postJson("/api/v1/rides/{$ride->id}/accept")
            ->assertOk();

        $this->assertSame($driver->id, $ride->fresh()->driver_id);
        $this->assertFalse((bool) $driver->fresh()->available);
    }

    public function test_dispatch_scope_skips_busy_drivers(): void
    {
        $free      = $this->user('driver');
        $onRide    = $this->user('driver');
        $onDeliver = $this->user('driver');
        $passenger = $this->user('passenger');
        $this->ride($passenger, ['driver_id' => $onRide->id, 'status' => Ride::STATUS_ACCEPTED]);
        $this->delivery($passenger, ['driver_id' => $onDeliver->id, 'status' => 'accepted']);
        // A finished trip doesn't count.
        $this->ride($passenger, ['driver_id' => $free->id, 'status' => Ride::STATUS_COMPLETED]);

        $ids = User::where('role', 'driver')->withoutActiveTrip()->pluck('id');

        $this->assertTrue($ids->contains($free->id));
        $this->assertFalse($ids->contains($onRide->id));
        $this->assertFalse($ids->contains($onDeliver->id));
    }
}

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

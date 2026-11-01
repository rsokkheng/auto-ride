<?php

namespace Tests\Feature;

use App\Models\Delivery;
use App\Models\Ride;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/** The driver app's Ride / Delivery / Rental toggles decide which jobs are offered. */
class DriverServiceModesTest extends TestCase
{
    use RefreshDatabase;

    private User $driver;
    private User $passenger;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        $make = fn (string $role) => User::factory()->create([
            'role' => $role, 'available' => true,
            'api_token' => 'test-token-' . uniqid(), 'token_expires_at' => now()->addHour(),
        ]);
        $this->driver    = $make('driver');
        $this->passenger = $make('passenger');
    }

    private function modes(array $modes)
    {
        return $this->withToken($this->driver->api_token)->postJson('/api/v1/driver/modes', $modes);
    }

    public function test_modes_are_saved_and_at_least_one_must_stay_on(): void
    {
        $this->modes(['ride' => true, 'delivery' => false, 'rental' => false])
            ->assertOk()
            ->assertJsonPath('data.delivery', false);
        $this->assertFalse($this->driver->fresh()->accepts_deliveries);
        $this->assertTrue($this->driver->fresh()->accepts_rides);

        $this->modes(['ride' => false, 'delivery' => false, 'rental' => false])->assertUnprocessable();
    }

    public function test_ride_only_driver_sees_rides_but_no_deliveries(): void
    {
        Ride::create([
            'passenger_id' => $this->passenger->id, 'pickup_address' => 'Wat Phnom',
            'pickup_lat' => 11.5564, 'pickup_lng' => 104.9282, 'service_type' => 'tuk_tuk',
            'status' => Ride::STATUS_REQUESTED, 'fare' => 8000, 'self_serve_expires_at' => now()->addMinutes(5),
        ]);
        Delivery::create([
            'sender_id' => $this->passenger->id, 'sender_name' => 'Alice', 'pickup_address' => 'A',
            'dropoff_address' => 'B', 'package_size' => 'small', 'fee' => 6000,
            'status' => 'requested', 'service_type' => 'delivery',
        ]);
        $this->modes(['ride' => true, 'delivery' => false, 'rental' => false])->assertOk();

        $this->withToken($this->driver->api_token)->getJson('/api/v1/rides/available')
            ->assertOk()->assertJsonCount(1, 'data.rides.data');
        $this->withToken($this->driver->api_token)->getJson('/api/v1/deliveries/available')
            ->assertOk()->assertJsonCount(0, 'data.deliveries.data');
    }

    public function test_drivers_default_to_every_job_type(): void
    {
        // Older app versions never send modes — keep offering everything.
        $fresh = $this->driver->fresh();
        $this->assertTrue($fresh->accepts_rides && $fresh->accepts_deliveries && $fresh->accepts_rentals);
    }
}

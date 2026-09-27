<?php

namespace Tests\Feature;

use App\Models\Ride;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** "Book without destination" rides: the dropoff is only known at completion. */
class MeteredRideCompletionTest extends TestCase
{
    use RefreshDatabase;

    private User $driver;
    private Ride $ride;

    protected function setUp(): void
    {
        parent::setUp();
        Http::fake(); // no Google / Firestore / FCM calls from the test

        $this->driver = User::factory()->create([
            'role'              => 'driver',
            'api_token'         => 'test-token-' . uniqid(),
            'token_expires_at'  => now()->addHour(),
            'current_latitude'  => 11.5680,
            'current_longitude' => 104.9195,
        ]);
        $passenger = User::factory()->create(['role' => 'passenger']);

        $this->ride = Ride::create([
            'passenger_id'   => $passenger->id,
            'driver_id'      => $this->driver->id,
            'pickup_address' => 'Phnom Penh',
            'pickup_lat'     => 11.5680,
            'pickup_lng'     => 104.9195,
            'service_type'   => 'motorcycle',
            'status'         => Ride::STATUS_IN_PROGRESS,
            'fare'           => 5000,
        ]);
    }

    private function complete(array $body)
    {
        return $this->withHeader('Authorization', 'Bearer ' . $this->driver->api_token)
            ->postJson("/api/v1/rides/{$this->ride->id}/complete", $body);
    }

    public function test_dropoff_outside_service_area_is_ignored(): void
    {
        // iOS simulator default location (Cupertino) — seen in production.
        $this->complete(['dropoff_lat' => 37.3301863, 'dropoff_lng' => -122.0326069, 'final_fare' => 4900])
            ->assertOk();

        $this->ride->refresh();
        $this->assertSame(Ride::STATUS_COMPLETED, $this->ride->status);
        $this->assertNull($this->ride->dropoff_lat);
        $this->assertNull($this->ride->dropoff_lng);
        $this->assertSame(4900, (int) $this->ride->fare);
    }

    public function test_valid_dropoff_is_stored_and_priced(): void
    {
        $this->complete(['dropoff_lat' => 11.5462, 'dropoff_lng' => 104.8440])->assertOk();

        $this->ride->refresh();
        $this->assertEqualsWithDelta(11.5462, (float) $this->ride->dropoff_lat, 0.0001);
        $this->assertGreaterThan(0, (float) $this->ride->distance_km);
        $this->assertLessThan(50, (float) $this->ride->distance_km);
    }
}

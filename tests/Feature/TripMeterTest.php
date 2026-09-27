<?php

namespace Tests\Feature;

use App\Models\Ride;
use App\Models\RideLocation;
use App\Models\User;
use App\Services\TripMeterService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TripMeterTest extends TestCase
{
    use RefreshDatabase;

    // Phnom Penh; 0.001° lat ≈ 111 m.
    private const LAT = 11.5680;
    private const LNG = 104.9195;

    private User $driver;
    private Ride $ride;

    protected function setUp(): void
    {
        parent::setUp();
        Http::fake();
        config(['services.google_maps.key' => null]); // straight-line floor, deterministic

        $this->driver = User::factory()->create([
            'role'             => 'driver',
            'api_token'        => 'test-token-' . uniqid(),
            'token_expires_at' => now()->addHour(),
        ]);
        $this->ride = Ride::create([
            'passenger_id'   => User::factory()->create(['role' => 'passenger'])->id,
            'driver_id'      => $this->driver->id,
            'pickup_address' => 'Phnom Penh',
            'pickup_lat'     => self::LAT,
            'pickup_lng'     => self::LNG,
            'service_type'   => 'motorcycle',
            'status'         => Ride::STATUS_IN_PROGRESS,
            'started_at'     => now()->subMinutes(20),
            'fare'           => 5000,
        ]);
    }

    /** Post a GPS fix $secondsAfterStart into the trip. */
    private function at(int $secondsAfterStart, float $lat, float $lng): void
    {
        $loc = new RideLocation(['ride_id' => $this->ride->id, 'latitude' => $lat, 'longitude' => $lng]);
        $loc->created_at = $this->ride->started_at->copy()->addSeconds($secondsAfterStart);
        $loc->updated_at = $loc->created_at;
        $loc->save();
    }

    private function measure(?float $endLat = null, ?float $endLng = null): array
    {
        return app(TripMeterService::class)->measure($this->ride->fresh(), $endLat, $endLng);
    }

    public function test_loop_trip_is_paid_for_the_distance_actually_driven(): void
    {
        // 2 km north and back to (almost) the pickup, a fix every 10 s.
        $t = 0;
        for ($i = 1; $i <= 18; $i++) $this->at($t += 10, self::LAT + $i * 0.001, self::LNG);
        for ($i = 17; $i >= 0; $i--) $this->at($t += 10, self::LAT + $i * 0.001, self::LNG);

        $m = $this->measure(self::LAT, self::LNG);

        // Straight line pickup → end is ~0 km; the old meter charged the minimum.
        $this->assertEqualsWithDelta(4.0, $m['distance_km'], 0.1);
        $this->assertSame('gps_track', $m['source']);
    }

    public function test_parked_car_jitter_does_not_accumulate(): void
    {
        // 10 minutes stationary with ±~5 m GPS noise.
        for ($i = 1; $i <= 60; $i++) {
            $this->at($i * 10, self::LAT + (($i % 3) - 1) * 0.00004, self::LNG + (($i % 2) ? 0.00004 : -0.00004));
        }

        $this->assertLessThan(0.05, $this->measure()['distance_km']);
    }

    public function test_gps_spike_and_simulator_fix_are_ignored(): void
    {
        $this->at(10, self::LAT + 0.001, self::LNG);
        $this->at(20, self::LAT + 0.45, self::LNG);            // 50 km jump in 10 s
        $this->at(30, 37.3301863, -122.0326069);               // iOS simulator (Cupertino)
        $this->at(40, self::LAT + 0.002, self::LNG);

        $this->assertEqualsWithDelta(0.22, $this->measure()['distance_km'], 0.05);
    }

    public function test_track_gap_falls_back_to_the_shortest_possible_distance(): void
    {
        // App backgrounded: no fixes at all, trip ends 5 km away.
        $m = $this->measure(self::LAT + 0.045, self::LNG);

        $this->assertEqualsWithDelta(5.0, $m['distance_km'], 0.1);
    }

    public function test_google_road_distance_is_the_floor_when_track_is_sparse(): void
    {
        config(['services.google_maps.key' => 'test-key']);
        // setUp()'s catch-all fake would match first — start from a clean client.
        Http::swap(new \Illuminate\Http\Client\Factory);
        Http::fake(['maps.googleapis.com/*' => Http::response([
            'status' => 'OK',
            'routes' => [['legs' => [[
                'distance' => ['value' => 7200, 'text' => '7.2 km'],
                'duration' => ['value' => 900, 'text' => '15 mins'],
            ]]]],
        ])]);

        // Straight line is 5 km, but the only road there is 7.2 km.
        $m = $this->measure(self::LAT + 0.045, self::LNG);

        $this->assertEqualsWithDelta(7.2, $m['distance_km'], 0.01);
        $this->assertSame('route_floor', $m['source']);
    }

    public function test_meter_endpoint_and_completion_use_the_track(): void
    {
        $t = 0;
        for ($i = 1; $i <= 18; $i++) $this->at($t += 10, self::LAT + $i * 0.001, self::LNG);
        for ($i = 17; $i >= 0; $i--) $this->at($t += 10, self::LAT + $i * 0.001, self::LNG);

        $meter = $this->withHeader('Authorization', 'Bearer ' . $this->driver->api_token)
            ->getJson("/api/v1/rides/{$this->ride->id}/meter?lat=" . self::LAT . '&lng=' . self::LNG)
            ->assertOk();
        $this->assertEqualsWithDelta(4.0, $meter->json('data.distance_km'), 0.1);
        $this->assertGreaterThan(0, $meter->json('data.fare'));

        $this->withHeader('Authorization', 'Bearer ' . $this->driver->api_token)
            ->postJson("/api/v1/rides/{$this->ride->id}/complete", ['dropoff_lat' => self::LAT, 'dropoff_lng' => self::LNG])
            ->assertOk();

        $ride = $this->ride->fresh();
        $this->assertEqualsWithDelta(4.0, (float) $ride->distance_km, 0.1);
        $this->assertSame((int) $meter->json('data.fare'), (int) $ride->fare);
    }

    public function test_meter_is_only_for_the_assigned_driver_on_a_metered_trip(): void
    {
        $other = User::factory()->create(['role' => 'driver', 'api_token' => 'other-' . uniqid(), 'token_expires_at' => now()->addHour()]);
        $this->withHeader('Authorization', 'Bearer ' . $other->api_token)
            ->getJson("/api/v1/rides/{$this->ride->id}/meter")->assertStatus(401);

        $this->ride->update(['dropoff_address' => 'Known destination']);
        $this->withHeader('Authorization', 'Bearer ' . $this->driver->api_token)
            ->getJson("/api/v1/rides/{$this->ride->id}/meter")->assertStatus(422);
    }
}

<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Redis;
use Tests\TestCase;

class NearbyDriversTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Redis::del('drivers:geo');
        // A configured key would normally make findDrivers() call Google.
        config(['services.google_maps.key' => 'test-key']);
        Http::fake();
    }

    private function driverAt(float $lat, float $lng): User
    {
        return User::factory()->create([
            'role'              => 'driver',
            'available'         => true,
            'current_latitude'  => $lat,
            'current_longitude' => $lng,
            'phone'             => '+85512345678',
            'rating'            => 4.8,
        ]);
    }

    private function nearby(User $as, float $lat = 11.5564, float $lng = 104.9282)
    {
        return $this->withHeader('Authorization', 'Bearer ' . $as->api_token)
            ->getJson("/api/v1/drivers/nearby?lat={$lat}&lng={$lng}&radius=5");
    }

    private function passenger(): User
    {
        return User::factory()->create([
            'role'             => 'passenger',
            'api_token'        => 'test-token-' . uniqid(),
            'token_expires_at' => now()->addHour(),
        ]);
    }

    public function test_map_lookup_never_calls_google_and_hides_driver_pii(): void
    {
        $driver = $this->driverAt(11.5570, 104.9290);

        $response = $this->nearby($this->passenger())->assertOk();

        $this->assertSame([$driver->id], collect($response->json('data.drivers'))->pluck('id')->all());
        $first = $response->json('data.drivers.0');
        $this->assertArrayNotHasKey('phone', $first);
        $this->assertArrayNotHasKey('name', $first);
        $this->assertArrayNotHasKey('license_plate', $first['vehicle'] ?? []);
        $this->assertNotNull($first['lat']);
        Http::assertNothingSent();
    }

    public function test_nearby_passengers_share_one_cached_answer(): void
    {
        $this->driverAt(11.5570, 104.9290);
        $this->nearby($this->passenger())->assertOk()->assertJsonPath('data.total', 1);

        // A new driver appears — within the 10 s window, a passenger in the same
        // ~110 m cell still gets the cached answer.
        $this->driverAt(11.5566, 104.9284);
        $this->nearby($this->passenger(), 11.55641, 104.92821)->assertJsonPath('data.total', 1);

        $this->travel(11)->seconds();
        $this->nearby($this->passenger())->assertJsonPath('data.total', 2);
    }
}

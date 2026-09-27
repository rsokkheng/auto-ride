<?php

namespace Tests\Feature;

use App\Models\Delivery;
use App\Models\Ride;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ServiceAreaTest extends TestCase
{
    use RefreshDatabase;

    private const PP        = ['lat' => 11.5564, 'lng' => 104.9282];     // Phnom Penh
    private const PP2       = ['lat' => 11.5462, 'lng' => 104.8440];
    private const CUPERTINO = ['lat' => 37.3301863, 'lng' => -122.0326069]; // iOS simulator default

    private User $passenger;

    protected function setUp(): void
    {
        parent::setUp();
        Http::fake();
        Queue::fake();
        $this->passenger = User::factory()->create([
            'role'             => 'passenger',
            'api_token'        => 'test-token-' . uniqid(),
            'token_expires_at' => now()->addHour(),
        ]);
    }

    private function book(string $uri, array $body, string $locale = 'en')
    {
        return $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->passenger->api_token,
            'X-Locale'      => $locale,
        ])->postJson("/api/v1/$uri", $body);
    }

    private function trip(array $pickup, array $dropoff): array
    {
        return [
            'pickup_lat'  => $pickup['lat'],  'pickup_lng'  => $pickup['lng'],
            'dropoff_lat' => $dropoff['lat'], 'dropoff_lng' => $dropoff['lng'],
        ];
    }

    public function test_ride_estimate_and_booking_reject_a_pickup_outside_cambodia(): void
    {
        $this->book('rides/estimate', $this->trip(self::CUPERTINO, self::PP))
            ->assertStatus(422)
            ->assertJsonPath('code', 'outside_service_area')
            ->assertJsonValidationErrors(['pickup_lat']);

        $this->book('rides', $this->trip(self::CUPERTINO, self::PP) + ['pickup_address' => 'x', 'service_type' => 'motorcycle'])
            ->assertStatus(422);

        $this->assertSame(0, Ride::count());
    }

    public function test_dropoff_and_stops_are_checked_too(): void
    {
        $this->book('rides/estimate', $this->trip(self::PP, self::CUPERTINO))
            ->assertStatus(422)->assertJsonValidationErrors(['dropoff_lat']);

        $this->book('rides/estimate', $this->trip(self::PP, self::PP2) + ['stops' => [self::PP2, self::CUPERTINO]])
            ->assertStatus(422)->assertJsonValidationErrors(['stops.1.lat']);
    }

    public function test_deliveries_and_movings_are_protected(): void
    {
        foreach (['deliveries/estimate', 'deliveries', 'movings/estimate', 'movings'] as $uri) {
            $this->book($uri, $this->trip(self::CUPERTINO, self::PP))
                ->assertStatus(422)
                ->assertJsonPath('code', 'outside_service_area');
        }
        $this->assertSame(0, Delivery::count());
    }

    public function test_message_follows_the_app_language(): void
    {
        $this->book('rides/estimate', $this->trip(self::CUPERTINO, self::PP), 'km')
            ->assertStatus(422)
            ->assertJsonPath('message', fn ($m) => str_contains($m, 'កម្ពុជា'));
    }

    public function test_trips_inside_cambodia_are_unaffected(): void
    {
        $this->book('rides/estimate', $this->trip(self::PP, self::PP2))
            ->assertOk()
            ->assertJsonMissingPath('code');
    }
}

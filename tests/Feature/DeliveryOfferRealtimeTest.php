<?php

namespace Tests\Feature;

use App\Events\RealtimeUpdate;
use App\Models\Delivery;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Redis;
use Tests\TestCase;

class DeliveryOfferRealtimeTest extends TestCase
{
    use RefreshDatabase;

    private User $sender;
    private User $near1;
    private User $near2;
    private User $far;

    protected function setUp(): void
    {
        parent::setUp();
        Http::fake();
        Redis::del('drivers:geo');

        $driver = fn (float $lat, float $lng) => User::factory()->create([
            'role' => 'driver', 'available' => true,
            'current_latitude' => $lat, 'current_longitude' => $lng,
            'api_token' => 'test-token-' . uniqid(), 'token_expires_at' => now()->addHour(),
        ]);
        $this->near1 = $driver(11.5570, 104.9290);   // Phnom Penh
        $this->near2 = $driver(11.5600, 104.9200);
        $this->far   = $driver(13.3633, 103.8564);   // Siem Reap, ~230 km

        $this->sender = User::factory()->create([
            'role' => 'passenger', 'api_token' => 'test-token-' . uniqid(), 'token_expires_at' => now()->addHour(),
        ]);

        Event::fake([RealtimeUpdate::class]);
    }

    private function book(): Delivery
    {
        $id = $this->withHeader('Authorization', 'Bearer ' . $this->sender->api_token)
            ->postJson('/api/v1/deliveries', [
                'sender_name' => 'Dara', 'recipient_name' => 'Sokha', 'recipient_phone' => '012345678',
                'pickup_address' => 'Phnom Penh', 'pickup_lat' => 11.5564, 'pickup_lng' => 104.9282,
                'dropoff_address' => 'Toul Kork', 'dropoff_lat' => 11.5760, 'dropoff_lng' => 104.8990,
                'package_size' => 'small',
            ])->assertCreated()->json('data.delivery.id');

        return Delivery::findOrFail($id);
    }

    private function sentTo(User $driver, string $event): bool
    {
        return Event::dispatched(RealtimeUpdate::class, fn ($e) =>
            $e->channel === "driver.{$driver->id}" && $e->event === $event)->isNotEmpty();
    }

    public function test_new_delivery_is_pushed_only_to_nearby_drivers(): void
    {
        $this->book();

        $this->assertTrue($this->sentTo($this->near1, 'delivery.offered'));
        $this->assertTrue($this->sentTo($this->near2, 'delivery.offered'));
        $this->assertFalse($this->sentTo($this->far, 'delivery.offered'), 'a driver 230 km away must not be offered it');
    }

    public function test_accepted_delivery_is_withdrawn_from_the_other_drivers(): void
    {
        $delivery = $this->book();

        $this->withHeader('Authorization', 'Bearer ' . $this->near1->api_token)
            ->postJson("/api/v1/deliveries/{$delivery->id}/accept")
            ->assertOk();

        $this->assertTrue($this->sentTo($this->near2, 'delivery.offer_withdrawn'));
        $this->assertFalse($this->sentTo($this->near1, 'delivery.offer_withdrawn'), 'the accepting driver keeps it');
    }
}

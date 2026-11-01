<?php

namespace Tests\Feature;

use App\Models\Delivery;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/** A cancelled delivery records who cancelled it and why, for the customer's screen. */
class DeliveryCancellationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::fake(); // Firestore sync + FCM push on cancel
        Queue::fake();
    }

    private function user(string $role): User
    {
        return User::factory()->create([
            'role' => $role, 'available' => true,
            'api_token' => 'test-token-' . uniqid(), 'token_expires_at' => now()->addHour(),
        ]);
    }

    private function delivery(User $sender, ?User $driver = null): Delivery
    {
        return Delivery::create([
            'sender_id' => $sender->id, 'driver_id' => $driver?->id, 'sender_name' => 'Alice',
            'pickup_address' => 'A', 'dropoff_address' => 'B', 'package_size' => 'small',
            'fee' => 6000, 'status' => $driver ? 'accepted' : 'requested', 'service_type' => 'moving',
        ]);
    }

    public function test_driver_cancellation_is_recorded_with_reason(): void
    {
        $sender = $this->user('passenger');
        $driver = $this->user('driver');
        $d = $this->delivery($sender, $driver);

        $this->withToken($driver->api_token)
            ->postJson("/api/v1/deliveries/{$d->id}/cancel", ['reason' => 'Truck broke down'])
            ->assertOk();

        $this->withToken($sender->api_token)->getJson("/api/v1/deliveries/{$d->id}")
            ->assertOk()
            ->assertJsonPath('data.delivery.status', 'cancelled')
            ->assertJsonPath('data.delivery.cancelled_by', 'driver')
            ->assertJsonPath('data.delivery.cancellation_reason', 'Truck broke down');
        $this->assertNotNull($d->fresh()->cancelled_at);
    }

    public function test_sender_cancellation_is_recorded(): void
    {
        $sender = $this->user('passenger');
        $d = $this->delivery($sender);

        $this->withToken($sender->api_token)->postJson("/api/v1/deliveries/{$d->id}/cancel")->assertOk();

        $this->assertSame('sender', $d->fresh()->cancelled_by);
    }
}

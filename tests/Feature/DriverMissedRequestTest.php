<?php

namespace Tests\Feature;

use App\Models\DriverMissedRequest;
use App\Models\Ride;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class DriverMissedRequestTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
    }

    private function user(string $role): User
    {
        return User::factory()->create([
            'role'             => $role,
            'api_token'        => 'test-token-' . uniqid(),
            'token_expires_at' => now()->addHour(),
        ]);
    }

    private function ride(User $passenger, array $attrs = []): Ride
    {
        return Ride::create($attrs + [
            'passenger_id'    => $passenger->id,
            'pickup_address'  => 'Wat Phnom',
            'dropoff_address' => 'Aeon Mall',
            'pickup_lat'      => 11.5564,
            'pickup_lng'      => 104.9282,
            'service_type'    => 'tuk_tuk',
            'status'          => Ride::STATUS_REQUESTED,
            'fare'            => 8000,
        ]);
    }

    public function test_driver_records_a_missed_ride_and_sees_it_in_history(): void
    {
        $driver    = $this->user('driver');
        $passenger = $this->user('passenger');
        $ride      = $this->ride($passenger);

        $this->withToken($driver->api_token)
            ->postJson('/api/v1/driver/missed-requests', [
                'request_type' => 'ride', 'request_id' => $ride->id, 'reason' => 'missed',
            ])->assertCreated();

        $this->withToken($driver->api_token)
            ->getJson('/api/v1/driver/missed-requests')
            ->assertOk()
            ->assertJsonPath('data.missed_requests.0.request_type', 'ride')
            ->assertJsonPath('data.missed_requests.0.reason', 'missed')
            ->assertJsonPath('data.missed_requests.0.outcome', 'open')
            ->assertJsonPath('data.missed_requests.0.summary.pickup_address', 'Wat Phnom')
            ->assertJsonPath('data.missed_requests.0.summary.fare_khr', 8000)
            ->assertJsonPath('data.missed_requests.0.summary.customer_name', $passenger->name);
    }

    public function test_repeat_offer_updates_instead_of_duplicating(): void
    {
        $driver = $this->user('driver');
        $ride   = $this->ride($this->user('passenger'));

        foreach (['missed', 'declined'] as $reason) {
            $this->withToken($driver->api_token)->postJson('/api/v1/driver/missed-requests', [
                'request_type' => 'ride', 'request_id' => $ride->id, 'reason' => $reason,
            ])->assertCreated();
        }

        $this->assertSame(1, DriverMissedRequest::count());
        $this->assertSame('declined', DriverMissedRequest::sole()->reason);
    }

    public function test_outcome_shows_when_another_driver_took_it_or_it_was_cancelled(): void
    {
        $driver    = $this->user('driver');
        $other     = $this->user('driver');
        $passenger = $this->user('passenger');
        $taken     = $this->ride($passenger);
        $cancelled = $this->ride($passenger);

        foreach ([$taken, $cancelled] as $ride) {
            $this->withToken($driver->api_token)->postJson('/api/v1/driver/missed-requests', [
                'request_type' => 'ride', 'request_id' => $ride->id, 'reason' => 'missed',
            ])->assertCreated();
        }
        $taken->update(['status' => Ride::STATUS_ACCEPTED, 'driver_id' => $other->id]);
        $cancelled->update(['status' => Ride::STATUS_CANCELLED]);

        $rows = collect($this->withToken($driver->api_token)
            ->getJson('/api/v1/driver/missed-requests')->json('data.missed_requests'))
            ->keyBy('request_id');

        $this->assertSame('taken', $rows[$taken->id]['outcome']);
        $this->assertSame('cancelled', $rows[$cancelled->id]['outcome']);
    }

    public function test_drivers_only_see_their_own_history(): void
    {
        $mine  = $this->user('driver');
        $other = $this->user('driver');
        $ride  = $this->ride($this->user('passenger'));

        $this->withToken($other->api_token)->postJson('/api/v1/driver/missed-requests', [
            'request_type' => 'ride', 'request_id' => $ride->id, 'reason' => 'declined',
        ])->assertCreated();

        $this->withToken($mine->api_token)
            ->getJson('/api/v1/driver/missed-requests')
            ->assertOk()
            ->assertJsonCount(0, 'data.missed_requests');
    }

    public function test_only_drivers_can_use_it_and_input_is_validated(): void
    {
        $passenger = $this->user('passenger');
        $driver    = $this->user('driver');

        $this->withToken($passenger->api_token)->getJson('/api/v1/driver/missed-requests')
            ->assertUnauthorized();

        $this->withToken($driver->api_token)->postJson('/api/v1/driver/missed-requests', [
            'request_type' => 'boat', 'request_id' => 1, 'reason' => 'missed',
        ])->assertUnprocessable();

        $this->withToken($driver->api_token)->postJson('/api/v1/driver/missed-requests', [
            'request_type' => 'ride', 'request_id' => 999999, 'reason' => 'missed',
        ])->assertNotFound();
    }
}

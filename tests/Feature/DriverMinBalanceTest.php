<?php

namespace Tests\Feature;

use App\Models\PushNotification;
use App\Models\Ride;
use App\Models\User;
use App\Services\RideDispatchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Redis;
use Tests\TestCase;

/** A driver needs the minimum wallet balance (default 50,000 ៛) to get or take jobs. */
class DriverMinBalanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::fake();
        Queue::fake();
        Redis::del('drivers:geo');
    }

    private function driver(int $balance): User
    {
        return User::factory()->create([
            'role' => 'driver', 'available' => true, 'wallet_balance' => $balance,
            'current_latitude' => 11.5564, 'current_longitude' => 104.9282, 'rating' => 5,
            'api_token' => 't-' . uniqid(), 'token_expires_at' => now()->addHour(),
        ]);
    }

    private function ride(): Ride
    {
        $p = User::factory()->create(['role' => 'passenger']);
        return Ride::create([
            'passenger_id' => $p->id, 'pickup_address' => 'A', 'pickup_lat' => 11.5564, 'pickup_lng' => 104.9282,
            'service_type' => 'tuk_tuk', 'status' => Ride::STATUS_REQUESTED, 'fare' => 5000,
            'self_serve_expires_at' => now()->addMinutes(5),
        ]);
    }

    public function test_withdrawing_below_the_minimum_takes_the_driver_offline(): void
    {
        $driver = $this->driver(60000);
        $driver->update(['fcm_token' => 'fcm-test-token']);

        $this->withToken($driver->api_token)->postJson('/api/v1/driver/withdraw', [
            'amount_khr' => 60000, 'payment_method' => 'aba',
            'account_number' => '000111222', 'account_name' => 'Driver',
        ])->assertOk()->assertJsonPath('data.went_offline', true);

        $this->assertFalse((bool) $driver->fresh()->available);

        // …and is told why, by push, in their language.
        $push = PushNotification::where('user_id', $driver->id)->where('type', 'driver_low_balance')->first();
        $this->assertNotNull($push, 'low-balance push sent');
        $this->assertStringContainsString('50,000', $push->body_kh);
    }

    public function test_low_balance_driver_is_not_offered_and_cannot_accept(): void
    {
        $poor = $this->driver(10000);
        $ride = $this->ride();

        app(RideDispatchService::class)->start($ride->fresh());
        $this->assertNotContains($poor->id, $ride->fresh()->dispatch_queue ?? []);

        $this->withToken($poor->api_token)->postJson("/api/v1/rides/{$ride->id}/accept")
            ->assertForbidden()
            ->assertJsonPath('code', 'low_balance');
    }

    public function test_withdrawal_sends_exactly_one_low_balance_push(): void
    {
        $driver = $this->driver(60000);
        $driver->update(['fcm_token' => 'fcm-test-token']);

        $this->withToken($driver->api_token)->postJson('/api/v1/driver/withdraw', [
            'amount_khr' => 60000, 'payment_method' => 'aba',
            'account_number' => '000111222', 'account_name' => 'Driver',
        ])->assertOk();

        $this->assertSame(1, PushNotification::where('user_id', $driver->id)->where('type', 'driver_low_balance')->count());
    }

    public function test_admin_approval_takes_a_low_balance_driver_offline(): void
    {
        $driver = $this->driver(60000);
        $driver->update(['fcm_token' => 'fcm-test-token']);
        $this->withToken($driver->api_token)->postJson('/api/v1/driver/withdraw', [
            'amount_khr' => 60000, 'payment_method' => 'aba',
            'account_number' => '000111222', 'account_name' => 'Driver',
        ])->assertOk();

        // Driver went back online somehow (old app, stale flag) before approval.
        \Illuminate\Support\Facades\DB::table('users')->where('id', $driver->id)->update(['available' => true]);

        $admin = User::factory()->create(['role' => 'admin', 'api_token' => 'a-' . uniqid(), 'token_expires_at' => now()->addHour()]);
        $withdrawal = \App\Models\WithdrawalRequest::where('driver_id', $driver->id)->firstOrFail();
        $this->withToken($admin->api_token)->postJson("/api/v1/admin/withdrawals/{$withdrawal->id}/approve")->assertOk();

        $this->assertFalse((bool) $driver->fresh()->available, 'approval re-checks the balance');
    }

    public function test_balance_dropping_under_the_minimum_while_online_goes_offline(): void
    {
        $driver = $this->driver(60000);

        app(\App\Services\WalletService::class)->debit($driver, 20000, 'adjustment', 'Admin adjustment');

        $this->assertFalse((bool) $driver->fresh()->available);
    }
}

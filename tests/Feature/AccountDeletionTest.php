<?php

namespace Tests\Feature;

use App\Models\Ride;
use App\Models\User;
use App\Models\UserSavedPlace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/** Google Play requires apps with sign-up to let users delete their account. */
class AccountDeletionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
    }

    private function user(string $role = 'passenger'): User
    {
        return User::factory()->create([
            'role'             => $role,
            'phone'            => '012345678',
            'api_token'        => 'test-token-' . uniqid(),
            'token_expires_at' => now()->addHour(),
            'fcm_token'        => 'fcm-abc',
        ]);
    }

    public function test_user_can_delete_their_account(): void
    {
        $user = $this->user();
        $token = $user->api_token;
        $email = $user->email;
        UserSavedPlace::create([
            'user_id' => $user->id, 'label' => 'Home', 'address' => 'Phnom Penh',
            'lat' => 11.55, 'lng' => 104.92,
        ]);
        $ride = Ride::create([
            'passenger_id' => $user->id, 'pickup_address' => 'A', 'pickup_lat' => 11.5,
            'pickup_lng' => 104.9, 'service_type' => 'tuk_tuk', 'status' => Ride::STATUS_COMPLETED, 'fare' => 5000,
        ]);

        $this->withToken($token)->deleteJson('/api/v1/auth/account')->assertOk();

        $user->refresh();
        $this->assertSame('Deleted user', $user->name);
        $this->assertNotSame($email, $user->email);
        $this->assertNull($user->phone);
        $this->assertNull($user->api_token);
        $this->assertNull($user->fcm_token);
        $this->assertSame(0, UserSavedPlace::where('user_id', $user->id)->count());
        // Trip history is kept (accounting), just no longer identifiable.
        $this->assertTrue(Ride::whereKey($ride->id)->exists());

        // The old session no longer works.
        $this->withToken($token)->getJson('/api/v1/auth/me')->assertUnauthorized();
    }

    public function test_cannot_delete_during_an_active_trip(): void
    {
        $driver = $this->user('driver');
        Ride::create([
            'passenger_id' => $this->user()->id, 'driver_id' => $driver->id, 'pickup_address' => 'A',
            'pickup_lat' => 11.5, 'pickup_lng' => 104.9, 'service_type' => 'tuk_tuk',
            'status' => Ride::STATUS_IN_PROGRESS, 'fare' => 5000,
        ]);

        $this->withToken($driver->api_token)->deleteJson('/api/v1/auth/account')->assertUnprocessable();
        $this->assertNotNull($driver->fresh()->api_token);
    }

    public function test_requires_sign_in(): void
    {
        $this->deleteJson('/api/v1/auth/account')->assertUnauthorized();
    }

    public function test_public_deletion_page_is_reachable(): void
    {
        $this->get('/delete-account')->assertOk()->assertSee('Delete account');
    }
}

<?php

namespace Tests\Feature;

use App\Models\DriverDevice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FcmTokenTest extends TestCase
{
    use RefreshDatabase;

    private const PHONE_TOKEN = 'fcm-token-of-this-phone';

    private function user(string $role = 'passenger'): User
    {
        return User::factory()->create([
            'role'             => $role,
            'api_token'        => 'test-token-' . uniqid(),
            'token_expires_at' => now()->addHour(),
        ]);
    }

    private function register(User $user, string $token = self::PHONE_TOKEN)
    {
        return $this->withHeader('Authorization', 'Bearer ' . $user->api_token)
            ->postJson('/api/v1/auth/fcm-token', ['fcm_token' => $token, 'platform' => 'ios']);
    }

    public function test_token_is_saved_for_the_logged_in_user(): void
    {
        $user = $this->user();
        $this->register($user)->assertOk();

        $this->assertSame(self::PHONE_TOKEN, $user->fresh()->fcm_token);
    }

    public function test_another_account_on_the_same_phone_takes_over_the_token(): void
    {
        $first  = $this->user('driver');
        $second = $this->user('driver');

        $this->register($first)->assertOk();
        $this->register($second)->assertOk();

        $this->assertNull($first->fresh()->fcm_token, "first account's pushes must stop reaching this phone");
        $this->assertSame(self::PHONE_TOKEN, $second->fresh()->fcm_token);
        $this->assertSame(1, DriverDevice::where('token', self::PHONE_TOKEN)->where('is_active', true)->count());
        $this->assertSame($second->id, DriverDevice::where('token', self::PHONE_TOKEN)->value('user_id'));
    }

    public function test_logout_unlinks_this_phones_token(): void
    {
        $driver = $this->user('driver');
        $this->register($driver)->assertOk();

        $this->withHeader('Authorization', 'Bearer ' . $driver->api_token)
            ->postJson('/api/v1/auth/logout', ['fcm_token' => self::PHONE_TOKEN])
            ->assertOk();

        $this->assertNull($driver->fresh()->fcm_token);
        $this->assertFalse((bool) DriverDevice::where('token', self::PHONE_TOKEN)->value('is_active'));
    }

    public function test_logout_from_another_phone_keeps_this_token(): void
    {
        $user = $this->user();
        $this->register($user)->assertOk();

        $this->withHeader('Authorization', 'Bearer ' . $user->api_token)
            ->postJson('/api/v1/auth/logout', ['fcm_token' => 'token-of-a-different-phone'])
            ->assertOk();

        $this->assertSame(self::PHONE_TOKEN, $user->fresh()->fcm_token);
    }

    public function test_logout_without_a_token_still_works(): void
    {
        $user = $this->user();
        $this->register($user)->assertOk();

        $this->withHeader('Authorization', 'Bearer ' . $user->api_token)
            ->postJson('/api/v1/auth/logout')
            ->assertOk();

        $this->assertNull($user->fresh()->api_token);
    }
}

<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** These returned HTTP 500 (Collection passed to success(array)) until 2026-11-01. */
class ListEndpointsTest extends TestCase
{
    use RefreshDatabase;

    public function test_collection_endpoints_respond(): void
    {
        $user = User::factory()->create([
            'role' => 'passenger', 'api_token' => 't-' . uniqid(), 'token_expires_at' => now()->addHour(),
        ]);

        foreach (['/auth/biometric/devices', '/accounts', '/vouchers', '/vouchers/mine'] as $path) {
            $this->withToken($user->api_token)->getJson('/api/v1' . $path)
                ->assertOk()
                ->assertJsonIsArray('data');
        }
    }
}

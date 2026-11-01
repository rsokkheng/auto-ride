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

    public function test_accessibility_settings_save(): void
    {
        $user = User::factory()->create([
            'role' => 'passenger', 'api_token' => 't-' . uniqid(), 'token_expires_at' => now()->addHour(),
        ]);

        $this->withToken($user->api_token)
            ->putJson('/api/v1/accessibility', ['font_size' => 'large', 'high_contrast' => true])
            ->assertOk()
            ->assertJsonPath('data.font_size', 'large')
            ->assertJsonPath('message', 'Accessibility settings updated.');
        $this->assertSame('large', $user->fresh()->accessibility_settings['font_size']);
    }
}

<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class HelmetCheckTest extends TestCase
{
    use RefreshDatabase;

    private function driver(): User
    {
        return User::factory()->create(['role' => 'driver', 'api_token' => 't-' . uniqid(), 'token_expires_at' => now()->addHour()]);
    }

    public function test_helmet_photo_passes(): void
    {
        config(['services.google_vision.key' => 'test-key']);
        Http::fake(['vision.googleapis.com/*' => Http::response(['responses' => [[
            'labelAnnotations' => [['description' => 'Motorcycle helmet', 'score' => 0.93]],
        ]]])]);

        $this->withToken($this->driver()->api_token)
            ->post('/api/v1/driver/helmet-check', ['image' => UploadedFile::fake()->image('h.jpg')], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('data.passed', true)
            ->assertJsonPath('data.helmet_detected', true);
    }

    public function test_missing_key_gives_a_clear_message(): void
    {
        config(['services.google_vision.key' => null, 'services.google_maps.key' => null]);

        $this->withToken($this->driver()->api_token)
            ->post('/api/v1/driver/helmet-check', ['image' => UploadedFile::fake()->image('h.jpg')], ['Accept' => 'application/json'])
            ->assertStatus(503)
            ->assertJsonPath('code', 'not_configured');
    }
}

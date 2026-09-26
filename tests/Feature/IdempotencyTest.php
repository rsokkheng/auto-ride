<?php

namespace Tests\Feature;

use App\Models\TopUpRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IdempotencyTest extends TestCase
{
    use RefreshDatabase;

    private function passenger(): User
    {
        return User::factory()->create([
            'role'             => 'passenger',
            'api_token'        => 'test-token-' . uniqid(),
            'token_expires_at' => now()->addHour(),
        ]);
    }

    private function topUp(User $user, array $body, ?string $key)
    {
        $headers = ['Authorization' => 'Bearer ' . $user->api_token];
        if ($key !== null) $headers['Idempotency-Key'] = $key;

        return $this->withHeaders($headers)->postJson('/api/v1/wallet/topup', $body);
    }

    public function test_retry_with_same_key_replays_instead_of_creating_twice(): void
    {
        $user = $this->passenger();

        $first  = $this->topUp($user, ['amount' => 5000], 'key-1')->assertCreated();
        $second = $this->topUp($user, ['amount' => 5000], 'key-1')->assertCreated();

        $this->assertSame(1, TopUpRequest::where('user_id', $user->id)->count());
        $this->assertSame($first->json('data.top_up_request.id'), $second->json('data.top_up_request.id'));
        $second->assertHeader('Idempotent-Replayed', 'true');
    }

    public function test_same_key_with_different_body_is_rejected(): void
    {
        $user = $this->passenger();

        $this->topUp($user, ['amount' => 5000], 'key-2')->assertCreated();
        $this->topUp($user, ['amount' => 9000], 'key-2')->assertStatus(422);

        $this->assertSame(1, TopUpRequest::where('user_id', $user->id)->count());
    }

    public function test_without_key_every_request_runs(): void
    {
        $user = $this->passenger();

        $this->topUp($user, ['amount' => 5000], null)->assertCreated();
        $this->topUp($user, ['amount' => 5000], null)->assertCreated();

        $this->assertSame(2, TopUpRequest::where('user_id', $user->id)->count());
    }

    public function test_keys_are_scoped_per_user(): void
    {
        $alice = $this->passenger();
        $bob   = $this->passenger();

        $this->topUp($alice, ['amount' => 5000], 'shared-key')->assertCreated();
        $this->topUp($bob, ['amount' => 5000], 'shared-key')->assertCreated()->assertHeaderMissing('Idempotent-Replayed');

        $this->assertSame(1, TopUpRequest::where('user_id', $bob->id)->count());
    }

    public function test_validation_errors_are_replayed_too(): void
    {
        $user = $this->passenger();

        $this->topUp($user, ['amount' => 1], 'key-3')->assertStatus(422);
        $this->topUp($user, ['amount' => 1], 'key-3')->assertStatus(422)->assertHeader('Idempotent-Replayed', 'true');

        $this->assertSame(0, TopUpRequest::where('user_id', $user->id)->count());
    }
}

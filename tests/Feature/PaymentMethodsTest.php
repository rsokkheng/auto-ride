<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/** Supported payment methods: cash, ABA, ACLEDA, ROTEH Pay (wallet). */
class PaymentMethodsTest extends TestCase
{
    use RefreshDatabase;

    public function test_acleda_is_accepted_for_a_delivery(): void
    {
        Http::fake(); Queue::fake();
        $u = User::factory()->create(['role' => 'passenger', 'api_token' => 't-' . uniqid(), 'token_expires_at' => now()->addHour()]);

        $this->withToken($u->api_token)->postJson('/api/v1/deliveries', [
            'sender_name' => 'Dara', 'recipient_name' => 'Sokha', 'recipient_phone' => '012345678',
            'pickup_address' => 'Phnom Penh', 'dropoff_address' => 'Toul Kork',
            'package_size' => 'small', 'payment_method' => 'acleda',
        ])->assertCreated()->assertJsonPath('data.delivery.payment_method', 'acleda');
    }
}

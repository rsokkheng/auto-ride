<?php

namespace Tests\Feature;

use App\Models\PushNotification;
use App\Models\TopUpRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class WalletTopUpTest extends TestCase
{
    use RefreshDatabase;

    private User $driver;

    protected function setUp(): void
    {
        parent::setUp();
        Http::fake();
        Queue::fake();
        $this->driver = User::factory()->create([
            'role' => 'driver', 'wallet_balance' => 0, 'fcm_token' => 'fcm-test',
            'api_token' => 't-' . uniqid(), 'token_expires_at' => now()->addHour(),
        ]);
    }

    private function topUp(string $method = 'aba')
    {
        return $this->withToken($this->driver->api_token)
            ->postJson('/api/v1/wallet/topup', ['amount' => 50000, 'method' => $method]);
    }

    public function test_bank_methods_are_accepted(): void
    {
        $this->topUp('acleda')->assertCreated()->assertJsonPath('data.top_up_request.method', 'acleda');
    }

    public function test_only_one_pending_request_and_it_shows_in_the_wallet(): void
    {
        $this->topUp('wing')->assertCreated();
        $this->topUp('aba')->assertStatus(422)->assertJsonPath('code', 'topup_pending');

        $this->withToken($this->driver->api_token)->getJson('/api/v1/wallet')
            ->assertOk()
            ->assertJsonPath('data.pending_topup.amount', 50000)
            ->assertJsonPath('data.pending_topup.method', 'wing');
    }

    public function test_approval_pushes_the_user(): void
    {
        $id = $this->topUp()->json('data.top_up_request.id');
        $admin = User::factory()->create(['role' => 'admin', 'api_token' => 'a-' . uniqid(), 'token_expires_at' => now()->addHour()]);

        $this->withToken($admin->api_token)->postJson("/api/v1/admin/topups/{$id}/approve")->assertOk();

        $this->assertSame(50000, (int) $this->driver->fresh()->wallet_balance);
        $this->assertTrue(PushNotification::where('user_id', $this->driver->id)->where('type', 'topup_approved')->exists());
        $this->assertSame('approved', TopUpRequest::find($id)->status);
    }

    public function test_admin_page_lists_passenger_top_ups_and_filters_by_role(): void
    {
        $passenger = User::factory()->create(['role' => 'passenger', 'name' => 'Pax Sokha', 'api_token' => 'p-' . uniqid(), 'token_expires_at' => now()->addHour()]);
        $this->withToken($passenger->api_token)->postJson('/api/v1/wallet/topup', ['amount' => 20000, 'method' => 'aba'])->assertCreated();
        $this->driver->update(['name' => 'Drv Dara']);
        $this->topUp('acleda')->assertCreated();

        $this->actingAs(User::factory()->create(['role' => 'admin']));

        $this->get(route('admin.topups'))->assertOk()
            ->assertSee('Pax Sokha')->assertSee('Drv Dara')->assertSee('Passenger');
        $this->get(route('admin.topups', ['role' => 'passenger']))->assertOk()
            ->assertSee('Pax Sokha')->assertDontSee('Drv Dara');
    }
}

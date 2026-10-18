<?php

namespace Tests\Feature;

use App\Models\AdminNotification;
use App\Models\MarketplaceProduct;
use App\Models\User;
use App\Services\FcmService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class AdminMobileNotificationTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role, ?string $fcm = null): User
    {
        return User::factory()->create([
            'role'             => $role,
            'api_token'        => 'test-token-' . uniqid(),
            'token_expires_at' => now()->addHour(),
            'fcm_token'        => $fcm,
        ]);
    }

    private function placeGuestOrder(User $seller): void
    {
        $product = MarketplaceProduct::create([
            'seller_id' => $seller->id, 'title' => 'Tuk Tuk', 'slug' => 'tuk-' . uniqid(),
            'listing_type' => 'sale', 'price' => 100, 'quantity' => 1, 'status' => 'active',
        ]);

        $this->postJson("/api/v1/marketplace/{$product->id}/order", [
            'guest_name' => 'Sokha', 'guest_phone' => '012345678',
            'guest_token' => 'guest-device-token-0123456789abcdef',
        ])->assertCreated();
    }

    public function test_new_order_pushes_to_admin_phones_only(): void
    {
        $fcm = Mockery::spy(FcmService::class);
        $this->app->instance(FcmService::class, $fcm);

        $admin     = $this->user('admin', 'admin-phone');
        $passenger = $this->user('passenger', 'passenger-phone');
        $driver    = $this->user('driver', 'driver-phone');
        $this->placeGuestOrder($this->user('passenger'));

        $fcm->shouldHaveReceived('pushOnly')
            ->withArgs(fn ($u, $title, $body, $data) => $u->is($admin) && $data['type'] === 'admin_alert')->once();
        $fcm->shouldNotHaveReceived('pushOnly', [Mockery::on(fn ($u) => $u->is($passenger) || $u->is($driver)),
            Mockery::any(), Mockery::any(), Mockery::any()]);
    }

    public function test_admin_can_list_and_read_alerts(): void
    {
        $admin = $this->user('admin');
        $this->placeGuestOrder($this->user('passenger'));
        $auth = ['Authorization' => 'Bearer ' . $admin->api_token];

        $this->withHeaders($auth)->getJson('/api/v1/admin/notifications')
            ->assertOk()
            ->assertJsonPath('data.counts.all', 1)
            ->assertJsonPath('data.notifications.0.type', AdminNotification::PURCHASE_ORDER)
            ->assertJsonPath('data.notifications.0.customer', 'Sokha');

        $id = AdminNotification::sole()->id;
        $this->withHeaders($auth)->postJson("/api/v1/admin/notifications/{$id}/read")
            ->assertOk()->assertJsonPath('data.counts.all', 0);
    }

    public function test_passengers_and_drivers_cannot_use_admin_alerts(): void
    {
        foreach (['passenger', 'driver'] as $role) {
            $this->withHeaders(['Authorization' => 'Bearer ' . $this->user($role)->api_token])
                ->getJson('/api/v1/admin/notifications')
                ->assertUnauthorized();
        }
    }

    public function test_admin_cannot_read_another_admins_alert(): void
    {
        $this->user('admin');
        $other = $this->user('admin');
        $this->placeGuestOrder($this->user('passenger'));
        $notMine = AdminNotification::where('user_id', '!=', $other->id)->firstOrFail();

        $this->withHeaders(['Authorization' => 'Bearer ' . $other->api_token])
            ->postJson("/api/v1/admin/notifications/{$notMine->id}/read")
            ->assertUnauthorized();
    }
}

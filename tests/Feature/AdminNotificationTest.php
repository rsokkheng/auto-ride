<?php

namespace Tests\Feature;

use App\Events\RealtimeUpdate;
use App\Models\AdminNotification;
use App\Models\CarRental;
use App\Models\MarketplaceOrder;
use App\Models\MarketplaceProduct;
use App\Models\User;
use App\Services\FcmService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Mockery;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminNotificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Buyer/seller pushes from ShopNotifications aren't under test here.
        $this->app->instance(FcmService::class, Mockery::mock(FcmService::class)->shouldIgnoreMissing());
        Event::fake([RealtimeUpdate::class]);
    }

    private function product(): MarketplaceProduct
    {
        return MarketplaceProduct::create([
            'seller_id'          => User::factory()->create(['role' => 'passenger'])->id,
            'title'              => 'Phone Holder',
            'slug'               => 'holder-' . uniqid(),
            'listing_type'       => 'both',
            'price'              => 9,
            'rent_price_per_day' => 10,
            'quantity'           => 5,
            'status'             => 'active',
        ]);
    }

    private function order(array $attrs = []): MarketplaceOrder
    {
        $p = $this->product();
        return MarketplaceOrder::create($attrs + [
            'product_id'     => $p->id,
            'buyer_id'       => User::factory()->create(['role' => 'passenger'])->id,
            'seller_id'      => $p->seller_id,
            'order_type'     => 'purchase',
            'quantity'       => 2,
            'unit_price'     => 9,
            'total_price'    => 18,
            'status'         => 'pending',
            'payment_status' => 'unpaid',
        ]);
    }

    public function test_new_purchase_order_notifies_every_admin_and_broadcasts(): void
    {
        $admins = User::factory()->count(2)->create(['role' => 'admin']);
        User::factory()->create(['role' => 'passenger']);

        $order = $this->order();

        $this->assertSame(2, AdminNotification::count());
        $n = AdminNotification::where('user_id', $admins[0]->id)->sole();
        $this->assertSame(AdminNotification::PURCHASE_ORDER, $n->type);
        $this->assertSame('purchase', $n->category);
        $this->assertSame($order->id, $n->order_id);
        $this->assertSame('#OD' . str_pad($order->id, 6, '0', STR_PAD_LEFT) . ' - Phone Holder', $n->message);
        $this->assertSame('2× · $18.00', $n->data['detail']);

        Event::assertDispatched(RealtimeUpdate::class, fn ($e) =>
            $e->channel === "admin.{$admins[0]->id}" && $e->payload['counts']['purchase'] === 1);
    }

    public function test_rentals_payments_and_cancellations_are_categorised(): void
    {
        User::factory()->create(['role' => 'admin']);

        $this->order(['order_type' => 'rent', 'rent_start_date' => '2026-04-20', 'rent_end_date' => '2026-04-22']);
        CarRental::create([
            'user_id' => User::factory()->create()->id, 'vehicle_type' => 'sedan', 'pickup_location' => 'PP',
            'start_date' => '2026-04-20', 'end_date' => '2026-04-22', 'total_days' => 2,
            'daily_rate_khr' => 60000, 'total_amount_khr' => 120000, 'status' => 'pending',
        ]);
        $paid = $this->order();
        $paid->update(['payment_status' => 'paid']);
        $cancelled = $this->order();
        $cancelled->actedBy = 'buyer';
        $cancelled->update(['status' => 'cancelled']);

        $this->assertSame(2, AdminNotification::where('type', AdminNotification::RENTAL_ORDER)->count());
        $this->assertSame('Apr 20 – Apr 22, 2026 · 120,000 ៛', AdminNotification::where('order_kind', 'car_rental')->sole()->data['detail']);
        $this->assertSame(1, AdminNotification::where('type', AdminNotification::PAYMENT_SUCCESS)->count());
        $this->assertStringStartsWith('Cancelled by customer', AdminNotification::where('type', AdminNotification::ORDER_CANCELLED)->sole()->data['detail']);
    }

    public function test_admin_own_actions_do_not_notify_admins(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $order = $this->order();
        AdminNotification::query()->delete();

        $this->actingAs($admin)->post(route('admin.marketplace-orders.cancel', $order))->assertRedirect();

        $this->assertSame('cancelled', $order->fresh()->status);
        $this->assertSame(0, AdminNotification::count());
    }

    public function test_only_admins_with_the_permission_are_notified_once_it_exists(): void
    {
        Permission::create(['name' => 'manage-deliveries', 'guard_name' => 'web']);
        Role::create(['name' => 'Dispatcher', 'guard_name' => 'web']);
        $allowed = User::factory()->create(['role' => 'admin']);
        $allowed->givePermissionTo('manage-deliveries');
        $denied = User::factory()->create(['role' => 'admin']);
        $denied->assignRole('Dispatcher');

        $this->order();

        $this->assertSame([$allowed->id], AdminNotification::pluck('user_id')->all());
    }

    public function test_feed_open_and_mark_all_read(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $other = User::factory()->create(['role' => 'admin']);
        $order = $this->order();
        $this->order(['order_type' => 'rent', 'rent_start_date' => '2026-04-20', 'rent_end_date' => '2026-04-22']);

        $this->actingAs($admin)->getJson(route('admin.notifications.feed', ['category' => 'purchase']))
            ->assertOk()
            ->assertJsonCount(1, 'items')
            ->assertJsonPath('counts', ['all' => 2, 'purchase' => 1, 'rental' => 1, 'system' => 0]);

        $mine = AdminNotification::where('user_id', $admin->id)->where('order_id', $order->id)->where('category', 'purchase')->sole();
        $theirs = AdminNotification::where('user_id', $other->id)->first();

        $this->get(route('admin.notifications.open', $theirs))->assertNotFound();
        $this->get(route('admin.notifications.open', $mine))
            ->assertRedirect(route('admin.marketplace-orders', ['id' => $order->id]));
        $this->assertNotNull($mine->fresh()->read_at);

        $this->postJson(route('admin.notifications.read-all'))->assertJsonPath('counts.all', 0);
        $this->assertSame(2, AdminNotification::where('user_id', $other->id)->unread()->count());

        $this->get(route('admin.notifications'))->assertOk()->assertSee('New Rental Order');
        $this->get(route('admin.dashboard'))->assertOk()->assertSee('anBell', false);
    }

    public function test_non_admins_cannot_use_the_endpoints(): void
    {
        $user = User::factory()->create(['role' => 'passenger']);

        $this->actingAs($user)->getJson(route('admin.notifications.feed'))->assertUnauthorized();
        $this->post(route('admin.broadcasting.auth'), ['channel_name' => "private-admin.{$user->id}", 'socket_id' => '1.1'])
            ->assertRedirect(route('admin.login'));
    }
}

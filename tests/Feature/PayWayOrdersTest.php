<?php

namespace Tests\Feature;

use App\Models\CarRental;
use App\Models\Delivery;
use App\Models\MarketplaceOrder;
use App\Models\MarketplaceProduct;
use App\Models\Ride;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Models\UserSubscription;
use App\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/** Every ABA / ACLEDA payment goes through ABA PayWay and is paid only once PayWay confirms. */
class PayWayOrdersTest extends TestCase
{
    use RefreshDatabase;

    private int $paywayStatus = 2;
    private User $passenger;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        config(['payway.merchant_id' => 'ec000001', 'payway.api_key' => 'k', 'payway.callback_key' => 'k']);
        Http::fake([
            '*/payments/generate-qr' => Http::response(['status' => ['code' => '0'], 'qrString' => 'KHQR', 'qrImage' => null]),
            '*/payments/check-transaction-2' => fn () => Http::response([
                'data' => ['payment_status_code' => $this->paywayStatus, 'payment_amount' => 999999999],
                'status' => ['code' => '00'],
            ]),
            '*' => Http::response([]),
        ]);
        $this->passenger = User::factory()->create(['role' => 'passenger', 'api_token' => 'p-' . uniqid(), 'token_expires_at' => now()->addHour()]);
    }

    private function pay(string $type, int $id, array $extra = []): string
    {
        $tran = $this->withToken($this->passenger->api_token)
            ->postJson('/api/v1/payments/payway/order', ['type' => $type, 'id' => $id] + $extra)
            ->assertCreated()->json('data.tran_id');
        $this->paywayStatus = 0; // customer pays
        $this->withToken($this->passenger->api_token)->getJson("/api/v1/payments/payway/{$tran}")
            ->assertOk()->assertJsonPath('data.status', 'paid');
        return $tran;
    }

    private function driver(): User
    {
        return User::factory()->create(['role' => 'driver', 'wallet_balance' => 100000]);
    }

    public function test_aba_ride_is_awaiting_payment_until_paid_from_the_receipt(): void
    {
        $ride = Ride::create([
            'passenger_id' => $this->passenger->id, 'driver_id' => $this->driver()->id, 'pickup_address' => 'A',
            'pickup_lat' => 11.55, 'pickup_lng' => 104.92, 'service_type' => 'tuk_tuk',
            'status' => Ride::STATUS_COMPLETED, 'fare' => 8000, 'payment_method' => 'aba',
        ]);
        app(PaymentService::class)->processRide($ride);
        $this->assertSame('awaiting_payment', $ride->fresh()->payment_status, 'not paid just because the trip ended');

        $this->pay('ride', $ride->id);
        $this->assertSame('paid', $ride->fresh()->payment_status);
        Http::assertSent(fn (HttpRequest $r) => str_ends_with($r->url(), 'generate-qr') && $r['amount'] === 8000 && $r['currency'] === 'KHR');
    }

    public function test_prepaid_delivery_credits_the_driver_once_on_completion(): void
    {
        $driver = $this->driver();
        $d = Delivery::create([
            'sender_id' => $this->passenger->id, 'driver_id' => $driver->id, 'sender_name' => 'A',
            'pickup_address' => 'A', 'dropoff_address' => 'B', 'package_size' => 'small', 'fee' => 12000,
            'status' => 'accepted', 'service_type' => 'delivery', 'payment_method' => 'aba',
        ]);
        $this->pay('delivery', $d->id);
        $this->assertSame('prepaid', $d->fresh()->payment_status);

        $before = (int) $driver->fresh()->wallet_balance;
        app(PaymentService::class)->settleDelivery($d->fresh());
        app(PaymentService::class)->settleDelivery($d->fresh()); // finished twice
        $this->assertSame('paid', $d->fresh()->payment_status);
        $this->assertGreaterThan($before, (int) $driver->fresh()->wallet_balance);
        $this->assertSame(1, \App\Models\TransactionRecord::where('reference_type', Delivery::class)->where('reference_id', $d->id)->count());
    }

    public function test_marketplace_checkout_is_charged_in_usd_for_the_whole_batch(): void
    {
        $seller = User::factory()->create(['role' => 'passenger']);
        $p = MarketplaceProduct::create(['seller_id' => $seller->id, 'title' => 'Tuk', 'slug' => 't-' . uniqid(),
            'listing_type' => 'both', 'price' => 100, 'quantity' => 5, 'status' => 'active']);
        $mk = fn ($total) => MarketplaceOrder::create(['product_id' => $p->id, 'seller_id' => $seller->id,
            'buyer_id' => $this->passenger->id, 'order_type' => 'purchase', 'quantity' => 1, 'unit_price' => $total,
            'total_price' => $total, 'status' => 'pending', 'payment_method' => 'aba', 'checkout_batch_id' => 'B1']);
        $a = $mk(100.50); $b = $mk(20.25);

        $this->pay('marketplace', $a->id);
        Http::assertSent(fn (HttpRequest $r) => str_ends_with($r->url(), 'generate-qr') && $r['currency'] === 'USD' && abs($r['amount'] - 120.75) < 0.001);
        $this->assertSame('paid', $a->fresh()->payment_status);
        $this->assertSame('paid', $b->fresh()->payment_status);
    }

    public function test_guest_car_rental_can_be_paid_with_its_guest_token(): void
    {
        $r = CarRental::create(['user_id' => null, 'entry_type' => 'guest', 'guest_name' => 'Dara', 'guest_phone' => '012345678',
            'guest_token' => 'tok-123', 'vehicle_type' => 'car', 'pickup_location' => 'PP', 'start_date' => now()->toDateString(),
            'end_date' => now()->addDay()->toDateString(), 'total_days' => 1, 'daily_rate_khr' => 50000, 'total_amount_khr' => 50000,
            'payment_method' => 'acleda', 'status' => 'pending']);

        $tran = $this->postJson('/api/v1/payments/payway/order', ['type' => 'car_rental', 'id' => $r->id, 'guest_token' => 'tok-123'])
            ->assertCreated()->json('data.tran_id');
        $this->postJson('/api/v1/payments/payway/order', ['type' => 'car_rental', 'id' => $r->id, 'guest_token' => 'wrong'])
            ->assertStatus(422);

        $this->paywayStatus = 0;
        $this->getJson("/api/v1/payments/payway/{$tran}?guest_token=tok-123")->assertOk()->assertJsonPath('data.status', 'paid');
        $this->assertSame('paid', $r->fresh()->payment_status);
    }

    public function test_aba_subscription_starts_only_after_payment(): void
    {
        $plan = SubscriptionPlan::first() ?? SubscriptionPlan::create(['name' => 'Basic', 'slug' => 'basic', 'price_khr' => 9900, 'billing_cycle' => 'monthly', 'is_active' => true]);
        $subId = $this->withToken($this->passenger->api_token)
            ->postJson('/api/v1/subscriptions/subscribe', ['plan_slug' => $plan->slug, 'payment_method' => 'aba'])
            ->assertCreated()->json('data.id');
        $this->assertSame('pending', UserSubscription::find($subId)->status, 'no plan without payment');

        $this->pay('subscription', $subId);
        $this->assertSame('active', UserSubscription::find($subId)->status);
    }
}

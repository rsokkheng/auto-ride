<?php

namespace Tests\Feature;

use App\Models\CarRental;
use App\Models\MarketplaceOrder;
use App\Models\MarketplaceProduct;
use App\Models\PromoCode;
use App\Models\User;
use App\Services\FcmService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class ShopGuestPromoTest extends TestCase
{
    use RefreshDatabase;

    private const GUEST_TOKEN = 'guest-device-token-0123456789abcdef';

    private function user(string $role = 'passenger'): User
    {
        return User::factory()->create([
            'role'             => $role,
            'api_token'        => 'test-token-' . uniqid(),
            'token_expires_at' => now()->addHour(),
        ]);
    }

    private function product(User $seller, array $attrs = []): MarketplaceProduct
    {
        return MarketplaceProduct::create($attrs + [
            'seller_id'          => $seller->id,
            'title'              => 'Electric Tuk Tuk',
            'slug'               => 'tuk-' . uniqid(),
            'listing_type'       => 'both',
            'price'              => 100,
            'rent_price_per_day' => 10,
            'quantity'           => 5,
            'status'             => 'active',
        ]);
    }

    private function coupon(string $serviceType, array $attrs = []): PromoCode
    {
        return PromoCode::create($attrs + [
            'code'           => 'SHOP10',
            'type'           => 'percent',
            'value'          => 10,
            'min_order'      => 0,
            'per_user_limit' => 1,
            'service_type'   => $serviceType,
            'active'         => true,
        ]);
    }

    private function guestFields(array $extra = []): array
    {
        return $extra + [
            'guest_name'  => 'Sokha',
            'guest_phone' => '012345678',
            'guest_token' => self::GUEST_TOKEN,
            'fcm_token'   => 'guest-fcm-token',
        ];
    }

    public function test_guest_can_buy_without_logging_in(): void
    {
        $product = $this->product($this->user());

        $this->postJson("/api/v1/marketplace/{$product->id}/order", $this->guestFields(['quantity' => 2]))
            ->assertCreated()
            ->assertJsonPath('data.order.is_guest', true);

        $order = MarketplaceOrder::sole();
        $this->assertNull($order->buyer_id);
        $this->assertSame('guest', $order->entry_type);
        $this->assertSame(self::GUEST_TOKEN, $order->guest_token);
    }

    public function test_guest_order_needs_contact_details(): void
    {
        $product = $this->product($this->user());

        $this->postJson("/api/v1/marketplace/{$product->id}/order", ['quantity' => 1])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['guest_name', 'guest_phone', 'guest_token']);
    }

    public function test_expired_login_is_rejected_instead_of_becoming_a_guest(): void
    {
        $product = $this->product($this->user());

        $this->withHeader('Authorization', 'Bearer not-a-real-token')
            ->postJson("/api/v1/marketplace/{$product->id}/order", $this->guestFields())
            ->assertUnauthorized();
    }

    public function test_guest_sees_and_cancels_only_their_own_orders(): void
    {
        $product = $this->product($this->user());
        $this->postJson("/api/v1/marketplace/{$product->id}/order", $this->guestFields())->assertCreated();
        $order = MarketplaceOrder::sole();

        $this->getJson('/api/v1/marketplace/guest-orders?guest_token=someone-elses-token-000000')
            ->assertOk()->assertJsonCount(0, 'data.orders');
        $this->getJson('/api/v1/marketplace/guest-orders?guest_token=' . self::GUEST_TOKEN)
            ->assertOk()->assertJsonCount(1, 'data.orders')
            ->assertJsonMissingPath('data.orders.0.guest_token');

        $this->postJson("/api/v1/marketplace/orders/{$order->id}/cancel", ['guest_token' => 'wrong-token-0000000000000'])
            ->assertUnauthorized();
        $this->postJson("/api/v1/marketplace/orders/{$order->id}/cancel", ['guest_token' => self::GUEST_TOKEN])
            ->assertOk();
        $this->assertSame('cancelled', $order->fresh()->status);
    }

    public function test_product_coupon_discounts_a_purchase(): void
    {
        $product = $this->product($this->user());
        $this->coupon('marketplace');
        $buyer = $this->user();

        $this->withHeader('Authorization', 'Bearer ' . $buyer->api_token)
            ->postJson("/api/v1/marketplace/{$product->id}/order", ['quantity' => 1, 'promo_code' => 'shop10'])
            ->assertCreated()
            ->assertJsonPath('data.order.promo_applied', true)
            ->assertJsonPath('data.order.total_price_usd', 90);

        $this->assertDatabaseHas('promo_code_usages', ['user_id' => $buyer->id, 'bookable_type' => MarketplaceOrder::class]);
    }

    public function test_guest_coupon_is_limited_per_phone(): void
    {
        $this->coupon('product_purchase');
        $productA = $this->product($this->user());
        $productB = $this->product($this->user());

        $this->postJson("/api/v1/marketplace/{$productA->id}/order", $this->guestFields(['promo_code' => 'SHOP10']))
            ->assertCreated()->assertJsonPath('data.order.total_price_usd', 90);

        $this->postJson("/api/v1/marketplace/{$productB->id}/order",
            $this->guestFields(['promo_code' => 'SHOP10', 'guest_phone' => '+855 12 345 678', 'guest_token' => 'another-device-token-000000']))
            ->assertUnprocessable();
    }

    public function test_ride_only_coupon_is_refused_for_products(): void
    {
        $product = $this->product($this->user());
        $this->coupon('rides');

        $this->postJson("/api/v1/marketplace/{$product->id}/order", $this->guestFields(['promo_code' => 'SHOP10']))
            ->assertUnprocessable();
        $this->assertSame(0, MarketplaceOrder::count());
    }

    public function test_promo_check_works_for_guests_on_products_only(): void
    {
        $this->coupon('product_rent');

        $this->postJson('/api/v1/promo/apply', ['code' => 'SHOP10', 'service_type' => 'product_rent', 'order_amount' => 41000])
            ->assertOk()->assertJsonPath('data.discount_amount', 4100);
        $this->postJson('/api/v1/promo/apply', ['code' => 'SHOP10', 'service_type' => 'product_purchase', 'order_amount' => 41000])
            ->assertUnprocessable();
        $this->postJson('/api/v1/promo/apply', ['code' => 'SHOP10', 'service_type' => 'rides', 'order_amount' => 41000])
            ->assertUnauthorized();
    }

    public function test_guest_can_rent_with_a_rent_coupon(): void
    {
        $product = $this->product($this->user());
        $this->coupon('product_rent');

        $this->postJson('/api/v1/rentals', $this->guestFields([
            'marketplace_product_id' => $product->id,
            'pickup_location'        => 'Phnom Penh',
            'start_date'             => now()->addDay()->toDateString(),
            'end_date'               => now()->addDays(2)->toDateString(),
            'promo_code'             => 'SHOP10',
        ]))->assertOk();

        $rental = CarRental::sole();
        $this->assertSame('guest', $rental->entry_type);
        $this->assertSame((int) round($rental->daily_rate_khr * 2 * 0.9), $rental->total_amount_khr);

        $this->getJson('/api/v1/marketplace/guest-orders?guest_token=' . self::GUEST_TOKEN)
            ->assertOk()->assertJsonPath('data.rentals.0.source', 'car_rental');
    }

    public function test_seller_and_guest_are_notified_through_the_order_lifecycle(): void
    {
        $fcm = Mockery::spy(FcmService::class);
        $this->app->instance(FcmService::class, $fcm);

        $seller  = $this->user();
        $product = $this->product($seller);

        $this->postJson("/api/v1/marketplace/{$product->id}/order", $this->guestFields())->assertCreated();
        $order = MarketplaceOrder::sole();

        $fcm->shouldHaveReceived('notifyUser')
            ->withArgs(fn ($user, $title, $body, $data) => $user->is($seller) && $data['type'] === 'shop_order_new')->once();
        $fcm->shouldHaveReceived('sendToToken')
            ->withArgs(fn ($token, $title, $body, $data) => $token === 'guest-fcm-token' && $data['type'] === 'shop_order_placed')->once();

        $this->withHeader('Authorization', 'Bearer ' . $seller->api_token)
            ->postJson("/api/v1/marketplace/orders/{$order->id}/confirm")->assertOk();

        $fcm->shouldHaveReceived('sendToToken')
            ->withArgs(fn ($token, $title, $body, $data) => $data['type'] === 'shop_order_confirmed')->once();
        $fcm->shouldNotHaveReceived('notifyUser', [Mockery::on(fn ($u) => $u->is($seller)), Mockery::any(), Mockery::any(),
            Mockery::on(fn ($d) => $d['type'] === 'shop_order_confirmed')]);
    }

    public function test_seller_without_a_device_still_gets_an_in_app_notification(): void
    {
        $seller  = $this->user();
        $product = $this->product($seller);

        $this->postJson("/api/v1/marketplace/{$product->id}/order", $this->guestFields())->assertCreated();

        $this->assertDatabaseHas('push_notifications', [
            'user_id'    => $seller->id,
            'type'       => 'shop_order_new',
            'fcm_status' => 'NO_TOKEN',
        ]);
    }
}

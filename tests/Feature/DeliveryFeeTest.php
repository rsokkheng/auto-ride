<?php

namespace Tests\Feature;

use App\Models\Delivery;
use App\Models\PricingSetting;
use App\Models\User;
use App\Services\FareService;
use Database\Seeders\RidePricingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeliveryFeeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RidePricingSeeder::class);
        FareService::clearCache();
    }

    private function makeUser(array $overrides = []): User
    {
        return User::factory()->create(array_merge([
            'role'      => 'passenger',
            'api_token' => 'test-user-token-' . uniqid(),
        ], $overrides));
    }

    public function test_fare_service_calculates_delivery_fare_for_all_package_sizes(): void
    {
        $fareService = app(FareService::class);
        $route = [
            'distance_km'   => 5.0,
            'duration_min'  => 15,
            'distance_text' => '5.0 km',
            'duration_text' => '15 mins',
            'source'        => 'haversine',
        ];

        // Base: 3000, per_km: 1200, 5km distance = 6000, booking fee = 500
        // Small: 0 surcharge -> 3000 + 6000 + 500 = 9500
        $smallFare = $fareService->calculateDeliveryFare('small', $route);
        $this->assertSame(9500, $smallFare['subtotal']);
        $this->assertSame(9500, $smallFare['total']);
        $this->assertSame(0, $smallFare['breakdown']['package_surcharge']);

        // Medium: 2000 surcharge -> 9500 + 2000 = 11500
        $mediumFare = $fareService->calculateDeliveryFare('medium', $route);
        $this->assertSame(11500, $mediumFare['subtotal']);
        $this->assertSame(11500, $mediumFare['total']);
        $this->assertSame(2000, $mediumFare['breakdown']['package_surcharge']);

        // Large: 5000 surcharge -> 9500 + 5000 = 14500
        $largeFare = $fareService->calculateDeliveryFare('large', $route);
        $this->assertSame(14500, $largeFare['subtotal']);
        $this->assertSame(14500, $largeFare['total']);
        $this->assertSame(5000, $largeFare['breakdown']['package_surcharge']);

        // Extra Large: 5000 surcharge -> 9500 + 5000 = 14500
        $xlFare = $fareService->calculateDeliveryFare('extra_large', $route);
        $this->assertSame(14500, $xlFare['subtotal']);
        $this->assertSame(14500, $xlFare['total']);
        $this->assertSame(5000, $xlFare['breakdown']['package_surcharge']);
    }

    public function test_estimate_delivery_api_returns_fare_breakdown(): void
    {
        $user = $this->makeUser();

        $response = $this->withHeader('Authorization', 'Bearer ' . $user->api_token)
            ->postJson('/api/v1/deliveries/estimate', [
                'pickup_lat'   => 11.5564,
                'pickup_lng'   => 104.9282,
                'dropoff_lat'  => 11.5600,
                'dropoff_lng'  => 104.9300,
                'package_size' => 'medium',
            ]);

        $response->assertOk()
            ->assertJsonPath('data.service_type', 'delivery')
            ->assertJsonStructure([
                'data' => [
                    'service_type',
                    'route' => ['distance_km', 'duration_min'],
                    'fare' => ['total', 'breakdown' => ['base_fare', 'distance_fare', 'package_surcharge', 'booking_fee']],
                ],
            ]);

        $this->assertGreaterThan(0, $response->json('data.fare.total'));
        $this->assertSame(2000, $response->json('data.fare.breakdown.package_surcharge'));
    }

    public function test_booking_delivery_calculates_fee_and_stores_package_amount(): void
    {
        $user = $this->makeUser();

        $response = $this->withHeader('Authorization', 'Bearer ' . $user->api_token)
            ->postJson('/api/v1/deliveries', [
                'sender_name'     => 'Alice',
                'sender_phone'    => '012345678',
                'recipient_name'  => 'Bob',
                'recipient_phone' => '098765432',
                'pickup_address'  => 'Central Market, Phnom Penh',
                'dropoff_address' => 'Aeon Mall, Phnom Penh',
                'pickup_lat'      => 11.5683,
                'pickup_lng'      => 104.9220,
                'dropoff_lat'     => 11.5476,
                'dropoff_lng'     => 104.9348,
                'package_size'    => 'large',
                'package_amount'  => 50000, // 50,000 KHR goods value (COD)
                'payment_by'      => 'recipient',
                'payment_method'  => 'cash',
            ]);

        $response->assertStatus(201);
        $deliveryId = $response->json('data.delivery.id');
        $this->assertNotNull($deliveryId);

        $delivery = Delivery::find($deliveryId);
        $this->assertNotNull($delivery);

        // Ensure delivery fee was calculated (base + distance + large pkg surcharge + booking fee)
        $this->assertGreaterThan(5000, $delivery->fee);
        $this->assertNotEquals(50000, $delivery->fee); // Fee must NOT be equal to goods value
        $this->assertSame(50000, $delivery->package_amount); // Package amount saved separately
        $this->assertSame('large', $delivery->package_size);
        $this->assertSame('recipient', $delivery->payment_by);
    }

    public function test_booking_delivery_with_express_service_option(): void
    {
        $user = $this->makeUser();

        $responseNormal = $this->withHeader('Authorization', 'Bearer ' . $user->api_token)
            ->postJson('/api/v1/deliveries', [
                'sender_name'     => 'Alice',
                'recipient_name'  => 'Bob',
                'recipient_phone' => '098765432',
                'pickup_address'  => 'Central Market',
                'dropoff_address' => 'Aeon Mall',
                'pickup_lat'      => 11.5683,
                'pickup_lng'      => 104.9220,
                'dropoff_lat'     => 11.5476,
                'dropoff_lng'     => 104.9348,
                'package_size'    => 'small',
                'service_option'  => 'normal',
            ]);

        $responseExpress = $this->withHeader('Authorization', 'Bearer ' . $user->api_token)
            ->postJson('/api/v1/deliveries', [
                'sender_name'     => 'Alice',
                'recipient_name'  => 'Bob',
                'recipient_phone' => '098765432',
                'pickup_address'  => 'Central Market',
                'dropoff_address' => 'Aeon Mall',
                'pickup_lat'      => 11.5683,
                'pickup_lng'      => 104.9220,
                'dropoff_lat'     => 11.5476,
                'dropoff_lng'     => 104.9348,
                'package_size'    => 'small',
                'service_option'  => 'express',
            ]);

        $normalFee  = $responseNormal->json('data.delivery.fee');
        $expressFee = $responseExpress->json('data.delivery.fee');

        $this->assertGreaterThan($normalFee, $expressFee);
    }

    public function test_update_delivery_recalculates_fee_and_updates_package_amount(): void
    {
        $user = $this->makeUser();

        $delivery = Delivery::create([
            'sender_id'       => $user->id,
            'sender_name'     => 'Alice',
            'pickup_address'  => 'Point A',
            'dropoff_address' => 'Point B',
            'pickup_lat'      => 11.5683,
            'pickup_lng'      => 104.9220,
            'dropoff_lat'     => 11.5476,
            'dropoff_lng'     => 104.9348,
            'package_size'    => 'small',
            'fee'             => 6000,
            'package_amount'  => 10000,
            'status'          => 'requested',
            'service_type'    => 'delivery',
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $user->api_token)
            ->patchJson("/api/v1/deliveries/{$delivery->id}", [
                'package_size'   => 'large',
                'package_amount' => 25000,
            ]);

        $response->assertOk();
        $delivery->refresh();
        $this->assertSame('large', $delivery->package_size);
        $this->assertSame(25000, $delivery->package_amount);
        $this->assertGreaterThan(6000, $delivery->fee);
    }

    public function test_admin_can_update_delivery_fare_settings(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        $response = $this->actingAs($admin)
            ->post('/admin/delivery-fare', [
                'delivery_fee_base'                 => 3500,
                'delivery_fee_per_km'               => 1500,
                'delivery_fee_surcharge_small'      => 0,
                'delivery_fee_surcharge_medium'     => 2500,
                'delivery_fee_surcharge_large'      => 6000,
                'delivery_fee_surcharge_extra_large'=> 7000,
                'delivery_night_surcharge_rate'     => 0.20,
                'delivery_express_multiplier'       => 1.30,
            ]);

        $response->assertRedirect(route('admin.delivery-fare'));
        $this->assertSame('3500', PricingSetting::get('delivery_fee_base'));
        $this->assertSame('7000', PricingSetting::get('delivery_fee_surcharge_extra_large'));
    }

    public function test_admin_deliveries_view_displays_delivery_fee_package_amount_and_net_driver(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $driver = User::factory()->create(['role' => 'driver', 'commission_rate' => 20]);
        $sender = User::factory()->create(['role' => 'passenger', 'name' => 'John Sender']);

        $delivery = Delivery::create([
            'sender_id'       => $sender->id,
            'sender_name'     => 'John Sender',
            'recipient_name'  => 'Mary Recipient',
            'recipient_phone' => '012345678',
            'driver_id'       => $driver->id,
            'pickup_address'  => 'Phnom Penh City Center',
            'dropoff_address' => 'Toul Kork, Phnom Penh',
            'pickup_lat'      => 11.5564,
            'pickup_lng'      => 104.9282,
            'dropoff_lat'     => 11.5700,
            'dropoff_lng'     => 104.9100,
            'package_size'    => 'medium',
            'fee'             => 10000,
            'package_amount'  => 45000,
            'status'          => 'in_progress',
            'service_type'    => 'delivery',
        ]);

        $response = $this->actingAs($admin)->get('/admin/deliveries?type=delivery');

        $response->assertOk();
        $response->assertSee('Delivery Fee');
        $response->assertSee('Pkg Amount');
        $response->assertSee('Net Driver');
        $response->assertSee('10,000 ៛');
        $response->assertSee('45,000 ៛');
        $response->assertSee('8,000 ៛'); // 10,000 - 20% = 8,000
        $response->assertSee(route('admin.deliveries.show', $delivery->id));
    }

    public function test_admin_delivery_detail_view_displays_breakdown_and_info(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $driver = User::factory()->create(['role' => 'driver', 'commission_rate' => 20]);
        $sender = User::factory()->create(['role' => 'passenger', 'name' => 'John Sender']);

        $delivery = Delivery::create([
            'sender_id'       => $sender->id,
            'sender_name'     => 'John Sender',
            'recipient_name'  => 'Mary Recipient',
            'recipient_phone' => '012345678',
            'driver_id'       => $driver->id,
            'pickup_address'  => 'Phnom Penh City Center',
            'dropoff_address' => 'Toul Kork, Phnom Penh',
            'pickup_lat'      => 11.5564,
            'pickup_lng'      => 104.9282,
            'dropoff_lat'     => 11.5700,
            'dropoff_lng'     => 104.9100,
            'package_size'    => 'medium',
            'package_details' => 'Fragile gifts',
            'fee'             => 10000,
            'package_amount'  => 45000,
            'status'          => 'in_progress',
            'service_type'    => 'delivery',
            'payment_by'      => 'recipient',
            'payment_method'  => 'cash',
            'payment_status'  => 'unpaid',
        ]);

        $response = $this->actingAs($admin)->get("/admin/deliveries/{$delivery->id}");

        $response->assertOk();
        $response->assertSee("Delivery Order #{$delivery->id}");
        $response->assertSee('Delivery Fee');
        $response->assertSee('Pkg Amount');
        $response->assertSee('10,000 ៛');
        $response->assertSee('45,000 ៛');
        $response->assertSee('8,000 ៛'); // Net driver
        $response->assertSee('2,000 ៛'); // Platform commission
        $response->assertSee('Fragile gifts');
        $response->assertSee('Mary Recipient');
        $response->assertSee('John Sender');
        $response->assertSee('Phnom Penh City Center');
        $response->assertSee('Toul Kork, Phnom Penh');
    }
}

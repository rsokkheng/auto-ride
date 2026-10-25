<?php

namespace Tests\Feature;

use App\Events\RealtimeUpdate;
use App\Models\CarRental;
use App\Models\MarketplaceProduct;
use App\Models\MarketplaceVehicleType;
use App\Models\User;
use App\Services\FcmService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Mockery;
use Tests\TestCase;

class CarRentalVehicleTypeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->instance(FcmService::class, Mockery::mock(FcmService::class)->shouldIgnoreMissing());
        Event::fake([RealtimeUpdate::class]);
    }

    public function test_renting_a_three_wheeler_listing_books_a_tuk_tuk_at_the_listed_price(): void
    {
        $type = MarketplaceVehicleType::create([
            'name_en' => 'Passenger Three-Wheeler', 'name_kh' => 'កង់បីដឹកអ្នកដំណើរ',
            'slug' => 'passenger-three-wheeler', 'active' => true,
        ]);
        $product = MarketplaceProduct::create([
            'seller_id'                   => User::factory()->create()->id,
            'title'                       => '1.8m Three-Wheeler',
            'slug'                        => 'tw-' . uniqid(),
            'listing_type'                => 'both',
            'price'                       => 2405,
            'rent_price_per_day'          => 4.34,
            'quantity'                    => 1,
            'status'                      => 'active',
            'marketplace_vehicle_type_id' => $type->id,
        ]);
        $renter = User::factory()->create([
            'role' => 'passenger', 'api_token' => Str::random(60), 'token_expires_at' => now()->addDay(),
        ]);

        $this->withToken($renter->api_token)->postJson('/api/v1/rentals', [
            'marketplace_product_id' => $product->id,
            'pickup_location'        => 'Phnom Penh',
            'start_date'             => now()->addDay()->toDateString(),
            'end_date'               => now()->addDays(2)->toDateString(),
        ])->assertOk()
          ->assertJsonPath('data.rental.vehicle_type', 'tuk_tuk')
          ->assertJsonPath('data.rental.vehicle_label', 'Passenger Three-Wheeler')
          ->assertJsonPath('data.rental.daily_rate_usd', 4.34);

        $rental = CarRental::firstOrFail();
        $this->assertSame('tuk_tuk', $rental->vehicle_type);
        $this->assertSame(17360, $rental->daily_rate_khr);        // 4.34 × 4000, not the $10 sedan rate
        $this->assertSame(17360 * 2, $rental->total_amount_khr);

        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->get(route('admin.car-rentals'))
            ->assertOk()
            ->assertSee('Passenger Three-Wheeler')
            ->assertSee('$8.68')
            ->assertDontSee('Sedan</span>', false);
    }

    public function test_generic_bookings_keep_the_chosen_type_and_rate(): void
    {
        $renter = User::factory()->create([
            'role' => 'passenger', 'api_token' => Str::random(60), 'token_expires_at' => now()->addDay(),
        ]);

        $this->withToken($renter->api_token)->postJson('/api/v1/rentals', [
            'vehicle_type'    => 'suv',
            'pickup_location' => 'Phnom Penh',
            'start_date'      => now()->addDay()->toDateString(),
            'end_date'        => now()->addDay()->toDateString(),
        ])->assertOk()->assertJsonPath('data.rental.vehicle_type', 'suv');

        $this->assertSame(15 * 4000, CarRental::firstOrFail()->daily_rate_khr);
    }
}

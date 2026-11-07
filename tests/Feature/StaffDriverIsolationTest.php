<?php

namespace Tests\Feature;

use App\Models\Delivery;
use App\Models\Ride;
use App\Models\User;
use App\Services\DeliveryOfferService;
use App\Services\RideDispatchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * ROTEH staff drivers (driver_type = staff) deliver Partner packages ONLY;
 * existing drivers (owner / rental / employee) keep working exactly as before.
 */
class StaffDriverIsolationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        Redis::del('drivers:geo');
    }

    private function driver(string $type, array $o = []): User
    {
        return User::factory()->create($o + [
            'role' => 'driver', 'driver_type' => $type, 'available' => true, 'rating' => 5.0,
            'current_latitude' => 11.5564, 'current_longitude' => 104.9282,
            'api_token' => "t-{$type}-" . uniqid(), 'token_expires_at' => now()->addHour(),
        ]);
    }

    private function as(User $u)
    {
        return $this->withHeaders(['Authorization' => 'Bearer ' . $u->api_token]);
    }

    private function delivery(array $a = []): Delivery
    {
        $sender = User::factory()->create(['role' => 'passenger']);
        return Delivery::create($a + [
            'sender_id' => $sender->id, 'service_type' => 'delivery', 'sender_name' => 'S', 'recipient_name' => 'R',
            'recipient_phone' => '0961234567', 'pickup_address' => 'A', 'dropoff_address' => 'B',
            'pickup_lat' => 11.5564, 'pickup_lng' => 104.9282, 'fee' => 5000, 'status' => 'requested',
            'payment_by' => 'sender', 'payment_method' => 'cash', 'payment_status' => 'unpaid', 'share_token' => Str::random(32),
        ]);
    }

    public function test_staff_never_offered_rides_but_employee_and_owner_are(): void
    {
        $staff    = $this->driver('staff', ['wallet_balance' => 0]);
        $employee = $this->driver('employee', ['wallet_balance' => 100000]);
        $owner    = $this->driver('owner', ['wallet_balance' => 100000]);

        $passenger = User::factory()->create(['role' => 'passenger']);
        $ride = Ride::create(['passenger_id' => $passenger->id, 'pickup_address' => 'P', 'pickup_lat' => 11.5564, 'pickup_lng' => 104.9282,
            'service_type' => 'motorcycle', 'status' => Ride::STATUS_REQUESTED, 'fare' => 5000]);

        app(RideDispatchService::class)->start($ride->fresh());
        $queue = $ride->fresh()->dispatch_queue ?? [];

        $this->assertNotContains($staff->id, $queue);
        $this->assertContains($employee->id, $queue);
        $this->assertContains($owner->id, $queue);

        $this->as($staff)->postJson("/api/v1/rides/{$ride->id}/accept")->assertStatus(403)->assertJsonPath('code', 'partner_only');
        $this->as($staff)->getJson('/api/v1/rides/available')->assertOk();
    }

    public function test_normal_deliveries_skip_staff_and_partner_packages_only_reach_staff(): void
    {
        $staff = $this->driver('staff', ['wallet_balance' => 0]);
        $owner = $this->driver('owner', ['wallet_balance' => 100000]);
        $geo = app(\App\Services\DriverGeoService::class);
        foreach ([$staff, $owner] as $d) $geo->add($d->id, 11.5564, 104.9282);

        $normal  = $this->delivery();
        $partner = User::factory()->create(['role' => 'partner']);
        $package = $this->delivery(['partner_id' => $partner->id, 'sender_id' => $partner->id]);

        $offers = app(DeliveryOfferService::class);
        $this->assertSame([$owner->id], $offers->driversToOffer($normal)->pluck('id')->all());
        $this->assertSame([$staff->id], $offers->driversToOffer($package)->pluck('id')->all());

        // Lists and accept follow the same rule (staff are assigned by admin, never self-pick).
        $this->as($staff)->getJson('/api/v1/deliveries/available')->assertJsonCount(0, 'data.deliveries.data');
        $this->as($owner)->getJson('/api/v1/deliveries/available')->assertJsonCount(1, 'data.deliveries.data')
            ->assertJsonPath('data.deliveries.data.0.id', $normal->id);
        $this->as($staff)->postJson("/api/v1/deliveries/{$normal->id}/accept")->assertStatus(403)->assertJsonPath('code', 'partner_only');
        $this->as($owner)->postJson("/api/v1/deliveries/{$package->id}/accept")->assertStatus(403)->assertJsonPath('code', 'staff_only');
    }

    public function test_existing_drivers_unchanged_wallet_minimum_and_modes(): void
    {
        $poorEmployee = $this->driver('employee', ['wallet_balance' => 0, 'available' => false]);
        $this->as($poorEmployee)->postJson('/api/v1/driver/availability', ['available' => true])->assertStatus(403);

        $staff = $this->driver('staff', ['wallet_balance' => 0, 'available' => false]);
        $this->as($staff)->postJson('/api/v1/driver/availability', ['available' => true])->assertOk();

        // Staff can't switch rides / rentals on.
        $this->as($staff)->postJson('/api/v1/driver/modes', ['ride' => true, 'delivery' => true, 'rental' => true])->assertOk();
        $staff->refresh();
        $this->assertSame([false, true, false], [(bool) $staff->accepts_rides, (bool) $staff->accepts_deliveries, (bool) $staff->accepts_rentals]);
    }

    public function test_staff_today_card(): void
    {
        $staff = $this->driver('staff', ['salary' => 900000]);
        $partner = User::factory()->create(['role' => 'partner']);
        $this->delivery(['partner_id' => $partner->id, 'sender_id' => $partner->id, 'driver_id' => $staff->id,
            'status' => 'completed', 'completed_at' => now(), 'package_amount' => 40000]);

        $this->as($staff)->getJson('/api/v1/driver/staff/today')->assertOk()
            ->assertJsonPath('data.is_staff', true)
            ->assertJsonPath('data.salary', 900000)
            ->assertJsonPath('data.delivered', 1)
            ->assertJsonPath('data.cash_to_hand_over', 40000);

        $owner = $this->driver('owner');
        $this->as($owner)->getJson('/api/v1/driver/staff/today')->assertJsonPath('data.is_staff', false);
    }

    public function test_admin_creates_staff_driver_packages_only_and_approved(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        \Spatie\Permission\Models\Permission::findOrCreate('manage-users', 'web');
        $admin->givePermissionTo('manage-users');
        $this->actingAs($admin)->post(route('admin.users.store'), [
            'name' => 'Staff One', 'email' => 'staff1@roteh.test', 'password' => 'secret123', 'phone' => '012345678',
            'role' => 'driver', 'driver_type' => 'staff', 'salary' => 850000,
        ])->assertRedirect();

        $u = User::where('email', 'staff1@roteh.test')->firstOrFail();
        $this->assertTrue($u->isSalariedStaff());
        $this->assertSame([false, true, false, 'approved', 850000],
            [(bool) $u->accepts_rides, (bool) $u->accepts_deliveries, (bool) $u->accepts_rentals, $u->approval_status, (int) $u->salary]);
    }
}

<?php

namespace Tests\Feature;

use App\Models\Delivery;
use App\Models\PartnerProfile;
use App\Models\TransactionRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

/** Partner packages are delivered by ROTEH staff drivers on a salary; the fee is ROTEH's. */
class PartnerStaffDriverTest extends TestCase
{
    use RefreshDatabase;

    private User $partner;
    private User $staff;
    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        $tok = fn () => ['api_token' => 't-' . uniqid(), 'token_expires_at' => now()->addHour()];
        $this->partner = User::factory()->create(['role' => 'partner', 'wallet_balance' => 0] + $tok());
        PartnerProfile::create(['user_id' => $this->partner->id, 'business_name' => 'Shop', 'phone' => '012345678', 'status' => 'approved']);
        // Staff driver with an empty wallet — salary, no commission.
        $this->staff = User::factory()->create(['role' => 'driver', 'driver_type' => 'employee', 'salary' => 800000, 'wallet_balance' => 0, 'available' => true] + $tok());
        $this->owner = User::factory()->create(['role' => 'driver', 'driver_type' => 'owner', 'wallet_balance' => 100000, 'available' => true] + $tok());
    }

    private function as(User $u)
    {
        return $this->withHeaders(['Authorization' => 'Bearer ' . $u->api_token]);
    }

    private function package(array $a = []): Delivery
    {
        return Delivery::create($a + [
            'partner_id' => $this->partner->id, 'sender_id' => $this->partner->id, 'service_type' => 'delivery',
            'sender_name' => 'Shop', 'recipient_name' => 'Dara', 'recipient_phone' => '0961234567',
            'pickup_address' => 'TK', 'dropoff_address' => 'BKK1', 'pickup_lat' => 11.58, 'pickup_lng' => 104.89,
            'package_size' => 'small', 'fee' => 5000, 'package_amount' => 30000,
            'payment_by' => 'sender', 'payment_method' => 'cash', 'payment_status' => 'unpaid',
            'status' => 'requested', 'confirmed_at' => now(),
            'qr_token' => Str::random(32), 'share_token' => Str::random(32),
        ]);
    }

    public function test_only_staff_see_and_accept_partner_packages(): void
    {
        $d = $this->package();

        $this->as($this->owner)->getJson('/api/v1/deliveries/available')->assertJsonCount(0, 'data.deliveries.data');
        $this->as($this->owner)->postJson("/api/v1/deliveries/{$d->id}/accept")->assertStatus(403)->assertJsonPath('code', 'staff_only');

        // Staff with 0 wallet: sees it and accepts (no wallet minimum for salaried staff).
        $this->as($this->staff)->getJson('/api/v1/deliveries/available')->assertJsonCount(1, 'data.deliveries.data');
        $this->as($this->staff)->postJson("/api/v1/deliveries/{$d->id}/accept")->assertOk();
        $this->assertSame($this->staff->id, $d->fresh()->driver_id);
    }

    public function test_staff_can_go_online_with_empty_wallet_but_owner_cannot(): void
    {
        $this->staff->update(['available' => false]);
        $this->as($this->staff)->postJson('/api/v1/driver/availability', ['available' => true])->assertOk();

        $poor = User::factory()->create(['role' => 'driver', 'driver_type' => 'owner', 'wallet_balance' => 0,
            'api_token' => 'p-' . uniqid(), 'token_expires_at' => now()->addHour()]);
        $this->as($poor)->postJson('/api/v1/driver/availability', ['available' => true])->assertStatus(403);
    }

    public function test_settlement_gives_whole_fee_to_roteh_and_keeps_cash_fee_on_statement(): void
    {
        $d = $this->package(['status' => 'in_progress', 'driver_id' => $this->staff->id]);

        $this->as($this->staff)->postJson("/api/v1/deliveries/{$d->id}/complete")->assertOk();

        $this->assertSame(0, (int) $this->staff->fresh()->wallet_balance);         // salary — nothing per trip
        $rec = TransactionRecord::where('reference_type', Delivery::class)->where('reference_id', $d->id)->firstOrFail();
        $this->assertSame([5000, 5000, 0, 0], [(int) $rec->gross_amount, (int) $rec->platform_fee, (int) $rec->company_share, (int) $rec->driver_earning]);
        $this->assertSame('unpaid', $d->fresh()->payment_status);                  // partner still owes the fee

        // The daily statement takes the fee from the COD: ROTEH pays 30,000 − 5,000.
        $date = now(\App\Services\PartnerStatementService::tz())->toDateString();
        $this->as($this->partner)->getJson("/api/v1/partner/statements?date={$date}")
            ->assertJsonPath('data.successful.0.fee_paid_by', 'partner_from_cod')
            ->assertJsonPath('data.summary.roteh_pays_partner', 25000);
    }

    public function test_receiver_pays_only_cod_and_driver_cash_handover(): void
    {
        // Even if "recipient" is sent, the fee is charged to the partner — the driver collects only the COD.
        $d = $this->package(['status' => 'in_progress', 'driver_id' => $this->staff->id, 'payment_by' => 'recipient']);
        $this->as($this->staff)->postJson("/api/v1/deliveries/{$d->id}/complete")->assertOk();
        $this->assertSame(['sender', 'unpaid'], [$d->fresh()->payment_by, $d->fresh()->payment_status]);

        $back = $this->package(['status' => 'in_progress', 'driver_id' => $this->staff->id]);
        $this->as($this->staff)->postJson("/api/v1/deliveries/{$back->id}/fail", ['reason' => 'cannot_contact'])->assertOk();

        $rows = app(\App\Services\PartnerStatementService::class)->driverCash(...\App\Services\PartnerStatementService::dayRange(null));
        $this->assertCount(1, $rows);
        $this->assertSame([1, 30000, 0, 30000, 1], [$rows[0]['delivered'], $rows[0]['cod_collected'], $rows[0]['cash_fees'], $rows[0]['cash_to_hand_over'], $rows[0]['returning']]);

        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->get(route('admin.partner-statements.drivers'))->assertOk()
            ->assertSee('ប្រាក់ដែលអ្នកបើកបរត្រូវប្រគល់')->assertSee('30,000')->assertSee($this->staff->name);
    }

    public function test_admin_cannot_assign_partner_package_to_non_staff(): void
    {
        $d = $this->package(['status' => 'created']);
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->post(route('admin.deliveries.assign', $d), ['driver_id' => $this->owner->id])->assertSessionHas('error');
        $this->assertNull($d->fresh()->driver_id);

        $this->actingAs($admin)->post(route('admin.deliveries.assign', $d), ['driver_id' => $this->staff->id]);
        $this->assertSame($this->staff->id, $d->fresh()->driver_id);
    }

    public function test_normal_deliveries_still_pay_owner_drivers(): void
    {
        $d = Delivery::create([
            'sender_id' => $this->partner->id, 'service_type' => 'delivery', 'sender_name' => 'X', 'recipient_name' => 'Y',
            'recipient_phone' => '0961234567', 'pickup_address' => 'A', 'dropoff_address' => 'B', 'fee' => 10000,
            'payment_by' => 'sender', 'payment_method' => 'cash', 'payment_status' => 'unpaid',
            'status' => 'in_progress', 'driver_id' => $this->owner->id, 'share_token' => Str::random(32),
        ]);
        $this->as($this->owner)->postJson("/api/v1/deliveries/{$d->id}/complete")->assertOk();
        $rec = TransactionRecord::where('reference_type', Delivery::class)->where('reference_id', $d->id)->firstOrFail();
        $this->assertGreaterThan(0, (int) $rec->driver_earning);
    }
}

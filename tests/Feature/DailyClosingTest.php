<?php

namespace Tests\Feature;

use App\Models\DailyClosing;
use App\Models\Delivery;
use App\Models\DeliveryCollection;
use App\Models\PartnerProfile;
use App\Models\User;
use App\Services\PartnerStatementService as PS;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

/** Scan pickup → scan handover → verify mixed payment → close driver → close partner → finish day. */
class DailyClosingTest extends TestCase
{
    use RefreshDatabase;

    private User $partner;
    private User $staff;
    private User $admin;
    private string $today;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        config(['ride.khr_per_usd' => 4100]);
        $tok = fn () => ['api_token' => 't-' . uniqid(), 'token_expires_at' => now()->addHour()];
        $this->partner = User::factory()->create(['role' => 'partner'] + $tok());
        PartnerProfile::create(['user_id' => $this->partner->id, 'business_name' => 'Shop', 'phone' => '012345678', 'status' => 'approved']);
        $this->staff = User::factory()->create(['role' => 'driver', 'driver_type' => 'staff', 'available' => true, 'wallet_balance' => 0] + $tok());
        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->today = now(PS::tz())->toDateString();
    }

    private function as(User $u)
    {
        return $this->withHeaders(['Authorization' => 'Bearer ' . $u->api_token]);
    }

    private function package(int $cod, int $fee = 15000): Delivery
    {
        return Delivery::create([
            'partner_id' => $this->partner->id, 'sender_id' => $this->partner->id, 'service_type' => 'delivery',
            'sender_name' => 'Shop', 'recipient_name' => 'Dara', 'recipient_phone' => '0961234567',
            'pickup_address' => 'TK', 'dropoff_address' => 'BKK1', 'package_size' => 'small',
            'fee' => $fee, 'package_amount' => $cod, 'payment_method' => 'cash', 'payment_status' => 'unpaid',
            'status' => 'accepted', 'driver_id' => $this->staff->id, 'confirmed_at' => now(),
            'qr_token' => Str::random(32), 'share_token' => Str::random(32),
        ]);
    }

    private function scan(Delivery $d)
    {
        return $this->as($this->staff)->postJson('/api/v1/deliveries/scan-qr', ['qr_token' => 'AUTORIDE:DELIVERY:' . $d->qr_token]);
    }

    public function test_full_daily_process(): void
    {
        $d = $this->package(100000);

        // Pickup must be scanned — the old "picked up" button is refused.
        $this->as($this->staff)->postJson("/api/v1/deliveries/{$d->id}/start")->assertStatus(422)->assertJsonPath('code', 'scan_required');
        $this->scan($d)->assertOk()->assertJsonPath('data.action', 'pickup_confirmed');
        $this->assertSame('picked_up', $d->fresh()->status);

        // Can't complete or verify before the handover scan.
        $this->as($this->staff)->postJson("/api/v1/deliveries/{$d->id}/complete")->assertStatus(422)->assertJsonPath('code', 'verify_payment_required');
        $this->as($this->staff)->postJson("/api/v1/deliveries/{$d->id}/collect", ['cash_khr' => 100000])->assertStatus(422)->assertJsonPath('code', 'scan_required');

        // Handover scan → verify payment (never auto-completes).
        $this->scan($d)->assertOk()->assertJsonPath('data.action', 'verify_payment')->assertJsonPath('data.expected_khr', 100000);
        $this->assertSame('picked_up', $d->fresh()->status);

        // Short without a note → refused; bank without bank name → refused.
        $this->as($this->staff)->postJson("/api/v1/deliveries/{$d->id}/collect", ['cash_khr' => 90000])->assertStatus(422)->assertJsonPath('code', 'note_required');
        $this->as($this->staff)->postJson("/api/v1/deliveries/{$d->id}/collect", ['bank_khr' => 100000])->assertStatus(422)->assertJsonPath('code', 'bank_required');

        // Mixed: 50,000 ៛ cash + $10 cash (41,000 ៛) + 9,000 ៛ ABA = 100,000 ៛.
        $this->as($this->staff)->postJson("/api/v1/deliveries/{$d->id}/collect", [
            'cash_khr' => 50000, 'cash_usd' => 10, 'bank_khr' => 9000, 'bank_name' => 'ABA', 'bank_ref' => 'TX123',
        ])->assertOk()->assertJsonPath('data.collection.total_khr', 100000)->assertJsonPath('data.collection.difference_khr', 0);
        $this->assertSame('completed', $d->fresh()->status);

        // Statement shows how it was paid, and the partner gets 100,000 − 15,000.
        $this->as($this->partner)->getJson("/api/v1/partner/statements?date={$this->today}")
            ->assertJsonPath('data.successful.0.paid_via', 'Cash ៛ + Cash $ + Bank (ABA)')
            ->assertJsonPath('data.summary.roteh_pays_partner', 85000)
            ->assertJsonPath('data.closing', null);

        // Admin page lists the driver's expected cash.
        $this->actingAs($this->admin)->get(route('admin.daily-closing', ['date' => $this->today]))
            ->assertOk()->assertSee($this->staff->name)->assertSee('50,000')->assertSee('$10.00');

        // Close driver: bank must be checked; a cash difference needs a note.
        $this->actingAs($this->admin)->post(route('admin.daily-closing.driver.close', $this->staff),
            ['date' => $this->today, 'received_cash_khr' => 50000, 'received_cash_usd' => 10])->assertSessionHasErrors('bank_verified');
        $this->actingAs($this->admin)->post(route('admin.daily-closing.driver.close', $this->staff),
            ['date' => $this->today, 'received_cash_khr' => 49000, 'received_cash_usd' => 10, 'bank_verified' => 1])->assertSessionHasErrors('note');
        $this->actingAs($this->admin)->post(route('admin.daily-closing.driver.close', $this->staff),
            ['date' => $this->today, 'received_cash_khr' => 50000, 'received_cash_usd' => 10, 'bank_verified' => 1])->assertSessionHas('success');
        $this->assertNotNull(DeliveryCollection::first()->driver_closing_id);

        // Finish is blocked while the partner is open.
        $this->actingAs($this->admin)->post(route('admin.daily-closing.finish'), ['date' => $this->today])->assertSessionHasErrors('day');

        // Close partner → statement shows it closed.
        $this->actingAs($this->admin)->post(route('admin.daily-closing.partner.close', $this->partner),
            ['date' => $this->today, 'settle_method' => 'bank', 'settle_ref' => 'ABA-555'])->assertSessionHas('success');
        $this->as($this->partner)->getJson("/api/v1/partner/statements?date={$this->today}")
            ->assertJsonPath('data.closing.settle_method', 'bank')->assertJsonPath('data.closing.net_to_partner', 85000);

        // Finish → locked; reopening is refused.
        $this->actingAs($this->admin)->post(route('admin.daily-closing.finish'), ['date' => $this->today, 'note' => 'OK'])->assertSessionHas('success');
        $this->assertTrue(DailyClosing::isClosed($this->today));
        $this->actingAs($this->admin)->post(route('admin.daily-closing.driver.reopen', $this->staff), ['date' => $this->today])->assertSessionHasErrors('day');
        $this->actingAs($this->admin)->get(route('admin.daily-closing', ['date' => $this->today]))->assertOk()->assertSee('Day finished');
    }

    public function test_short_payment_with_note_and_no_cod_package(): void
    {
        $short = $this->package(100000);
        $this->scan($short); $this->scan($short);
        $this->as($this->staff)->postJson("/api/v1/deliveries/{$short->id}/collect", ['cash_khr' => 95000, 'note' => 'Receiver had only 95k'])
            ->assertOk()->assertJsonPath('data.collection.difference_khr', -5000);

        $free = $this->package(0);
        $this->scan($free); $this->scan($free);
        $this->as($this->staff)->postJson("/api/v1/deliveries/{$free->id}/collect", [])->assertOk();
        $this->assertSame('completed', $free->fresh()->status);

        $e = app(\App\Services\DailyClosingService::class)->driverExpected($this->staff->id, $this->today);
        $this->assertSame([2, 95000, -5000], [$e['packages'], $e['cash_khr'], $e['difference_khr']]);
    }

    public function test_normal_deliveries_keep_old_flow(): void
    {
        $owner = User::factory()->create(['role' => 'driver', 'driver_type' => 'owner', 'wallet_balance' => 100000,
            'api_token' => 'o-' . uniqid(), 'token_expires_at' => now()->addHour()]);
        $sender = User::factory()->create(['role' => 'passenger']);
        $d = Delivery::create(['sender_id' => $sender->id, 'service_type' => 'delivery', 'sender_name' => 'S', 'recipient_name' => 'R',
            'recipient_phone' => '0961234567', 'pickup_address' => 'A', 'dropoff_address' => 'B', 'fee' => 8000,
            'payment_by' => 'sender', 'payment_method' => 'cash', 'payment_status' => 'unpaid',
            'status' => 'accepted', 'driver_id' => $owner->id, 'share_token' => Str::random(32)]);

        $this->as($owner)->postJson("/api/v1/deliveries/{$d->id}/start")->assertOk();
        $this->as($owner)->postJson("/api/v1/deliveries/{$d->id}/complete")->assertOk();
        $this->assertSame('completed', $d->fresh()->status);
    }
}

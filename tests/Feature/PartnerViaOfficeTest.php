<?php

namespace Tests\Feature;

use App\Models\Delivery;
use App\Models\User;
use App\Services\StaffAssignmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

/** Pickup driver: shop → office · office assigns · delivery driver: office → customer. */
class PartnerViaOfficeTest extends TestCase
{
    use RefreshDatabase;

    public function test_two_leg_flow_through_the_office(): void
    {
        Queue::fake();
        $tok = fn () => ['api_token' => 't-' . uniqid(), 'token_expires_at' => now()->addHour()];
        $partner = User::factory()->create(['role' => 'partner'] + $tok());
        $pickup  = User::factory()->create(['role' => 'driver', 'driver_type' => 'staff', 'wallet_balance' => 0] + $tok());
        $courier = User::factory()->create(['role' => 'driver', 'driver_type' => 'staff', 'wallet_balance' => 0] + $tok());
        $as = fn (User $u) => $this->withHeaders(['Authorization' => 'Bearer ' . $u->api_token]);

        $d = Delivery::create([
            'partner_id' => $partner->id, 'sender_id' => $partner->id, 'service_type' => 'delivery',
            'sender_name' => 'Shop', 'recipient_name' => 'Dara', 'recipient_phone' => '0961234567',
            'pickup_address' => 'Shop TK', 'pickup_lat' => 11.58, 'pickup_lng' => 104.89,
            'dropoff_address' => 'BKK1', 'dropoff_lat' => 11.55, 'dropoff_lng' => 104.92,
            'fee' => 5000, 'package_amount' => 30000, 'payment_method' => 'cash', 'payment_status' => 'unpaid',
            'status' => 'requested', 'qr_token' => Str::random(32), 'share_token' => Str::random(32),
        ]);
        $svc = app(StaffAssignmentService::class);
        $scan = fn (User $u) => $as($u)->postJson('/api/v1/deliveries/scan-qr', ['qr_token' => $d->qr_token]);

        // ── Pickup leg: shop → office ───────────────────────────────────────
        $svc->assign($d->fresh(), $pickup);
        $this->assertSame('pickup', $d->fresh()->leg);
        $as($pickup)->getJson('/api/v1/driver/staff/packages')
            ->assertJsonPath('data.active.0.leg', 'pickup')
            ->assertJsonPath('data.active.0.collect_from.address', 'Shop TK');

        $scan($pickup)->assertOk()->assertJsonPath('data.action', 'pickup_confirmed')->assertJsonPath('data.leg', 'pickup');
        $scan($pickup)->assertOk()->assertJsonPath('data.action', 'office_received');     // scanned at the office
        $this->assertSame('at_office', $d->fresh()->status);
        $as($partner)->getJson("/api/v1/partner/packages/{$d->id}")->assertJsonPath('data.package.status', 'at_office');
        $as($pickup)->getJson('/api/v1/driver/staff/packages')
            ->assertJsonCount(0, 'data.active')
            ->assertJsonPath('data.done.0.brought_to_office', true);

        // ── Office assigns a delivery driver: office → customer ─────────────
        $svc->assign($d->fresh(), $courier);
        $this->assertSame(['delivery', $courier->id], [$d->fresh()->leg, $d->fresh()->driver_id]);
        $this->assertSame($pickup->id, $d->fresh()->pickup_driver_id);
        $as($courier)->getJson('/api/v1/driver/staff/packages')
            ->assertJsonPath('data.active.0.leg', 'delivery')
            ->assertJsonPath('data.active.0.destination.address', 'BKK1');

        // No scan at the office: assigned = handed over, already on the way.
        $this->assertSame('in_transit', $d->fresh()->status);
        $this->assertNotNull($d->fresh()->office_dispatched_at);
        $as($partner)->getJson("/api/v1/partner/packages/{$d->id}")->assertJsonPath('data.package.status', 'in_transit');

        // At the receiver: scan → confirm the money → completed.
        $scan($courier)->assertOk()->assertJsonPath('data.action', 'verify_payment')->assertJsonPath('data.expected_khr', 30000);
        $as($courier)->postJson("/api/v1/deliveries/{$d->id}/collect", ['cash_khr' => 30000])->assertOk();

        $this->assertSame('completed', $d->fresh()->status);
        $this->assertSame($courier->id, $d->fresh()->collection->driver_id);   // COD is the delivery driver's cash
    }

    public function test_admin_assigns_delivery_driver_for_package_at_office(): void
    {
        Queue::fake();
        $partner = User::factory()->create(['role' => 'partner']);
        $courier = User::factory()->create(['role' => 'driver', 'driver_type' => 'staff', 'name' => 'Courier Dara',
            'api_token' => 'c-' . uniqid(), 'token_expires_at' => now()->addHour()]);
        $admin = User::factory()->create(['role' => 'admin']);
        $d = Delivery::create([
            'partner_id' => $partner->id, 'sender_id' => $partner->id, 'service_type' => 'delivery',
            'sender_name' => 'Shop', 'recipient_name' => 'Receiver Sok', 'recipient_phone' => '0961234567',
            'pickup_address' => 'Shop', 'dropoff_address' => 'BKK1', 'fee' => 5000, 'package_amount' => 0,
            'payment_method' => 'cash', 'payment_status' => 'unpaid', 'status' => 'at_office', 'leg' => 'pickup',
            'pickup_scanned_at' => now(), 'office_received_at' => now(),
            'qr_token' => Str::random(32), 'share_token' => Str::random(32),
        ]);

        // The office board lists it under "at office" with the staff driver to pick.
        $this->actingAs($admin)->get(route('admin.partner-deliveries', ['stage' => 'at_office']))
            ->assertOk()->assertSee('Receiver Sok')->assertSee('Courier Dara')->assertSee('name="ids[]"', false);

        // Assign → delivery leg, on the courier's list, notified.
        $this->actingAs($admin)->post(route('admin.partner-packages.assign'), ['ids' => [$d->id], 'driver_id' => $courier->id])
            ->assertSessionHas('success');
        $d->refresh();
        $this->assertSame(['delivery', 'in_transit', $courier->id], [$d->leg, $d->status, $d->driver_id]);
        $this->withHeaders(['Authorization' => 'Bearer ' . $courier->api_token])
            ->getJson('/api/v1/driver/staff/packages')->assertJsonPath('data.active.0.id', $d->id)
            ->assertJsonPath('data.active.0.leg', 'delivery');
    }

    public function test_delivery_leg_can_be_reassigned_until_handover(): void
    {
        Queue::fake();
        $partner = User::factory()->create(['role' => 'partner']);
        $a = User::factory()->create(['role' => 'driver', 'driver_type' => 'staff', 'api_token' => 'a-' . uniqid(), 'token_expires_at' => now()->addHour()]);
        $b = User::factory()->create(['role' => 'driver', 'driver_type' => 'staff']);
        $d = Delivery::create([
            'partner_id' => $partner->id, 'sender_id' => $partner->id, 'service_type' => 'delivery',
            'sender_name' => 'Shop', 'recipient_name' => 'R', 'recipient_phone' => '0961234567',
            'pickup_address' => 'Shop', 'dropoff_address' => 'BKK1', 'fee' => 5000, 'package_amount' => 0,
            'payment_method' => 'cash', 'payment_status' => 'unpaid', 'status' => 'at_office', 'leg' => 'pickup',
            'pickup_scanned_at' => now(), 'office_received_at' => now(),
            'qr_token' => Str::random(32), 'share_token' => Str::random(32),
        ]);
        $svc = app(StaffAssignmentService::class);

        $svc->assign($d->fresh(), $a);
        $svc->assign($d->fresh(), $b);                                      // reassign while on the way
        $this->assertSame($b->id, $d->fresh()->driver_id);

        $this->withHeaders(['Authorization' => 'Bearer ' . $a->api_token])
            ->postJson('/api/v1/deliveries/scan-qr', ['qr_token' => $d->qr_token])->assertStatus(403);   // no longer A's

        $d->update(['delivery_scanned_at' => now()]);                       // B reached the receiver
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $svc->assign($d->fresh(), $a);
    }
}

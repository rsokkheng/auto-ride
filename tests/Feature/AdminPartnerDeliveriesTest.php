<?php

namespace Tests\Feature;

use App\Models\Delivery;
use App\Models\PartnerProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

/** Partner packages: shop → (pickup driver) → ROTEH office → (delivery driver) → customer. */
class AdminPartnerDeliveriesTest extends TestCase
{
    use RefreshDatabase;

    private User $partner;
    private User $pickupStaff;
    private User $deliveryStaff;
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        config(['ride.khr_per_usd' => 4100, 'ride.office.address' => 'ROTEH HQ, Toul Kork']);
        $tok = fn () => ['api_token' => 't-' . uniqid(), 'token_expires_at' => now()->addHour()];
        $this->partner = User::factory()->create(['role' => 'partner', 'name' => 'Dara'] + $tok());
        PartnerProfile::create(['user_id' => $this->partner->id, 'business_name' => 'Dara Shop', 'phone' => '012345678', 'status' => 'approved']);
        $this->pickupStaff   = User::factory()->create(['role' => 'driver', 'driver_type' => 'staff', 'name' => 'Pickup Pich'] + $tok());
        $this->deliveryStaff = User::factory()->create(['role' => 'driver', 'driver_type' => 'staff', 'name' => 'Delivery Dina'] + $tok());
        $this->admin = User::factory()->create(['role' => 'admin']);
    }

    /** $partner = false → an ordinary customer delivery. */
    private function package(string $recipient = 'Sokha', bool $partner = true): Delivery
    {
        return Delivery::create([
            'partner_id' => $partner ? $this->partner->id : null,
            'sender_id' => $partner ? $this->partner->id : User::factory()->create()->id, 'service_type' => 'delivery',
            'sender_name' => 'Dara Shop', 'recipient_name' => $recipient, 'recipient_phone' => '0961234567',
            'pickup_address' => 'Shop, BKK1', 'dropoff_address' => 'Customer, Sen Sok', 'fee' => 5000, 'package_amount' => 20000,
            'payment_method' => 'cash', 'payment_status' => 'unpaid', 'status' => 'created',
            'qr_token' => Str::random(32), 'share_token' => Str::random(32),
        ]);
    }

    private function scan(User $driver, Delivery $d)
    {
        return $this->withToken($driver->api_token)->postJson('/api/v1/deliveries/scan-qr', ['qr_token' => $d->qr_token]);
    }

    private function assign(Delivery $d, User $driver)
    {
        return $this->actingAs($this->admin)->post(route('admin.partner-packages.assign'), ['ids' => [$d->id], 'driver_id' => $driver->id]);
    }

    private function partnerStatus(Delivery $d): string
    {
        return $this->withToken($this->partner->api_token)->getJson("/api/v1/partner/packages/{$d->id}")->assertOk()->json('data.package.status');
    }

    public function test_partner_orders_have_their_own_board_and_are_left_out_of_deliveries(): void
    {
        $this->package('Customer Order', false);
        $this->package('Partner Order');

        $this->actingAs($this->admin)->get(route('admin.deliveries'))->assertOk()
            ->assertSee('Customer Order')->assertDontSee('Partner Order');

        $this->get(route('admin.partner-deliveries'))->assertOk()
            ->assertSee('Partner Deliveries')->assertSee('Partner Order')->assertDontSee('Customer Order')
            ->assertSee('Dara Shop')->assertSee('ROTEH HQ, Toul Kork')
            ->assertViewHas('counts', fn ($c) => $c['new'] === 1)
            ->assertViewHas('partners', fn ($p) => $p->all() === [$this->partner->id => 'Dara Shop']);

        $this->get(route('admin.partner-packages', ['stage' => 'new']))->assertRedirect(route('admin.partner-deliveries', ['stage' => 'new']));
    }

    public function test_drivers_finish_both_legs_by_qr_scan(): void
    {
        $d = $this->package();

        // Leg 1 — pickup driver: shop → office.
        $this->assign($d, $this->pickupStaff)->assertSessionHas('success');
        $this->assertSame(['accepted', 'pickup', $this->pickupStaff->id], [$d->fresh()->status, $d->fresh()->leg, $d->fresh()->pickup_driver_id]);
        $this->assertSame('driver_assigned', $this->partnerStatus($d));
        $this->assertSame('pickup', $d->fresh()->workflowStage());

        $this->scan($this->pickupStaff, $d)->assertOk()
            ->assertJsonPath('data.action', 'pickup_confirmed')->assertJsonPath('data.destination.address', 'ROTEH HQ, Toul Kork');
        $this->withToken($this->pickupStaff->api_token)->postJson("/api/v1/deliveries/{$d->id}/collect", ['cash_khr' => 20000])
            ->assertStatus(422)->assertJsonPath('code', 'pickup_leg');
        $this->withToken($this->pickupStaff->api_token)->postJson("/api/v1/deliveries/{$d->id}/fail", ['reason' => 'wrong_address'])
            ->assertStatus(422)->assertJsonPath('code', 'pickup_leg');

        $this->scan($this->pickupStaff, $d)->assertOk()->assertJsonPath('data.action', 'office_received');
        $this->assertSame('at_office', $d->fresh()->status);
        $this->assertSame('at_office', $this->partnerStatus($d));
        $this->withToken($this->partner->api_token)->postJson("/api/v1/partner/packages/{$d->id}/cancel")->assertStatus(422);
        $filtered = fn (string $status) => collect($this->withToken($this->partner->api_token)
            ->getJson("/api/v1/partner/packages?status={$status}")->assertOk()->json('data.packages'))->pluck('id')->all();
        $this->assertSame([$d->id], $filtered('at_office'));
        $this->assertSame([], $filtered('driver_assigned'));
        $done = $this->withToken($this->pickupStaff->api_token)->getJson('/api/v1/driver/staff/packages')->assertOk();
        $this->assertSame([$d->id], collect($done->json('data.done'))->pluck('id')->all());
        $this->assertTrue($done->json('data.done.0.brought_to_office'));

        // Leg 2 — delivery driver: office → customer.
        $this->assign($d, $this->deliveryStaff)->assertSessionHas('success');
        $this->assertSame(['accepted', 'delivery', $this->deliveryStaff->id], [$d->fresh()->status, $d->fresh()->leg, $d->fresh()->driver_id]);
        $this->assertSame('at_office', $this->partnerStatus($d));
        $list = $this->withToken($this->deliveryStaff->api_token)->getJson('/api/v1/driver/staff/packages')->assertOk();
        $this->assertSame('ROTEH HQ, Toul Kork', $list->json('data.active.0.collect_from.address'));
        $this->assertSame('Customer, Sen Sok', $list->json('data.active.0.destination.address'));

        $this->scan($this->deliveryStaff, $d)->assertOk()->assertJsonPath('data.action', 'pickup_confirmed')->assertJsonPath('data.leg', 'delivery');
        $this->assertSame('in_transit', $this->partnerStatus($d));
        $this->scan($this->deliveryStaff, $d)->assertOk()->assertJsonPath('data.action', 'verify_payment');
        $this->withToken($this->deliveryStaff->api_token)->postJson("/api/v1/deliveries/{$d->id}/collect", ['cash_khr' => 20000])->assertOk();

        $d->refresh();
        $this->assertSame('completed', $d->status);
        $this->assertSame($this->pickupStaff->id, $d->pickup_driver_id);
        $this->assertSame($this->deliveryStaff->id, $d->collection->driver_id);
    }

    public function test_admin_marks_at_office_and_completes_with_the_money_received(): void
    {
        $d = $this->package();
        $this->assign($d, $this->pickupStaff);

        // Driver forgot to scan: admin marks it received at the office.
        $this->actingAs($this->admin)->post(route('admin.partner-deliveries.office', $d))->assertSessionHas('success');
        $this->assertSame('at_office', $d->fresh()->status);
        $this->assertSame($this->admin->id, $d->fresh()->office_received_by);
        $this->post(route('admin.partner-deliveries.office', $d))->assertSessionHas('error');

        // Not out for delivery yet → can't complete.
        $this->post(route('admin.partner-deliveries.complete', $d), ['cash_khr' => 20000])->assertSessionHas('error');

        $this->assign($d, $this->deliveryStaff);
        $this->get(route('admin.partner-deliveries', ['stage' => 'at_office']))->assertOk()->assertSee('Completed');

        // Short payment needs a note.
        $this->post(route('admin.partner-deliveries.complete', $d), ['cash_khr' => 15000])->assertSessionHas('error');
        $this->assertNull($d->fresh()->collection);

        $this->post(route('admin.partner-deliveries.complete', $d), ['cash_khr' => 15000, 'cash_usd' => 1, 'note' => 'Paid part in dollars'])
            ->assertSessionHas('success');

        $d->refresh();
        $this->assertSame('completed', $d->status);
        $this->assertSame('done', $d->workflowStage());
        $this->assertSame($this->deliveryStaff->id, $d->collection->driver_id);
        $this->assertSame(19100, $d->collection->total_khr);
    }

    public function test_editing_a_partner_package_keeps_its_status_and_driver(): void
    {
        $d = $this->package('Old Name');
        $this->assign($d, $this->pickupStaff);

        $this->actingAs($this->admin)->get(route('admin.partner-deliveries'))->assertOk()->assertSee('id="editModal"', false);

        $this->from(route('admin.partner-deliveries'))->put(route('admin.deliveries.update', $d), [
            'service_type' => 'moving', 'status' => 'completed',
            'sender_id' => $this->partner->id, 'sender_name' => 'Dara Shop', 'recipient_name' => 'New Name',
            'recipient_phone' => '0961234567', 'pickup_address' => 'Shop, BKK1', 'dropoff_address' => 'Customer, Sen Sok', 'fee' => 5000,
        ])->assertRedirect(route('admin.partner-deliveries'));

        $d->refresh();
        $this->assertSame(['New Name', 'delivery', 'accepted', $this->pickupStaff->id], [$d->recipient_name, $d->service_type, $d->status, $d->driver_id]);
    }
}

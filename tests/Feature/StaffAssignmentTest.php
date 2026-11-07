<?php

namespace Tests\Feature;

use App\Models\Delivery;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

/** ROTEH staff: no deposit, no online/offline — admin assigns Partner packages. */
class StaffAssignmentTest extends TestCase
{
    use RefreshDatabase;

    private User $partner;
    private User $staffA;
    private User $staffB;
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        $tok = fn () => ['api_token' => 't-' . uniqid(), 'token_expires_at' => now()->addHour()];
        $this->partner = User::factory()->create(['role' => 'partner']);
        // Offline, empty wallet — still gets packages.
        $this->staffA = User::factory()->create(['role' => 'driver', 'driver_type' => 'staff', 'available' => false, 'wallet_balance' => 0] + $tok());
        $this->staffB = User::factory()->create(['role' => 'driver', 'driver_type' => 'staff', 'available' => false, 'wallet_balance' => 0] + $tok());
        $this->admin  = User::factory()->create(['role' => 'admin']);
    }

    private function package(string $status = 'created'): Delivery
    {
        return Delivery::create([
            'partner_id' => $this->partner->id, 'sender_id' => $this->partner->id, 'service_type' => 'delivery',
            'sender_name' => 'Shop', 'recipient_name' => 'R', 'recipient_phone' => '0961234567',
            'pickup_address' => 'TK', 'dropoff_address' => 'BKK1', 'fee' => 5000, 'package_amount' => 20000,
            'payment_method' => 'cash', 'payment_status' => 'unpaid', 'status' => $status,
            'qr_token' => Str::random(32), 'share_token' => Str::random(32),
        ]);
    }

    private function as(User $u)
    {
        return $this->withHeaders(['Authorization' => 'Bearer ' . $u->api_token]);
    }

    public function test_status_needs_no_deposit_for_staff(): void
    {
        $this->as($this->staffA)->getJson('/api/v1/driver/status')
            ->assertJsonPath('data.can_go_online', true)->assertJsonPath('data.is_staff', true);
    }

    public function test_bulk_assign_from_pending_or_confirmed_and_scan_works_while_offline(): void
    {
        $p1 = $this->package('created');
        $p2 = $this->package('requested');

        $this->actingAs($this->admin)->post(route('admin.partner-packages.assign'),
            ['ids' => [$p1->id, $p2->id], 'driver_id' => $this->staffA->id])->assertSessionHas('success');

        foreach ([$p1, $p2] as $p) {
            $this->assertSame([$this->staffA->id, 'accepted'], [$p->fresh()->driver_id, $p->fresh()->status]);
            $this->assertNotNull($p->fresh()->confirmed_at);
        }
        $this->as($this->staffA)->getJson('/api/v1/driver/staff/packages')->assertJsonCount(2, 'data.active');

        // Offline staff can still scan — several packages at once.
        foreach ([$p1, $p2] as $p) {
            $this->as($this->staffA)->postJson('/api/v1/deliveries/scan-qr', ['qr_token' => $p->qr_token])
                ->assertOk()->assertJsonPath('data.action', 'pickup_confirmed');
        }
        $this->assertSame(['picked_up', 'picked_up'], [$p1->fresh()->status, $p2->fresh()->status]);
    }

    public function test_reassign_before_pickup_only_and_staff_only(): void
    {
        $p = $this->package('requested');
        $svc = app(\App\Services\StaffAssignmentService::class);

        $svc->assign($p, $this->staffA);
        $svc->assign($p->fresh(), $this->staffB);                    // reassign before pickup
        $this->assertSame($this->staffB->id, $p->fresh()->driver_id);
        $this->as($this->staffA)->getJson('/api/v1/driver/staff/packages')->assertJsonCount(0, 'data.active');

        $this->as($this->staffB)->postJson('/api/v1/deliveries/scan-qr', ['qr_token' => $p->qr_token])->assertOk();
        $this->actingAs($this->admin)->post(route('admin.partner-packages.assign'),
            ['ids' => [$p->id], 'driver_id' => $this->staffA->id])->assertSessionHas('error');   // already picked up
        $this->assertSame($this->staffB->id, $p->fresh()->driver_id);

        $owner = User::factory()->create(['role' => 'driver', 'driver_type' => 'owner']);
        $q = $this->package('requested');
        $this->actingAs($this->admin)->post(route('admin.partner-packages.assign'),
            ['ids' => [$q->id], 'driver_id' => $owner->id])->assertSessionHas('error');
        $this->assertNull($q->fresh()->driver_id);
    }

    public function test_confirm_no_longer_broadcasts(): void
    {
        $p = $this->package('created');
        $this->actingAs($this->admin)->post(route('admin.partner-packages.confirm', $p))->assertSessionHas('success');
        $this->assertSame(['requested', null], [$p->fresh()->status, $p->fresh()->driver_id]);
        $this->assertNull(\Illuminate\Support\Facades\Cache::get(\App\Http\Controllers\Api\DeliveryController::offeredCacheKey($p->id)));
    }

    public function test_admin_page_shows_staff_and_checkboxes(): void
    {
        $this->package('created');
        $this->actingAs($this->admin)->get(route('admin.partner-packages'))->assertOk()
            ->assertSee('Assign to ROTEH staff')->assertSee($this->staffA->name)->assertSee('name="ids[]"', false);
    }
}

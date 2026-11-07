<?php

namespace Tests\Feature;

use App\Models\Delivery;
use App\Models\PartnerContract;
use App\Models\PartnerProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Partner Package Delivery inside the Passenger app. */
class PartnerPackageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
    }

    private function user(array $attrs = []): User
    {
        return User::factory()->create($attrs + [
            'role' => 'passenger', 'phone' => '012345678', 'wallet_balance' => 0,
            'api_token' => 't-' . uniqid(), 'token_expires_at' => now()->addHour(),
        ]);
    }

    protected function as(User $u): static
    {
        return $this->withHeaders(['Authorization' => 'Bearer ' . $u->api_token]);
    }

    private function partner(): User
    {
        $u = $this->user(['role' => 'partner']);
        PartnerProfile::create(['user_id' => $u->id, 'business_name' => 'Sokha Shop', 'phone' => '012345678', 'status' => 'approved']);
        return $u;
    }

    private function packagePayload(array $over = []): array
    {
        return $over + [
            'sender_name' => 'Sokha Shop', 'sender_phone' => '012345678',
            'recipient_name' => 'Dara', 'recipient_phone' => '0961234567',
            'pickup_address' => 'Toul Kork', 'pickup_lat' => 11.58, 'pickup_lng' => 104.89,
            'dropoff_address' => 'BKK1', 'dropoff_lat' => 11.55, 'dropoff_lng' => 104.92,
            'package_type' => 'clothing', 'package_details' => '2 shirts', 'package_weight' => 1.5,
            'package_size' => 'large', 'service_option' => 'normal',
            'package_amount' => 40000, 'payment_by' => 'recipient',
            'fee' => 1, // ignored — the server prices it
        ];
    }

    public function test_passenger_registers_and_cannot_send_until_approved(): void
    {
        $u = $this->user();

        $this->as($u)->getJson('/api/v1/partner/profile')->assertOk()->assertJsonPath('data.status', 'none');
        $this->as($u)->postJson('/api/v1/partner/register', [
            'business_name' => 'Sokha Shop', 'phone' => '012345678', 'address' => 'Toul Kork, Phnom Penh',
        ])->assertStatus(201)->assertJsonPath('data.status', 'pending')->assertJsonPath('data.is_partner', false);

        $this->as($u)->postJson('/api/v1/partner/packages', $this->packagePayload())
            ->assertStatus(403)->assertJsonPath('code', 'not_partner');
        $this->assertSame('passenger', $u->fresh()->role);
    }

    public function test_admin_approval_turns_the_account_into_a_partner(): void
    {
        $u = $this->user();
        $profile = PartnerProfile::create(['user_id' => $u->id, 'business_name' => 'Sokha Shop', 'phone' => '012345678']);
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->post(route('admin.partners.approve', $profile))->assertRedirect();

        $this->assertSame('partner', $u->fresh()->role);
        $this->as($u)->getJson('/api/v1/partner/profile')->assertJsonPath('data.is_partner', true);
    }

    public function test_partner_creates_package_priced_by_contract_and_pending_until_confirmed(): void
    {
        Storage::fake('public');
        $p = $this->partner();
        PartnerContract::create(['partner_id' => $p->id, 'normal_fee' => 4000, 'express_fee' => 8000,
            'surcharge_large' => 2000, 'surcharge_extra_large' => 3000, 'is_active' => true]);

        $this->as($p)->getJson('/api/v1/partner/packages/quote?package_size=large')->assertJsonPath('data.fee', 6000);

        $res = $this->as($p)->post('/api/v1/partner/packages',
            $this->packagePayload(['photo' => UploadedFile::fake()->image('box.jpg')]), ['Accept' => 'application/json'])
            ->assertStatus(201)
            ->assertJsonPath('data.package.fee', 6000)
            ->assertJsonPath('data.package.status', 'pending')
            ->assertJsonPath('data.package.package_amount', 40000)
            ->assertJsonPath('data.package.timeline.0.status', 'pending');

        $d = Delivery::find($res->json('data.package.id'));
        $this->assertSame($p->id, $d->partner_id);
        $this->assertSame('cash', $d->payment_method); // recipient pays → cash
        $this->assertNotNull($d->package_photo);
        Storage::disk('public')->assertExists($d->package_photo);

        // Drivers don't see it before ROTEH confirms.
        $driver = User::factory()->create(['role' => 'driver', 'driver_type' => 'employee', 'api_token' => 'd-' . uniqid(), 'token_expires_at' => now()->addHour(), 'available' => true]);
        $this->as($driver)->getJson('/api/v1/deliveries/available')->assertJsonCount(0, 'data.deliveries.data');

        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->post(route('admin.partner-packages.confirm', $d))->assertRedirect();
        $this->assertSame('requested', $d->fresh()->status);
        $this->assertNotNull($d->fresh()->confirmed_at);

        $this->as($driver)->getJson('/api/v1/deliveries/available')->assertJsonCount(1, 'data.deliveries.data');

        $this->as($p)->getJson("/api/v1/partner/packages/{$d->id}")
            ->assertJsonPath('data.package.status', 'confirmed')
            ->assertJsonCount(2, 'data.package.timeline');
    }

    public function test_lists_summary_and_cancel_rules(): void
    {
        $p = $this->partner();
        $id = $this->as($p)->postJson('/api/v1/partner/packages', $this->packagePayload())->json('data.package.id');
        $done = Delivery::find($id)->replicate();
        $done->status = 'delivered';
        $done->qr_token = 'q-' . uniqid();
        $done->share_token = 's-' . uniqid();
        $done->save();

        $this->as($p)->getJson('/api/v1/partner/packages?group=active')->assertJsonPath('data.pagination.total', 1);
        $this->as($p)->getJson('/api/v1/partner/packages?group=history')->assertJsonPath('data.pagination.total', 1);
        $this->as($p)->getJson('/api/v1/partner/packages/summary')
            ->assertJsonPath('data.counts.pending', 1)
            ->assertJsonPath('data.counts.completed', 1)
            ->assertJsonPath('data.cod_pending', 40000);

        $this->as($p)->postJson("/api/v1/partner/packages/{$done->id}/cancel")->assertStatus(422);
        $this->as($p)->postJson("/api/v1/partner/packages/{$id}/cancel", ['reason' => 'Customer changed mind'])
            ->assertOk()->assertJsonPath('data.package.status', 'cancelled');

        // Another account can't see it.
        $other = $this->partner();
        $this->as($other)->getJson("/api/v1/partner/packages/{$id}")->assertStatus(401);
    }

    public function test_partner_can_still_book_like_a_passenger(): void
    {
        $p = $this->partner();
        $this->as($p)->getJson('/api/v1/rides')->assertOk();
        $this->as($p)->getJson('/api/v1/deliveries')->assertOk();
    }

    public function test_admin_pages_render(): void
    {
        $p = $this->partner();
        $this->as($p)->postJson('/api/v1/partner/packages', $this->packagePayload());
        PartnerProfile::create(['user_id' => $this->user()->id, 'business_name' => 'New Shop', 'phone' => '012345678']);
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->get(route('admin.partners'))->assertOk()->assertSee('New Shop');
        $this->actingAs($admin)->get(route('admin.partner-packages'))->assertOk()->assertSee('PKG-')->assertSee('Confirm');
        $d = Delivery::whereNotNull('partner_id')->first();
        $this->actingAs($admin)->get(route('admin.deliveries.show', $d))->assertOk()->assertSee('Confirm &amp; find driver', false);
    }

    private function pickedUpPackage(User $partner, User $driver): Delivery
    {
        $id = $this->as($partner)->postJson('/api/v1/partner/packages', $this->packagePayload())->json('data.package.id');
        $d = Delivery::find($id);
        $d->update(['driver_id' => $driver->id, 'status' => 'in_progress', 'confirmed_at' => now()]);
        return $d;
    }

    public function test_driver_marks_cannot_contact_then_returns_to_shop(): void
    {
        $p = $this->partner();
        $driver = User::factory()->create(['role' => 'driver', 'api_token' => 'd-' . uniqid(), 'token_expires_at' => now()->addHour()]);
        $d = $this->pickedUpPackage($p, $driver);

        // Return before failing is refused.
        $this->as($driver)->postJson("/api/v1/deliveries/{$d->id}/return")->assertStatus(422);

        $this->as($driver)->postJson("/api/v1/deliveries/{$d->id}/fail", ['reason' => 'cannot_contact', 'note' => 'Phone off'])
            ->assertOk()->assertJsonPath('data.delivery.status', 'delivery_failed');
        $this->assertTrue($driver->fresh()->hasActiveTrip()); // still holding the package

        $this->as($p)->getJson("/api/v1/partner/packages/{$d->id}")
            ->assertJsonPath('data.package.status', 'failed')
            ->assertJsonPath('data.package.failure_reason', 'cannot_contact')
            ->assertJsonPath('data.package.can_cancel', false);

        $this->as($driver)->postJson("/api/v1/deliveries/{$d->id}/return")
            ->assertOk()->assertJsonPath('data.delivery.status', 'returned');
        $this->assertFalse($driver->fresh()->hasActiveTrip());

        $res = $this->as($p)->getJson("/api/v1/partner/packages/{$d->id}")
            ->assertJsonPath('data.package.status', 'returned');
        $this->assertSame(['pending', 'in_transit', 'failed', 'returned'],
            collect($res->json('data.package.timeline'))->pluck('status')->all());
        $this->as($p)->getJson('/api/v1/partner/packages?group=history')->assertJsonPath('data.pagination.total', 1);

        // Another driver can't touch it; a returned package can't be completed.
        $other = User::factory()->create(['role' => 'driver', 'api_token' => 'o-' . uniqid(), 'token_expires_at' => now()->addHour()]);
        $this->as($other)->postJson("/api/v1/deliveries/{$d->id}/fail", ['reason' => 'other'])->assertStatus(401);
        $this->as($driver)->postJson("/api/v1/deliveries/{$d->id}/complete")->assertStatus(422);
    }

    public function test_failed_package_can_still_be_completed_if_receiver_answers(): void
    {
        $p = $this->partner();
        $driver = User::factory()->create(['role' => 'driver', 'api_token' => 'd-' . uniqid(), 'token_expires_at' => now()->addHour()]);
        $d = $this->pickedUpPackage($p, $driver);

        $this->as($driver)->postJson("/api/v1/deliveries/{$d->id}/fail", ['reason' => 'cannot_contact'])->assertOk();
        $this->as($driver)->postJson("/api/v1/deliveries/{$d->id}/complete")->assertOk();
        $this->assertSame('completed', $d->fresh()->packageStatus());
    }

    public function test_report_by_day_week_and_month(): void
    {
        $p = $this->partner();
        $driver = User::factory()->create(['role' => 'driver']);
        $tz = config('ride.local_timezone', 'Asia/Phnom_Penh');
        $make = function (string $status, string $localTime, array $extra = []) use ($p, $driver, $tz) {
            $id = $this->as($p)->postJson('/api/v1/partner/packages', $this->packagePayload())->json('data.package.id');
            $at = \Carbon\Carbon::parse($localTime, $tz)->setTimezone(config('app.timezone'));
            Delivery::whereKey($id)->update($extra + ['status' => $status, 'driver_id' => $driver->id, 'created_at' => $at]);
        };

        $make('completed', '2026-11-04 09:15', ['completed_at' => \Carbon\Carbon::parse('2026-11-04 10:00', $tz)->setTimezone(config('app.timezone'))]);
        $make('completed', '2026-11-04 14:00');
        $make('returned', '2026-11-04 15:00', ['failure_reason' => 'cannot_contact']);
        $make('cancelled', '2026-11-05 08:00');
        $make('created', '2026-11-20 08:00');

        $day = $this->as($p)->getJson('/api/v1/partner/packages/report?period=day&date=2026-11-04')->assertOk();
        $day->assertJsonPath('data.totals.total', 3)
            ->assertJsonPath('data.totals.completed', 2)
            ->assertJsonPath('data.totals.returned', 1)
            ->assertJsonPath('data.failure_reasons.cannot_contact', 1)
            ->assertJsonPath('data.money.delivery_fees', 2 * 10000)
            ->assertJsonPath('data.money.cod_collected', 80000)
            ->assertJsonPath('data.avg_delivery_minutes', 45)
            ->assertJsonCount(24, 'data.series');
        $this->assertEquals(66.7, $day->json('data.success_rate'));
        $this->assertSame(1, collect($day->json('data.series'))->firstWhere('key', '09')['completed']);

        $this->as($p)->getJson('/api/v1/partner/packages/report?period=week&date=2026-11-04')
            ->assertJsonPath('data.from', '2026-11-02')->assertJsonPath('data.to', '2026-11-08')
            ->assertJsonPath('data.totals.total', 4)->assertJsonPath('data.totals.cancelled', 1)
            ->assertJsonCount(7, 'data.series');

        $this->as($p)->getJson('/api/v1/partner/packages/report?period=month&date=2026-11-04')
            ->assertJsonPath('data.totals.total', 5)->assertJsonPath('data.totals.in_progress', 1)
            ->assertJsonCount(30, 'data.series');
    }

    public function test_report_exports_pdf_and_excel(): void
    {
        $p = $this->partner();
        $this->as($p)->postJson('/api/v1/partner/packages', $this->packagePayload(['recipient_name' => 'សុខា']));

        $pdf = $this->as($p)->get('/api/v1/partner/packages/report/export?period=month&format=pdf');
        $pdf->assertOk();
        $this->assertStringContainsString('application/pdf', $pdf->headers->get('content-type'));
        $this->assertStringStartsWith('%PDF', $pdf->getContent());

        $xlsx = $this->as($p)->get('/api/v1/partner/packages/report/export?period=week&format=xlsx');
        $xlsx->assertOk();
        $this->assertStringContainsString('.xlsx', $xlsx->headers->get('content-disposition'));

        // Normal passengers can't export.
        $this->as($this->user())->get('/api/v1/partner/packages/report/export?format=pdf', ['Accept' => 'application/json'])->assertStatus(403);
    }
}

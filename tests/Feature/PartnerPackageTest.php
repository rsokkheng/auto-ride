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

    private function as(User $u): static
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
        $driver = User::factory()->create(['role' => 'driver', 'api_token' => 'd-' . uniqid(), 'token_expires_at' => now()->addHour(), 'available' => true]);
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
            ->assertJsonPath('data.counts.delivered', 1)
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
}

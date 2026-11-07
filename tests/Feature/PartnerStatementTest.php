<?php

namespace Tests\Feature;

use App\Models\Delivery;
use App\Models\PartnerProfile;
use App\Models\PricingSetting;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

/** Daily Partner statements: successful / unsuccessful, same numbers everywhere. */
class PartnerStatementTest extends TestCase
{
    use RefreshDatabase;

    private User $partner;
    private string $day = '2026-11-06';

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        $this->partner = User::factory()->create([
            'role' => 'partner', 'phone' => '012345678', 'name' => 'Sokha',
            'api_token' => 't-' . uniqid(), 'token_expires_at' => now()->addHour(),
        ]);
        PartnerProfile::create(['user_id' => $this->partner->id, 'business_name' => 'ហាងសុខា Shop', 'phone' => '012345678', 'status' => 'approved']);

        $at = fn (string $hm) => Carbon::parse("{$this->day} {$hm}", 'Asia/Phnom_Penh')->setTimezone(config('app.timezone'));
        // Successful: COD 40k (created as "receiver pays" — still charged to the partner) · COD 30k · partner prepaid ABA (no COD)
        $this->pkg(['status' => 'completed', 'completed_at' => $at('09:00'), 'fee' => 5000, 'package_amount' => 40000, 'payment_by' => 'recipient']);
        $this->pkg(['status' => 'completed', 'completed_at' => $at('10:00'), 'fee' => 5000, 'package_amount' => 30000, 'payment_by' => 'sender', 'payment_method' => 'cash']);
        $this->pkg(['status' => 'completed', 'completed_at' => $at('11:00'), 'fee' => 10000, 'package_amount' => 0, 'payment_by' => 'sender', 'payment_method' => 'aba', 'payment_status' => 'paid']);
        // Unsuccessful: returned (cannot contact) · returning (wrong address) · cancelled
        $this->pkg(['status' => 'returned', 'failure_reason' => 'cannot_contact', 'failed_at' => $at('12:00'), 'returned_at' => $at('15:00'), 'fee' => 5000, 'package_amount' => 25000]);
        $this->pkg(['status' => 'delivery_failed', 'failure_reason' => 'wrong_address', 'failed_at' => $at('13:00'), 'fee' => 5000]);
        $this->pkg(['status' => 'cancelled', 'cancelled_at' => $at('08:00'), 'fee' => 5000]);
        // Other days / other partner — excluded
        $this->pkg(['status' => 'completed', 'completed_at' => $at('09:00')->addDay(), 'fee' => 5000]);
        $other = User::factory()->create(['role' => 'partner']);
        $this->pkg(['partner_id' => $other->id, 'sender_id' => $other->id, 'status' => 'completed', 'completed_at' => $at('09:30'), 'fee' => 5000]);
    }

    private function pkg(array $a): Delivery
    {
        $created = $a['created_at'] ?? null;
        unset($a['created_at']);
        $d = $this->make($a);
        if ($created) Delivery::whereKey($d->id)->update(['created_at' => $created]);
        return $d->fresh();
    }

    private function make(array $a): Delivery
    {
        return Delivery::create($a + [
            'partner_id' => $this->partner->id, 'sender_id' => $this->partner->id, 'service_type' => 'delivery',
            'sender_name' => 'Shop', 'recipient_name' => 'ដារ៉ា', 'recipient_phone' => '0961234567',
            'pickup_address' => 'TK', 'dropoff_address' => 'BKK1', 'package_size' => 'small',
            'payment_by' => 'sender', 'payment_method' => 'cash', 'payment_status' => 'unpaid',
            'qr_token' => Str::random(32), 'share_token' => Str::random(32),
        ]);
    }

    private function api(string $url)
    {
        return $this->withHeaders(['Authorization' => 'Bearer ' . $this->partner->api_token])->getJson($url);
    }

    public function test_statement_numbers(): void
    {
        $r = $this->api("/api/v1/partner/statements?date={$this->day}")->assertOk();

        $r->assertJsonPath('data.summary.successful_count', 3)
          ->assertJsonPath('data.summary.unsuccessful_count', 3)
          ->assertJsonPath('data.summary.returned_count', 1)
          ->assertJsonPath('data.summary.returning_count', 1)
          ->assertJsonPath('data.summary.cancelled_count', 1)
          ->assertJsonPath('data.summary.cod_collected', 70000)
          ->assertJsonPath('data.summary.service_fees', 20000)
          ->assertJsonPath('data.summary.fees_paid_by_receiver', 0)
          ->assertJsonPath('data.summary.fees_prepaid', 10000)
          ->assertJsonPath('data.summary.fees_from_cod', 10000)
          ->assertJsonPath('data.summary.cod_not_collected', 25000)
          ->assertJsonPath('data.summary.failed_fees', 0)
          ->assertJsonPath('data.summary.net_to_partner', 60000) // (40k − 5k) + (30k − 5k) + 0
          ->assertJsonPath('data.successful.1.fee_paid_by', 'partner_from_cod')
          ->assertJsonPath('data.unsuccessful.0.reason', 'cannot_contact');
    }

    public function test_failed_fee_percent_is_charged_on_attempts_not_cancellations(): void
    {
        PricingSetting::updateOrCreate(['key' => 'partner_failed_fee_percent'], ['value' => '50', 'label' => 'x']);
        \Illuminate\Support\Facades\Cache::flush();

        $this->api("/api/v1/partner/statements?date={$this->day}")
            ->assertJsonPath('data.summary.failed_fees', 5000)        // 2 attempts × 2,500
            ->assertJsonPath('data.summary.net_to_partner', 55000);
    }

    public function test_admin_index_print_and_excel(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->get(route('admin.partner-statements', ['date' => $this->day]))
            ->assertOk()->assertSee('ហាងសុខា Shop')->assertSee('60,000');

        $this->actingAs($admin)->get(route('admin.partner-statements.show', [$this->partner, 'date' => $this->day, 'type' => 'successful']))
            ->assertOk()->assertSee('របាយការណ៍កញ្ចប់ដឹកជោគជ័យ')->assertDontSee('របាយការណ៍កញ្ចប់ដឹកមិនបានជោគជ័យ')->assertSee('ដារ៉ា');

        $this->actingAs($admin)->get(route('admin.partner-statements.show', [$this->partner, 'date' => $this->day, 'type' => 'unsuccessful']))
            ->assertOk()->assertSee('របាយការណ៍កញ្ចប់ដឹកមិនបានជោគជ័យ')->assertSee('មិនអាចទាក់ទងអ្នកទទួល');

        $x = $this->actingAs($admin)->get(route('admin.partner-statements.excel', [$this->partner, 'date' => $this->day]));
        $x->assertOk();
        $this->assertStringContainsString('.xlsx', $x->headers->get('content-disposition'));
    }

    public function test_partner_website_and_signed_app_links(): void
    {
        $this->actingAs($this->partner, 'partner')->get(route('partner.statements', ['date' => $this->day]))
            ->assertOk()->assertSee('PKG-');

        $link = $this->api("/api/v1/partner/statements/link?date={$this->day}&type=unsuccessful")->assertOk()->json('data');
        $this->get($link['print_url'])->assertOk()->assertSee('របាយការណ៍កញ្ចប់ដឹកមិនបានជោគជ័យ');
        $this->get($link['excel_url'])->assertOk();

        // Tampering with the link (another date) breaks the signature.
        $this->get(str_replace("date={$this->day}", 'date=2026-11-01', $link['print_url']))->assertStatus(403);
    }

    public function test_passenger_cannot_get_statements(): void
    {
        $p = User::factory()->create(['role' => 'passenger', 'api_token' => 'p-' . uniqid(), 'token_expires_at' => now()->addHour()]);
        $this->withHeaders(['Authorization' => 'Bearer ' . $p->api_token])->getJson('/api/v1/partner/statements')->assertStatus(403);
    }

    public function test_admin_overview_all_statuses_filters_and_exports(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $at = fn (string $hm) => Carbon::parse("{$this->day} {$hm}", 'Asia/Phnom_Penh')->setTimezone(config('app.timezone'));
        // Open packages created that day: pending, confirmed, on the way
        $this->pkg(['status' => 'created', 'created_at' => $at('14:00')]);
        $this->pkg(['status' => 'requested', 'created_at' => $at('14:10')]);
        $this->pkg(['status' => 'in_progress', 'created_at' => $at('14:20')]);
        // A second partner with only a pending package
        $b = User::factory()->create(['role' => 'partner', 'name' => 'Bopha Store']);
        $this->pkg(['partner_id' => $b->id, 'sender_id' => $b->id, 'status' => 'created', 'created_at' => $at('15:00')]);

        $ov = app(\App\Services\PartnerStatementService::class)->overview(...\App\Services\PartnerStatementService::dayRange($this->day));
        $mine = $ov[$this->partner->id];
        $this->assertSame(['pending' => 1, 'confirmed' => 1, 'on_the_way' => 1, 'completed' => 3, 'failed' => 1, 'returned' => 1, 'cancelled' => 1], $mine['counts']);
        $this->assertSame(9, $mine['total']);
        $this->assertSame(60000, $mine['net_to_partner']);
        // Same numbers as the per-partner statement.
        $st = $this->api("/api/v1/partner/statements?date={$this->day}")->json('data.summary');
        $this->assertSame($mine['counts'], $st['status_counts']);
        $this->assertSame($mine['net_to_partner'], $st['net_to_partner']);

        $page = fn (array $q) => $this->actingAs($admin)->get(route('admin.partner-statements', ['date' => $this->day] + $q))->assertOk();
        $page([])->assertSee('ហាងសុខា Shop')->assertSee('Bopha Store');
        $page(['search' => 'Bopha'])->assertSee('Bopha Store')->assertDontSee('ហាងសុខា Shop');
        $page(['status' => 'unsuccessful'])->assertSee('ហាងសុខា Shop')->assertDontSee('Bopha Store');
        $page(['status' => 'pending', 'sort' => 'name'])->assertSee('Bopha Store')->assertSee('ហាងសុខា Shop');

        $this->actingAs($admin)->get(route('admin.partner-statements.overview', ['date' => $this->day, 'status' => 'unsuccessful']))
            ->assertOk()->assertSee('របាយការណ៍ដៃគូទាំងអស់')->assertDontSee('Bopha Store');
        $this->actingAs($admin)->get(route('admin.partner-statements.overview.excel', ['date' => $this->day]))->assertOk();
        $this->actingAs($admin)->get(route('admin.partner-statements.show', [$this->partner, 'date' => $this->day, 'type' => 'all']))
            ->assertOk()->assertSee('របាយការណ៍កញ្ចប់ទាំងអស់')->assertSee('កំពុងរង់ចាំ');
    }

    public function test_total_collected_and_who_pays_whom(): void
    {
        // No COD, partner pays the fee in cash and hasn't paid → partner pays ROTEH 5,000.
        $at = Carbon::parse("{$this->day} 16:00", 'Asia/Phnom_Penh')->setTimezone(config('app.timezone'));
        $this->pkg(['status' => 'completed', 'completed_at' => $at, 'fee' => 5000, 'package_amount' => 0, 'payment_by' => 'sender', 'payment_method' => 'cash']);

        $r = $this->api("/api/v1/partner/statements?date={$this->day}")->assertOk();
        // Receivers pay only COD: 40k · 30k · 0 (prepaid) · 0 (no COD)
        $r->assertJsonPath('data.summary.total_collected', 70000)
          ->assertJsonPath('data.summary.roteh_pays_partner', 60000)   // (40k − 5k) + (30k − 5k)
          ->assertJsonPath('data.summary.partner_pays_roteh', 5000)    // the no-COD package
          ->assertJsonPath('data.summary.net_to_partner', 55000)
          ->assertJsonPath('data.summary.non_cod_packages', 2)
          ->assertJsonPath('data.successful.0.total_collected', 40000)
          ->assertJsonPath('data.successful.0.roteh_pays_partner', 35000)
          ->assertJsonPath('data.successful.3.fee_paid_by', 'partner_pays')
          ->assertJsonPath('data.successful.3.partner_pays_roteh', 5000)
          ->assertJsonPath('data.successful.3.roteh_pays_partner', 0);

        // The admin overview (one SQL query) gives the same numbers.
        $ov = app(\App\Services\PartnerStatementService::class)
            ->overview(...\App\Services\PartnerStatementService::dayRange($this->day))[$this->partner->id];
        $this->assertSame([70000, 60000, 5000, 55000],
            [$ov['total_collected'], $ov['roteh_pays_partner'], $ov['partner_pays_roteh'], $ov['net_to_partner']]);

        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->get(route('admin.partner-statements.show', [$this->partner, 'date' => $this->day, 'type' => 'successful']))
            ->assertOk()->assertSee('ROTEH pays partner')->assertSee('70,000')->assertSee('55,000');
    }

    public function test_reported_case_cod_100k_fee_15k_pays_partner_85k(): void
    {
        $other = User::factory()->create(['role' => 'partner', 'api_token' => 'o-' . uniqid(), 'token_expires_at' => now()->addHour()]);
        $at = Carbon::parse("{$this->day} 10:00", 'Asia/Phnom_Penh')->setTimezone(config('app.timezone'));
        $d = $this->make(['partner_id' => $other->id, 'sender_id' => $other->id, 'status' => 'completed', 'completed_at' => $at,
            'fee' => 15000, 'package_amount' => 100000, 'payment_by' => 'recipient', 'payment_method' => 'cash', 'payment_status' => 'paid']);
        $this->assertSame('sender', $d->fresh()->payment_by); // the fee is always the partner's

        $this->withHeaders(['Authorization' => 'Bearer ' . $other->api_token])
            ->getJson("/api/v1/partner/statements?date={$this->day}")
            ->assertJsonPath('data.successful.0.roteh_pays_partner', 85000)
            ->assertJsonPath('data.successful.0.fee_paid_by', 'partner_from_cod')
            ->assertJsonPath('data.summary.net_to_partner', 85000);
    }
}

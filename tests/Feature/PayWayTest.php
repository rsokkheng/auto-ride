<?php

namespace Tests\Feature;

use App\Models\PayWayTransaction;
use App\Models\User;
use App\Services\PayWayService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/** ABA PayWay KHQR: paid only when PayWay's Check Transaction says so, credited once. */
class PayWayTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        config([
            'payway.base_url' => 'https://checkout-sandbox.payway.com.kh',
            'payway.merchant_id' => 'ec000001', 'payway.api_key' => 'test-public-key',
            'payway.callback_key' => 'test-public-key', 'payway.lifetime' => 15,
        ]);
        $this->user = User::factory()->create([
            'role' => 'passenger', 'wallet_balance' => 0, 'phone' => '012345678',
            'api_token' => 't-' . uniqid(), 'token_expires_at' => now()->addHour(),
        ]);
    }

    private int $paywayStatus = 2; // what Check Transaction answers (0 approved, 2 pending)
    private bool $faked = false;

    private function fakePayWay(int $paymentStatusCode = 2): void
    {
        $this->paywayStatus = $paymentStatusCode;
        if ($this->faked) return; // one fake; the closure reads the current status
        $this->faked = true;
        Http::fake([
            '*/payments/generate-qr' => Http::response([
                'status' => ['code' => '0', 'message' => 'Success.'],
                'amount' => 20000, 'currency' => 'KHR',
                'qrString' => '00020101021230510016abaakhppxxx', 'qrImage' => 'data:image/png;base64,AAAA',
                'abapay_deeplink' => 'abamobilebank://ababank.com?type=payway&qrcode=xyz',
            ]),
            '*/payments/check-transaction-2' => fn () => Http::response([
                'data' => ['payment_status_code' => $this->paywayStatus, 'payment_amount' => 20000,
                           'payment_status' => $this->paywayStatus === 0 ? 'APPROVED' : 'PENDING', 'apv' => '123456'],
                'status' => ['code' => '00', 'message' => 'Success!'],
            ]),
        ]);
    }

    private function create(): string
    {
        return $this->withToken($this->user->api_token)
            ->postJson('/api/v1/payments/payway/khqr', ['amount' => 20000, 'purpose' => 'topup'])
            ->assertCreated()
            ->assertJsonPath('data.qr_string', '00020101021230510016abaakhppxxx')
            ->json('data.tran_id');
    }

    public function test_khqr_request_is_signed_in_payways_field_order(): void
    {
        $this->fakePayWay();
        $tranId = $this->create();

        $this->assertLessThanOrEqual(20, strlen($tranId));
        Http::assertSent(function (HttpRequest $r) use ($tranId) {
            if (! str_ends_with($r->url(), '/payments/generate-qr')) return false;
            $d = $r->data();
            $expected = PayWayService::hash([
                $d['req_time'], 'ec000001', $tranId, '20000', '', $d['first_name'], $d['last_name'], '',
                '012345678', 'purchase', 'abapay_khqr', $d['callback_url'], '', 'KHR', '', 'topup', '', '15', $d['qr_image_template'],
            ], 'test-public-key');
            return $d['hash'] === $expected && $d['amount'] === 20000 && $d['payment_option'] === 'abapay_khqr';
        });
    }

    public function test_approved_payment_credits_the_wallet_exactly_once(): void
    {
        $this->fakePayWay();
        $tranId = $this->create();

        $this->fakePayWay(0); // PayWay now says APPROVED
        for ($i = 0; $i < 3; $i++) {
            $this->withToken($this->user->api_token)->getJson("/api/v1/payments/payway/{$tranId}")
                ->assertOk()->assertJsonPath('data.status', 'paid');
        }

        $this->assertSame(20000, (int) $this->user->fresh()->wallet_balance);
    }

    public function test_forged_callback_does_not_credit(): void
    {
        $this->fakePayWay(2); // still pending at PayWay
        $tranId = $this->create();

        $this->postJson('/api/v1/payments/payway/callback', ['tran_id' => $tranId, 'status' => '0', 'apv' => '999999'])
            ->assertOk();

        $this->assertSame('pending', PayWayTransaction::where('tran_id', $tranId)->value('status'));
        $this->assertSame(0, (int) $this->user->fresh()->wallet_balance);
    }

    public function test_real_callback_confirms_and_credits(): void
    {
        $this->fakePayWay();
        $tranId = $this->create();
        $this->fakePayWay(0);

        $this->postJson('/api/v1/payments/payway/callback', ['tran_id' => $tranId, 'status' => '0'])->assertOk();

        $this->assertSame('paid', PayWayTransaction::where('tran_id', $tranId)->value('status'));
        $this->assertSame(20000, (int) $this->user->fresh()->wallet_balance);
    }

    public function test_not_configured_and_old_webhook(): void
    {
        config(['payway.merchant_id' => null]);
        $this->withToken($this->user->api_token)->postJson('/api/v1/payments/payway/khqr', ['amount' => 20000])
            ->assertStatus(503)->assertJsonPath('code', 'not_configured');

        // The unauthenticated "mark as paid" webhook is closed.
        $this->postJson('/api/v1/payments/qr/webhook', ['reference' => 'X', 'bank_ref' => 'Y', 'status' => 'paid'])
            ->assertStatus(410);
    }
}

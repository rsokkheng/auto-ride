<?php

namespace App\Services;

use App\Models\PayWayTransaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * ABA PayWay KHQR payments (https://developer.payway.com.kh).
 *
 * A KHQR can be paid from ABA Mobile and any KHQR member bank app (ACLEDA…).
 * A transaction only becomes "paid" after PayWay's Check Transaction API —
 * a signed, server-to-server call — says APPROVED. Callbacks merely trigger
 * that check, so a forged callback can never credit anyone.
 */
class PayWayService
{
    public function __construct(private WalletService $wallet) {}

    public function isConfigured(): bool
    {
        return filled(config('payway.merchant_id')) && filled(config('payway.api_key'));
    }

    /** Base64(HMAC-SHA512(concatenated values, key)) — PayWay's request hash. */
    public static function hash(array $orderedValues, string $key): string
    {
        return base64_encode(hash_hmac('sha512', implode('', array_map(fn ($v) => (string) $v, $orderedValues)), $key, true));
    }

    /**
     * Creates a KHQR for [amount] riel. Returns the transaction plus what the
     * app shows: qr_string, qr_image (data URI) and the ABA Mobile deeplink.
     */
    /**
     * @param float|int   $amount     whole riel for KHR, dollars (2 decimals) for USD
     * @param string|null $guestToken guest checkouts (no account)
     */
    public function createKhqr(?User $user, float|int $amount, string $purpose = 'topup', ?Model $payable = null,
                               string $currency = 'KHR', ?string $guestToken = null, ?string $guestName = null,
                               ?string $guestPhone = null): array
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('ABA PayWay is not configured.');
        }

        $currency = strtoupper($currency) === 'USD' ? 'USD' : 'KHR';
        // KHR must be a whole number; USD has cents. The same text is hashed and sent.
        $amount   = $currency === 'KHR' ? (int) round($amount) : round((float) $amount, 2);

        $tx = PayWayTransaction::create([
            'user_id'      => $user?->id,
            'guest_token'  => $user ? null : $guestToken,
            'tran_id'      => PayWayTransaction::newTranId(),
            'purpose'      => $purpose,
            'payable_type' => $payable?->getMorphClass(),
            'payable_id'   => $payable?->getKey(),
            'amount'       => $amount,
            'currency'     => $currency,
            'status'       => 'pending',
            'expires_at'   => now()->addMinutes(max(3, (int) config('payway.lifetime', 15))),
        ]);

        [$first, $last] = array_pad(explode(' ', trim((string) ($user?->name ?? $guestName)), 2), 2, '');
        $p = [
            'req_time'          => now('UTC')->format('YmdHis'),
            'merchant_id'       => (string) config('payway.merchant_id'),
            'tran_id'           => $tx->tran_id,
            'amount'            => (string) $amount,
            'items'             => '',
            'first_name'        => mb_substr($first, 0, 20),
            'last_name'         => mb_substr($last, 0, 20),
            'email'             => '',
            'phone'             => (string) ($user?->phone ?? $guestPhone ?? ''),
            'purchase_type'     => 'purchase',
            'payment_option'    => 'abapay_khqr',
            'callback_url'      => filled(config('payway.callback_url')) ? base64_encode(config('payway.callback_url')) : '',
            'return_deeplink'   => '',
            'currency'          => $tx->currency,
            'custom_fields'     => '',
            'return_params'     => $tx->purpose,
            'payout'            => '',
            'lifetime'          => (string) max(3, (int) config('payway.lifetime', 15)),
            'qr_image_template' => (string) config('payway.qr_template', 'template3_color'),
        ];
        // Exact field order from PayWay's QR API docs.
        $p['hash'] = self::hash([
            $p['req_time'], $p['merchant_id'], $p['tran_id'], $p['amount'], $p['items'],
            $p['first_name'], $p['last_name'], $p['email'], $p['phone'], $p['purchase_type'],
            $p['payment_option'], $p['callback_url'], $p['return_deeplink'], $p['currency'],
            $p['custom_fields'], $p['return_params'], $p['payout'], $p['lifetime'], $p['qr_image_template'],
        ], (string) config('payway.api_key'));

        $body = $p;
        $body['amount']   = $amount;
        $body['lifetime'] = (int) $p['lifetime'];

        $res  = Http::timeout(20)->acceptJson()->asJson()
            ->post(rtrim(config('payway.base_url'), '/') . '/api/payment-gateway/v1/payments/generate-qr', $body);
        $json = $res->json() ?? [];
        $code = (string) data_get($json, 'status.code', '');

        if (! $res->successful() || ! in_array($code, ['0', '00'], true) || blank($json['qrString'] ?? null)) {
            $tx->update(['status' => 'failed', 'last_response' => $json]);
            Log::warning('[PayWay] generate-qr failed', ['tran_id' => $tx->tran_id, 'http' => $res->status(), 'status' => $json['status'] ?? null]);
            throw new RuntimeException((string) data_get($json, 'status.message', 'Could not create the ABA payment.'));
        }

        $tx->update(['last_response' => array_diff_key($json, ['qrImage' => 1])]);

        return [
            'transaction'     => $tx->fresh(),
            'qr_string'       => $json['qrString'],
            'qr_image'        => $json['qrImage'] ?? null,
            'abapay_deeplink' => $json['abapay_deeplink'] ?? null,
            'app_store'       => $json['app_store'] ?? null,
            'play_store'      => $json['play_store'] ?? null,
        ];
    }

    /**
     * Asks PayWay for the real status and applies it (credit once on APPROVED).
     * Safe to call any number of times, from polling or callbacks.
     */
    public function refresh(PayWayTransaction $tx): PayWayTransaction
    {
        if ($tx->isFinal() || ! $this->isConfigured()) return $tx;

        $reqTime = now('UTC')->format('YmdHis');
        $merchant = (string) config('payway.merchant_id');
        $res = Http::timeout(15)->acceptJson()->asJson()
            ->post(rtrim(config('payway.base_url'), '/') . '/api/payment-gateway/v1/payments/check-transaction-2', [
                'req_time'    => $reqTime,
                'merchant_id' => $merchant,
                'tran_id'     => $tx->tran_id,
                'hash'        => self::hash([$reqTime, $merchant, $tx->tran_id], (string) config('payway.api_key')),
            ]);

        $json = $res->json() ?? [];
        $code = data_get($json, 'data.payment_status_code');

        if (! $res->successful() || $code === null) {
            // Not found yet / temporary error — expire only once the QR has lapsed.
            if ($tx->expires_at && $tx->expires_at->isPast()) $tx->update(['status' => 'expired']);
            return $tx->fresh();
        }

        $result = match ((int) $code) {
            0       => $this->markPaid($tx, $json),
            3       => tap($tx)->update(['status' => 'failed', 'last_response' => $json]),
            4       => tap($tx)->update(['status' => 'refunded', 'last_response' => $json]),
            7       => tap($tx)->update(['status' => 'cancelled', 'last_response' => $json]),
            default => tap($tx, function ($t) use ($json) {  // 2 = pending
                $t->update(['last_response' => $json] + ($t->expires_at?->isPast() ? ['status' => 'expired'] : []));
            }),
        };

        return $result->fresh();
    }

    /** Idempotent: the row lock means a payment is credited exactly once. */
    private function markPaid(PayWayTransaction $tx, array $json): PayWayTransaction
    {
        return DB::transaction(function () use ($tx, $json) {
            $locked = PayWayTransaction::whereKey($tx->id)->lockForUpdate()->first();
            if ($locked->status === 'paid') return $locked;

            // Credit what PayWay says was paid, never more than was asked.
            $paid = (float) data_get($json, 'data.payment_amount', $locked->amount);
            if ($paid + 0.001 < $locked->amount) {
                Log::warning('[PayWay] underpaid', ['tran_id' => $locked->tran_id, 'asked' => $locked->amount, 'paid' => $paid]);
            }

            $locked->update([
                'status'        => 'paid',
                'apv'           => data_get($json, 'data.apv'),
                'paid_at'       => now(),
                'last_response' => $json,
            ]);

            $this->applyPaid($locked, $paid);

            return $locked;
        });
    }

    /** What a confirmed payment unlocks, per purpose. Runs inside markPaid's transaction. */
    private function applyPaid(PayWayTransaction $tx, float $paid): void
    {
        $payable = $tx->payable;

        switch ($tx->purpose) {
            case 'topup':
                $this->wallet->credit($tx->user, (int) min($paid, $tx->amount), 'topup',
                    'ABA PayWay top-up ' . $tx->tran_id, $tx);
                break;

            case 'ride':
                // Rides are paid from the receipt, after the trip.
                $payable?->update(['payment_status' => 'paid']);
                break;

            case 'delivery':
                // Paid before the job → 'prepaid' (settlement then credits the
                // driver and marks it paid); paid after it was settled → 'paid'.
                if ($payable) {
                    $payable->update(['payment_status' => $payable->payment_status === 'awaiting_payment' ? 'paid' : 'prepaid']);
                }
                break;

            case 'marketplace':
                // One checkout can hold several orders (checkout_batch_id).
                if ($payable) {
                    $q = \App\Models\MarketplaceOrder::query();
                    $payable->checkout_batch_id
                        ? $q->where('checkout_batch_id', $payable->checkout_batch_id)
                        : $q->whereKey($payable->getKey());
                    $q->update(['payment_status' => 'paid']);
                }
                break;

            case 'car_rental':
                $payable?->update(['payment_status' => 'paid']);
                break;

            case 'subscription':
                if ($payable && $payable->status === 'pending') {
                    // An upgrade replaces the plan that was still running.
                    \App\Models\UserSubscription::where('user_id', $payable->user_id)->where('status', 'active')
                        ->whereKeyNot($payable->getKey())
                        ->update(['status' => 'cancelled', 'cancelled_at' => now(), 'auto_renew' => false]);
                    $payable->update(['status' => 'active', 'started_at' => now()]);
                    \App\Models\SubscriptionTransaction::where('user_subscription_id', $payable->getKey())
                        ->where('status', 'pending')
                        ->update(['status' => 'paid', 'paid_at' => now(), 'reference' => $tx->tran_id]);
                }
                break;
        }
    }

    /**
     * Callback signature (X-PayWay-HMAC-SHA512): values of the JSON body sorted
     * by key, concatenated, HMAC-SHA512 with the callback key, Base64.
     */
    public static function callbackSignatureValid(array $payload, ?string $signature): bool
    {
        $key = (string) config('payway.callback_key');
        if (! $signature || $key === '') return false;

        ksort($payload);
        $b4hash = implode('', array_map(fn ($v) => is_array($v) ? json_encode($v) : (string) $v, $payload));

        return hash_equals(base64_encode(hash_hmac('sha512', $b4hash, $key, true)), $signature);
    }
}

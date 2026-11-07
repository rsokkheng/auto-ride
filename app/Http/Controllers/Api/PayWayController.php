<?php

namespace App\Http\Controllers\Api;

use App\Models\PayWayTransaction;
use App\Services\PayWayService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class PayWayController extends ApiController
{
    public function __construct(private PayWayService $payway) {}

    /**
     * POST /v1/payments/payway/khqr  { amount, purpose? }
     * Creates an ABA KHQR. purpose=topup credits the wallet once paid.
     */
    public function createKhqr(Request $request)
    {
        $user = $this->authUser($request);
        if (! $user) return $this->unauthorized();

        $data = $request->validate([
            'amount'  => 'required|integer|min:1000|max:10000000',
            'purpose' => 'nullable|in:topup',
        ]);

        if (! $this->payway->isConfigured()) {
            return response()->json(['data' => null, 'code' => 'not_configured',
                'message' => 'ABA payment is not available yet.'], 503);
        }

        // One open KHQR per user at a time — reuse the screen instead of piling up.
        PayWayTransaction::where('user_id', $user->id)->where('status', 'pending')
            ->where('expires_at', '<', now())->update(['status' => 'expired']);

        try {
            $r = $this->payway->createKhqr($user, (int) $data['amount'], $data['purpose'] ?? 'topup');
        } catch (RuntimeException $e) {
            return response()->json(['data' => null, 'code' => 'payway_error', 'message' => $e->getMessage()], 502);
        }

        return $this->success($this->checkoutPayload($r), 201);
    }

    /**
     * POST /v1/payments/payway/order  { type, id, guest_token? }
     * KHQR for an order. The amount always comes from the order on the server.
     * type: ride | delivery | marketplace | car_rental | subscription
     */
    public function createForOrder(Request $request)
    {
        $data = $request->validate([
            'type'        => 'required|in:ride,delivery,marketplace,car_rental,subscription',
            'id'          => 'required|integer',
            'guest_token' => 'nullable|string|max:64',
        ]);
        $user  = $this->authUser($request);
        $guest = $user ? null : ($data['guest_token'] ?? null);
        if (! $user && ! $guest) return $this->unauthorized();

        if (! $this->payway->isConfigured()) {
            return response()->json(['data' => null, 'code' => 'not_configured',
                'message' => 'ABA payment is not available yet.'], 503);
        }

        [$payable, $amount, $currency, $error] = $this->resolveOrder($data['type'], (int) $data['id'], $user, $guest);
        if ($error) return response()->json(['data' => null, 'code' => 'not_payable', 'message' => $error], 422);

        try {
            $r = $this->payway->createKhqr($user, $amount, $data['type'], $payable, $currency, $guest,
                $payable->guest_name ?? null, $payable->guest_phone ?? null);
        } catch (RuntimeException $e) {
            return response()->json(['data' => null, 'code' => 'payway_error', 'message' => $e->getMessage()], 502);
        }

        return $this->success($this->checkoutPayload($r), 201);
    }

    /**
     * The order, what to charge, in which currency — or why it can't be paid.
     * @return array{0: ?\Illuminate\Database\Eloquent\Model, 1: float|int, 2: string, 3: ?string}
     */
    private function resolveOrder(string $type, int $id, ?\App\Models\User $user, ?string $guest): array
    {
        $online = \App\Services\PaymentService::ONLINE_METHODS;
        $owns = fn ($m, string $ownerCol) => $m && (($user && (int) $m->{$ownerCol} === $user->id)
            || (! $user && $guest && hash_equals((string) $m->guest_token, $guest)));

        switch ($type) {
            case 'ride':
                $m = \App\Models\Ride::find($id);
                if (! $user || ! $m || $m->passenger_id !== $user->id) return [null, 0, 'KHR', 'Ride not found.'];
                if ($m->payment_status === 'paid') return [null, 0, 'KHR', 'This ride is already paid.'];
                if ($m->status !== \App\Models\Ride::STATUS_COMPLETED) return [null, 0, 'KHR', 'You can pay once the trip is finished.'];
                return [$m, (int) $m->fare, 'KHR', null];

            case 'delivery':
                $m = \App\Models\Delivery::find($id);
                if (! $user || ! $m || $m->sender_id !== $user->id) return [null, 0, 'KHR', 'Delivery not found.'];
                if (in_array($m->payment_status, ['paid', 'prepaid'], true)) return [null, 0, 'KHR', 'This delivery is already paid.'];
                if ($m->status === 'cancelled') return [null, 0, 'KHR', 'This delivery was cancelled.'];
                if ((int) $m->fee <= 0) return [null, 0, 'KHR', 'Nothing to pay yet.'];
                return [$m, (int) $m->fee, 'KHR', null];

            case 'marketplace':
                $m = \App\Models\MarketplaceOrder::find($id);
                if (! $owns($m, 'buyer_id')) return [null, 0, 'USD', 'Order not found.'];
                if ($m->payment_status === 'paid') return [null, 0, 'USD', 'This order is already paid.'];
                if ($m->status === 'cancelled') return [null, 0, 'USD', 'This order was cancelled.'];
                // The whole checkout (several orders) in one payment, in USD.
                $total = $m->checkout_batch_id
                    ? (float) \App\Models\MarketplaceOrder::where('checkout_batch_id', $m->checkout_batch_id)
                        ->where('status', '!=', 'cancelled')->sum('total_price')
                    : (float) $m->total_price;
                return [$m, round($total, 2), 'USD', null];

            case 'car_rental':
                $m = \App\Models\CarRental::find($id);
                if (! $owns($m, 'user_id')) return [null, 0, 'KHR', 'Rental not found.'];
                if ($m->payment_status === 'paid') return [null, 0, 'KHR', 'This rental is already paid.'];
                if ($m->status === 'cancelled') return [null, 0, 'KHR', 'This rental was cancelled.'];
                return [$m, (int) $m->total_amount_khr, 'KHR', null];

            case 'subscription':
                $m = \App\Models\UserSubscription::with('plan')->find($id);
                if (! $user || ! $m || $m->user_id !== $user->id) return [null, 0, 'KHR', 'Subscription not found.'];
                if ($m->status !== 'pending') return [null, 0, 'KHR', 'This subscription is not waiting for payment.'];
                return [$m, (int) ($m->plan->price_khr ?? 0), 'KHR', null];
        }

        return [null, 0, 'KHR', 'Unknown order type.'];
    }

    private function checkoutPayload(array $r): array
    {
        return [
            'tran_id'         => $r['transaction']->tran_id,
            'amount'          => $r['transaction']->amount,
            'currency'        => $r['transaction']->currency,
            'purpose'         => $r['transaction']->purpose,
            'status'          => $r['transaction']->status,
            'expires_at'      => $r['transaction']->expires_at?->toIso8601String(),
            'qr_string'       => $r['qr_string'],
            'qr_image'        => $r['qr_image'],
            'abapay_deeplink' => $r['abapay_deeplink'],
            'app_store'       => $r['app_store'],
            'play_store'      => $r['play_store'],
        ];
    }

    /** GET /v1/payments/payway/{tranId}?guest_token= — current status (asks PayWay while pending). */
    public function status(Request $request, string $tranId)
    {
        $user  = $this->authUser($request);
        $guest = $user ? null : (string) $request->query('guest_token', '');
        if (! $user && $guest === '') return $this->unauthorized();

        $tx = PayWayTransaction::where('tran_id', $tranId)
            ->when($user, fn ($q) => $q->where('user_id', $user->id),
                          fn ($q) => $q->whereNull('user_id')->where('guest_token', $guest))
            ->first();
        if (! $tx) return response()->json(['data' => null, 'message' => 'Payment not found.'], 404);

        $tx = $this->payway->refresh($tx);

        return $this->success([
            'tran_id'    => $tx->tran_id,
            'status'     => $tx->status,
            'amount'     => $tx->amount,
            'purpose'    => $tx->purpose,
            'paid_at'    => $tx->paid_at?->toIso8601String(),
            'expires_at' => $tx->expires_at?->toIso8601String(),
        ]);
    }

    /**
     * POST /v1/payments/payway/callback — PayWay's notification (public).
     * Never trusted on its own: it only triggers a Check Transaction call.
     */
    public function callback(Request $request)
    {
        $payload = $request->json()->all() ?: $request->all();
        $tranId  = (string) ($payload['tran_id'] ?? '');

        if (! PayWayService::callbackSignatureValid($payload, $request->header('X-PayWay-HMAC-SHA512'))) {
            Log::info('[PayWay] callback without a valid signature — verifying via Check Transaction', ['tran_id' => $tranId]);
        }

        $tx = $tranId !== '' ? PayWayTransaction::where('tran_id', $tranId)->first() : null;
        if ($tx) $this->payway->refresh($tx);

        return response()->json(['ok' => true]);
    }
}

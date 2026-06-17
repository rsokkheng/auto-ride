<?php

namespace App\Http\Controllers\Api;

use App\Models\QrPayment;
use App\Services\WalletService;
use Illuminate\Http\Request;

class QrPaymentController extends ApiController
{
    public function __construct(private WalletService $wallet) {}

    /**
     * POST /v1/payments/qr/generate
     * Body: { amount_khr, payment_type?, payable_type?, payable_id? }
     */
    public function generate(Request $request)
    {
        $user = $this->authUser($request);
        if (! $user) return $this->unauthorized();

        $data = $request->validate([
            'amount_khr'   => 'required|integer|min:1000',
            'payment_type' => 'nullable|in:topup,ride,delivery,marketplace',
            'payable_type' => 'nullable|string|max:50',
            'payable_id'   => 'nullable|integer',
        ]);

        $reference = QrPayment::generateReference();

        // Build KHQR-compatible payload (EMV-like structure for Cambodia)
        $qrData = $this->buildKhqrPayload($reference, $data['amount_khr']);

        $qr = QrPayment::create([
            'user_id'      => $user->id,
            'reference'    => $reference,
            'amount_khr'   => $data['amount_khr'],
            'status'       => 'pending',
            'payment_type' => $data['payment_type'] ?? 'topup',
            'payable_type' => $data['payable_type'] ?? null,
            'payable_id'   => $data['payable_id']   ?? null,
            'qr_data'      => $qrData,
            'expires_at'   => now()->addMinutes(15),
        ]);

        return $this->success([
            'id'         => $qr->id,
            'reference'  => $qr->reference,
            'amount_khr' => $qr->amount_khr,
            'qr_data'    => $qrData,
            'expires_at' => $qr->expires_at->toISOString(),
        ]);
    }

    /** GET /v1/payments/qr/{reference}/status */
    public function status(Request $request, string $reference)
    {
        $user = $this->authUser($request);
        if (! $user) return $this->unauthorized();

        $qr = QrPayment::where('reference', $reference)
            ->where('user_id', $user->id)
            ->first();

        if (! $qr) {
            return response()->json(['data' => null, 'message' => 'QR payment not found.'], 404);
        }

        if ($qr->isExpired()) {
            $qr->update(['status' => 'expired']);
        }

        return $this->success([
            'reference'  => $qr->reference,
            'status'     => $qr->status,
            'amount_khr' => $qr->amount_khr,
            'paid_at'    => $qr->paid_at?->toISOString(),
            'expires_at' => $qr->expires_at->toISOString(),
        ]);
    }

    /**
     * POST /v1/payments/qr/webhook
     * Called by payment gateway with bank_ref and reference.
     */
    public function webhook(Request $request)
    {
        $data = $request->validate([
            'reference' => 'required|string',
            'bank_ref'  => 'required|string',
            'status'    => 'required|in:paid,failed',
        ]);

        $qr = QrPayment::where('reference', $data['reference'])->first();
        if (! $qr || $qr->status !== 'pending') {
            return response()->json(['ok' => false], 200);
        }

        if ($data['status'] === 'paid') {
            $qr->update([
                'status'   => 'paid',
                'bank_ref' => $data['bank_ref'],
                'paid_at'  => now(),
            ]);

            if ($qr->payment_type === 'topup') {
                $this->wallet->credit(
                    $qr->user,
                    $qr->amount_khr,
                    'QR Top-up — ' . $qr->reference
                );
            }
        } else {
            $qr->update(['status' => 'failed', 'bank_ref' => $data['bank_ref']]);
        }

        return response()->json(['ok' => true], 200);
    }

    /** GET /v1/payments/qr — list user's QR payments */
    public function index(Request $request)
    {
        $user = $this->authUser($request);
        if (! $user) return $this->unauthorized();

        $payments = QrPayment::where('user_id', $user->id)
            ->orderByDesc('id')
            ->paginate(20);

        return $this->success($payments);
    }

    private function buildKhqrPayload(string $reference, int $amountKhr): string
    {
        // Simplified EMV QR payload for KHQR
        $merchant = config('app.name', 'AutoRide');
        $amount   = number_format($amountKhr / 100, 2, '.', ''); // KHR in Riel (no decimals typical)

        return implode('|', [
            'KHQR',
            $merchant,
            $reference,
            $amountKhr,
            'KHR',
            now()->addMinutes(15)->format('YmdHis'),
        ]);
    }
}

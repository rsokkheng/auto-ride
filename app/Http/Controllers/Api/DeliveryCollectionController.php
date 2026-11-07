<?php

namespace App\Http\Controllers\Api;

use App\Models\Delivery;
use App\Models\DeliveryCollection;
use App\Services\PartnerStatementService as PS;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * POST /v1/deliveries/{delivery}/collect
 * Staff driver verifies the money received at handover — any mix of cash ៛,
 * cash $, bank ៛, bank $ — then the Partner package is completed.
 * Requires the handover QR scan first.
 */
class DeliveryCollectionController extends ApiController
{
    public const BANKS = ['ABA', 'ACLEDA', 'Wing', 'Other'];

    public function store(Request $request, Delivery $delivery)
    {
        $user = $this->authUser($request);
        if (! $user || $user->role !== 'driver' || $delivery->driver_id !== $user->id) return $this->unauthorized();

        if (! $delivery->partner_id) {
            return response()->json(['message' => 'Only Partner packages use payment verification.'], 422);
        }
        if ($existing = $delivery->collection) {
            return $this->success(['collection' => $this->format($existing), 'delivery' => $delivery->fresh(['sender', 'driver'])]);
        }
        if (! in_array($delivery->status, ['picked_up', 'in_progress', 'in_transit', 'delivery_failed'], true)) {
            return response()->json(['message' => 'This package is not on its way — status "' . $delivery->status . '".'], 422);
        }
        if (! $delivery->delivery_scanned_at) {
            return response()->json(['code' => 'scan_required', 'message' => 'Scan the package QR code at handover first.'], 422);
        }

        $data = $request->validate([
            'cash_khr'  => 'nullable|integer|min:0|max:100000000',
            'cash_usd'  => 'nullable|numeric|min:0|max:100000',
            'bank_khr'  => 'nullable|integer|min:0|max:100000000',
            'bank_usd'  => 'nullable|numeric|min:0|max:100000',
            'bank_name' => 'nullable|in:' . implode(',', self::BANKS),
            'bank_ref'  => 'nullable|string|max:100',
            'note'      => 'nullable|string|max:500',
        ]);
        $cashKhr = (int) ($data['cash_khr'] ?? 0);
        $cashUsd = round((float) ($data['cash_usd'] ?? 0), 2);
        $bankKhr = (int) ($data['bank_khr'] ?? 0);
        $bankUsd = round((float) ($data['bank_usd'] ?? 0), 2);

        if (($bankKhr > 0 || $bankUsd > 0) && empty($data['bank_name'])) {
            return response()->json(['code' => 'bank_required', 'message' => 'Choose the bank the receiver paid to.'], 422);
        }

        $rate     = max(1, (int) config('ride.khr_per_usd', 4100));
        $expected = (int) $delivery->package_amount;
        $total    = $cashKhr + $bankKhr + (int) round(($cashUsd + $bankUsd) * $rate);
        $diff     = $total - $expected;

        // Small rounding (< 100 ៛) is fine; anything else needs a reason.
        if (abs($diff) >= 100 && trim((string) ($data['note'] ?? '')) === '') {
            return response()->json([
                'code' => 'note_required', 'difference_khr' => $diff, 'expected_khr' => $expected, 'total_khr' => $total,
                'message' => 'The amount received does not match the COD. Add a note explaining the difference.',
            ], 422);
        }

        $collection = DB::transaction(fn () => DeliveryCollection::create([
            'delivery_id'   => $delivery->id,
            'driver_id'     => $user->id,
            'partner_id'    => $delivery->partner_id,
            'business_date' => now(PS::tz())->toDateString(),
            'expected_khr'  => $expected,
            'cash_khr'      => $cashKhr,
            'cash_usd'      => $cashUsd,
            'bank_khr'      => $bankKhr,
            'bank_usd'      => $bankUsd,
            'bank_name'     => $data['bank_name'] ?? null,
            'bank_ref'      => $data['bank_ref'] ?? null,
            'khr_per_usd'   => $rate,
            'total_khr'     => $total,
            'difference_khr' => $diff,
            'note'          => $data['note'] ?? null,
        ]));

        // Complete through the normal path (settlement, realtime, notifications).
        $response = app(DeliveryController::class)->complete($request, $delivery->fresh());
        $payload  = json_decode($response->getContent(), true) ?? [];

        return response()->json([
            'data' => [
                'collection' => $this->format($collection),
                'delivery'   => $payload['data']['delivery'] ?? null,
            ],
            'message' => 'Payment verified. Package completed.',
        ], $response->getStatusCode() < 300 ? 200 : $response->getStatusCode());
    }

    private function format(DeliveryCollection $c): array
    {
        return $c->only(['id', 'delivery_id', 'expected_khr', 'cash_khr', 'cash_usd', 'bank_khr', 'bank_usd',
            'bank_name', 'bank_ref', 'khr_per_usd', 'total_khr', 'difference_khr', 'note']) + ['method' => $c->methodLabel()];
    }
}

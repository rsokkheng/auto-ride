<?php

namespace App\Http\Controllers\Api;

use App\Models\Delivery;
use App\Models\DeliveryCollection;
use App\Services\PartnerPackageWorkflow;
use Illuminate\Http\Request;

/**
 * POST /v1/deliveries/{delivery}/collect
 * Staff driver verifies the money received at handover — any mix of cash ៛,
 * cash $, bank ៛, bank $ — then the Partner package is completed.
 * Requires the handover QR scan first.
 */
class DeliveryCollectionController extends ApiController
{
    public const BANKS = PartnerPackageWorkflow::BANKS;

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
        if ($delivery->onPickupLeg()) {
            return response()->json(['code' => 'pickup_leg', 'message' => 'Bring this package to the ROTEH office first — it is not out for delivery yet.'], 422);
        }
        if (! in_array($delivery->status, ['picked_up', 'in_progress', 'in_transit', 'delivery_failed'], true)) {
            return response()->json(['message' => 'This package is not on its way — status "' . $delivery->status . '".'], 422);
        }
        if (! $delivery->delivery_scanned_at) {
            return response()->json(['code' => 'scan_required', 'message' => 'Scan the package QR code at handover first.'], 422);
        }

        $data = $request->validate(self::rules());
        $result = app(PartnerPackageWorkflow::class)->collect($delivery, $user->id, $data);
        if (isset($result['error'])) return response()->json($result['error'], 422);

        // Complete through the normal path (settlement, realtime, notifications).
        $response = app(DeliveryController::class)->complete($request, $delivery->fresh());
        $payload  = json_decode($response->getContent(), true) ?? [];

        return response()->json([
            'data' => [
                'collection' => $this->format($result['collection']),
                'delivery'   => $payload['data']['delivery'] ?? null,
            ],
            'message' => 'Payment verified. Package completed.',
        ], $response->getStatusCode() < 300 ? 200 : $response->getStatusCode());
    }

    /** Money received at handover — shared with the admin "Completed" form. */
    public static function rules(): array
    {
        return [
            'cash_khr'  => 'nullable|integer|min:0|max:100000000',
            'cash_usd'  => 'nullable|numeric|min:0|max:100000',
            'bank_khr'  => 'nullable|integer|min:0|max:100000000',
            'bank_usd'  => 'nullable|numeric|min:0|max:100000',
            'bank_name' => 'nullable|in:' . implode(',', PartnerPackageWorkflow::BANKS),
            'bank_ref'  => 'nullable|string|max:100',
            'note'      => 'nullable|string|max:500',
        ];
    }

    private function format(DeliveryCollection $c): array
    {
        return $c->only(['id', 'delivery_id', 'expected_khr', 'cash_khr', 'cash_usd', 'bank_khr', 'bank_usd',
            'bank_name', 'bank_ref', 'khr_per_usd', 'total_khr', 'difference_khr', 'note']) + ['method' => $c->methodLabel()];
    }
}

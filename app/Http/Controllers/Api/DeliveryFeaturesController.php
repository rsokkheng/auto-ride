<?php

namespace App\Http\Controllers\Api;

use App\Models\Delivery;
use App\Models\DeliveryStop;
use App\Services\FirestoreService;
use Illuminate\Http\Request;

class DeliveryFeaturesController extends ApiController
{
    public function __construct(private FirestoreService $firestore) {}

    // ── Multi-stop delivery ───────────────────────────────────────────────────

    public function stops(Request $request, Delivery $delivery)
    {
        $user = $this->authUser($request);
        if (! $user || ! in_array($user->id, [$delivery->sender_id, $delivery->driver_id], true)) {
            return $this->unauthorized();
        }
        return $this->success(['stops' => $delivery->stops]);
    }

    public function addStops(Request $request, Delivery $delivery)
    {
        $user = $this->authUser($request);
        if (! $user || $delivery->sender_id !== $user->id) return $this->unauthorized();

        if (! in_array($delivery->status, ['requested', 'accepted'], true)) {
            return response()->json(['data' => null, 'message' => 'Stops can only be added before pickup.'], 422);
        }

        $data = $request->validate([
            'stops'                   => 'required|array|min:1|max:10',
            'stops.*.address'         => 'required|string|max:255',
            'stops.*.lat'             => 'nullable|numeric',
            'stops.*.lng'             => 'nullable|numeric',
            'stops.*.recipient_name'  => 'nullable|string|max:100',
            'stops.*.recipient_phone' => 'nullable|string|max:24',
            'stops.*.notes'           => 'nullable|string|max:255',
        ]);

        $delivery->stops()->delete();

        $rows = collect($data['stops'])->map(fn($s, $i) => [
            'delivery_id'     => $delivery->id,
            'address'         => $s['address'],
            'lat'             => $s['lat'] ?? null,
            'lng'             => $s['lng'] ?? null,
            'recipient_name'  => $s['recipient_name'] ?? null,
            'recipient_phone' => $s['recipient_phone'] ?? null,
            'notes'           => $s['notes'] ?? null,
            'sort_order'      => $i + 1,
            'status'          => 'pending',
            'created_at'      => now(),
            'updated_at'      => now(),
        ])->toArray();

        DeliveryStop::insert($rows);

        return $this->success(['stops' => $delivery->stops()->get()]);
    }

    // ── Proof of delivery ─────────────────────────────────────────────────────

    public function uploadProof(Request $request, Delivery $delivery)
    {
        $user = $this->authUser($request);
        if (! $user || $delivery->driver_id !== $user->id) return $this->unauthorized();

        $request->validate([
            'photo' => 'required|image|max:5120',
        ]);

        $path = $request->file('photo')->store('delivery-proofs', 'public');
        $delivery->update([
            'proof_photo' => $path,
            'status'      => 'completed',
        ]);

        $this->firestore->syncDelivery($delivery->fresh());

        return $this->success([
            'proof_photo_url' => asset('storage/' . $path),
        ]);
    }

    public function uploadStopProof(Request $request, Delivery $delivery, DeliveryStop $stop)
    {
        $user = $this->authUser($request);
        if (! $user || $delivery->driver_id !== $user->id || $stop->delivery_id !== $delivery->id) {
            return $this->unauthorized();
        }

        $request->validate(['photo' => 'required|image|max:5120']);

        $path = $request->file('photo')->store('delivery-proofs', 'public');

        $stop->update([
            'proof_photo'  => $path,
            'status'       => 'delivered',
            'delivered_at' => now(),
        ]);

        // Mark whole delivery completed if all stops are done
        if ($delivery->stops()->where('status', 'pending')->doesntExist()) {
            $delivery->update(['status' => 'completed']);
            $this->firestore->syncDelivery($delivery->fresh());
        }

        return $this->success([
            'stop'            => $stop->fresh(),
            'proof_photo_url' => asset('storage/' . $path),
        ]);
    }

    // ── Live driver location (returns current driver coords) ──────────────────

    public function driverLocation(Request $request, Delivery $delivery)
    {
        $user = $this->authUser($request);
        if (! $user || $delivery->sender_id !== $user->id) return $this->unauthorized();

        if (! $delivery->driver) {
            return response()->json(['data' => null, 'message' => 'No driver assigned.'], 404);
        }

        return $this->success([
            'driver' => [
                'id'     => $delivery->driver->id,
                'name'   => $delivery->driver->name,
                'phone'  => $delivery->driver->phone,
                'rating' => $delivery->driver->rating,
                'lat'    => $delivery->driver->current_latitude,
                'lng'    => $delivery->driver->current_longitude,
            ],
        ]);
    }
}

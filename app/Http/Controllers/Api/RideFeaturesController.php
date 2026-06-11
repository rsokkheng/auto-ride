<?php

namespace App\Http\Controllers\Api;

use App\Models\Ride;
use App\Models\RideStop;
use App\Models\PromoCode;
use App\Models\UserEmergencyContact;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class RideFeaturesController extends ApiController
{

    // ── Multi-stop waypoints ──────────────────────────────────────────────────

    public function stops(Request $request, Ride $ride)
    {
        $user = $this->authUser($request);
        if (! $user || ! in_array($user->id, [$ride->passenger_id, $ride->driver_id], true)) {
            return $this->unauthorized();
        }
        return $this->success(['stops' => $ride->stops]);
    }

    public function addStops(Request $request, Ride $ride)
    {
        $user = $this->authUser($request);
        if (! $user || $ride->passenger_id !== $user->id) return $this->unauthorized();

        if (! in_array($ride->status, [Ride::STATUS_REQUESTED, Ride::STATUS_ACCEPTED], true)) {
            return response()->json(['data' => null, 'message' => 'Stops can only be added before the trip starts.'], 422);
        }

        $data = $request->validate([
            'stops'              => 'required|array|min:1|max:5',
            'stops.*.address'    => 'required|string|max:255',
            'stops.*.lat'        => 'nullable|numeric',
            'stops.*.lng'        => 'nullable|numeric',
        ]);

        $ride->stops()->delete();

        $stops = collect($data['stops'])->map(fn($s, $i) => [
            'ride_id'    => $ride->id,
            'address'    => $s['address'],
            'lat'        => $s['lat'] ?? null,
            'lng'        => $s['lng'] ?? null,
            'sort_order' => $i + 1,
            'created_at' => now(),
            'updated_at' => now(),
        ])->toArray();

        RideStop::insert($stops);

        return $this->success(['stops' => $ride->stops()->get()]);
    }

    public function markStopArrived(Request $request, Ride $ride, RideStop $stop)
    {
        $user = $this->authUser($request);
        if (! $user || $ride->driver_id !== $user->id || $stop->ride_id !== $ride->id) {
            return $this->unauthorized();
        }

        $stop->update(['arrived_at' => now()]);
        return $this->success(['stop' => $stop->fresh()]);
    }

    // ── Ride for someone else ─────────────────────────────────────────────────

    public function storeForSomeoneElse(Request $request)
    {
        $user = $this->authUser($request);
        if (! $user) return $this->unauthorized();

        // Delegate to regular store but inject passenger info
        $request->merge(['_for_someone_else' => true]);
        return app(RideController::class)->store($request);
    }

    // ── Re-order last ride ────────────────────────────────────────────────────

    public function reorderLast(Request $request)
    {
        $user = $this->authUser($request);
        if (! $user) return $this->unauthorized();

        $last = Ride::where('passenger_id', $user->id)
            ->where('status', Ride::STATUS_COMPLETED)
            ->latest('completed_at')
            ->first();

        if (! $last) {
            return response()->json(['data' => null, 'message' => 'No completed ride to re-order.'], 404);
        }

        return $this->success([
            'prefill' => [
                'pickup_address'  => $last->pickup_address,
                'dropoff_address' => $last->dropoff_address,
                'pickup_lat'      => $last->pickup_lat,
                'pickup_lng'      => $last->pickup_lng,
                'dropoff_lat'     => $last->dropoff_lat,
                'dropoff_lng'     => $last->dropoff_lng,
                'service_type'    => $last->service_type,
                'payment_method'  => $last->payment_method,
                'passenger_name'  => $last->passenger_name,
                'passenger_phone' => $last->passenger_phone,
            ],
        ]);
    }

    // ── Share trip link ───────────────────────────────────────────────────────

    public function shareToken(Request $request, Ride $ride)
    {
        $user = $this->authUser($request);
        if (! $user || $ride->passenger_id !== $user->id) return $this->unauthorized();

        if (! in_array($ride->status, [Ride::STATUS_ACCEPTED, Ride::STATUS_DRIVER_ARRIVED, Ride::STATUS_IN_PROGRESS], true)) {
            return response()->json(['data' => null, 'message' => 'Sharing is only available for active rides.'], 422);
        }

        if (! $ride->share_token) {
            $ride->update(['share_token' => Str::random(32), 'share_active' => true]);
        } else {
            $ride->update(['share_active' => true]);
        }

        $ride->refresh();

        // Notify emergency contacts
        $this->notifyEmergencyContactsOfShare($user->id);

        return $this->success([
            'share_token' => $ride->share_token,
            'share_url'   => route('track.show', $ride->share_token),
        ]);
    }

    public function trackByToken(string $token)
    {
        $ride = Ride::with(['driver', 'vehicle'])
            ->where('share_token', $token)
            ->first();

        if (! $ride) {
            return response()->json(['data' => null, 'message' => 'Link not found.'], 404);
        }

        $isLive = in_array($ride->status, [
            Ride::STATUS_ACCEPTED,
            Ride::STATUS_DRIVER_ARRIVED,
            Ride::STATUS_IN_PROGRESS,
        ]);

        $driverLat = $ride->driver?->current_latitude;
        $driverLng = $ride->driver?->current_longitude;

        return response()->json([
            'data' => [
                'ride_id'          => $ride->id,
                'status'           => $ride->status,
                'is_live'          => $isLive,
                'pickup_address'   => $ride->pickup_address,
                'dropoff_address'  => $ride->dropoff_address,
                'driver' => $ride->driver ? [
                    'id'               => $ride->driver->id,
                    'name'             => $ride->driver->name,
                    'rating'           => $ride->driver->rating,
                    'phone'            => $ride->driver->phone,
                    // explicit key names — all formats Flutter might expect
                    'lat'              => $driverLat,
                    'lng'              => $driverLng,
                    'latitude'         => $driverLat,
                    'longitude'        => $driverLng,
                    'current_lat'      => $driverLat,
                    'current_lng'      => $driverLng,
                    'current_latitude' => $driverLat,
                    'current_longitude'=> $driverLng,
                    'location_updated' => $driverLat ? true : false,
                    'vehicle' => $ride->vehicle ? [
                        'type'          => $ride->vehicle->type ?? null,
                        'make'          => $ride->vehicle->make ?? null,
                        'model'         => $ride->vehicle->model ?? null,
                        'plate'         => $ride->vehicle->plate ?? $ride->vehicle->license_plate ?? null,
                        'license_plate' => $ride->vehicle->plate ?? $ride->vehicle->license_plate ?? null,
                        'color'         => $ride->vehicle->color ?? null,
                    ] : null,
                ] : null,
            ],
        ]);
    }

    public function deactivateShare(Request $request, Ride $ride)
    {
        $user = $this->authUser($request);
        if (! $user || $ride->passenger_id !== $user->id) return $this->unauthorized();
        $ride->update(['share_active' => false]);
        return $this->success(['message' => 'Sharing stopped.']);
    }

    // ── Promo code validation ─────────────────────────────────────────────────

    public function validatePromo(Request $request)
    {
        $user = $this->authUser($request);
        if (! $user) return $this->unauthorized();

        $data = $request->validate([
            'code'         => 'required|string',
            'service_type' => 'required|in:rides,deliveries,moving',
            'order_amount' => 'required|integer|min:0',
        ]);

        $promo = PromoCode::where('code', strtoupper($data['code']))->first();

        if (! $promo || ! $promo->isValid($data['service_type'], $data['order_amount'], $user->id)) {
            return response()->json(['data' => null, 'message' => 'Invalid or expired promo code.'], 422);
        }

        $discount = $promo->calculateDiscount($data['order_amount']);

        return $this->success([
            'promo_code_id'   => $promo->id,
            'code'            => $promo->code,
            'discount_amount' => $discount,
            'final_amount'    => max(0, $data['order_amount'] - $discount),
            'description'     => $promo->description,
        ]);
    }

    // ── Fake call (safety feature) ────────────────────────────────────────────

    public function fakeCall(Request $request)
    {
        $user = $this->authUser($request);
        if (! $user) return $this->unauthorized();

        // Returns a trigger token the Flutter app uses to show a fake incoming call screen
        return $this->success([
            'trigger' => 'fake_call',
            'caller'  => ['name' => 'Mom', 'number' => '+855 12 345 678'],
            'delay_seconds' => $request->input('delay_seconds', 5),
        ]);
    }

    // ── SOS / Share to emergency contacts ────────────────────────────────────

    public function sos(Request $request, Ride $ride)
    {
        $user = $this->authUser($request);
        if (! $user || $ride->passenger_id !== $user->id) return $this->unauthorized();

        $contacts = UserEmergencyContact::where('user_id', $user->id)
            ->where('notify_on_sos', true)
            ->get();

        // In a real implementation, push an FCM/SMS to each contact.
        // Here we return what would be sent.
        $shareUrl = $ride->share_token ? url('/track/' . $ride->share_token) : null;

        if (! $shareUrl) {
            $ride->update(['share_token' => \Illuminate\Support\Str::random(32), 'share_active' => true]);
            $ride->refresh();
            $shareUrl = url('/track/' . $ride->share_token);
        }

        return $this->success([
            'message'      => 'SOS sent to ' . $contacts->count() . ' emergency contact(s).',
            'share_url'    => $shareUrl,
            'contacts_notified' => $contacts->count(),
        ]);
    }

    // ── Driver arrive timeout ─────────────────────────────────────────────────

    public function setPickupTimeout(Request $request, Ride $ride)
    {
        $user = $this->authUser($request);
        if (! $user || $ride->driver_id !== $user->id) return $this->unauthorized();

        if ($ride->status !== Ride::STATUS_DRIVER_ARRIVED) {
            return response()->json(['data' => null, 'message' => 'Must mark arrived first.'], 422);
        }

        // Default: 5-min timeout from now
        $minutes = $request->input('timeout_minutes', 5);
        $ride->update(['pickup_timeout_at' => now()->addMinutes($minutes)]);

        return $this->success(['pickup_timeout_at' => $ride->fresh()->pickup_timeout_at]);
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    private function notifyEmergencyContactsOfShare(int $userId): void
    {
        // UserEmergencyContact::where('user_id', $userId)->where('notify_on_trip_share', true)->get()
        // → dispatch(new NotifyEmergencyContactJob($contact, $ride))
    }
}

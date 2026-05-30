<?php

namespace App\Http\Controllers\Api;

use App\Models\Delivery;
use App\Models\Vehicle;
use Illuminate\Http\Request;

class DeliveryController extends ApiController
{
    public function index(Request $request)
    {
        $user = $this->authUser($request);

        if (! $user) {
            return $this->unauthorized();
        }

        if ($user->role === 'driver') {
            $deliveries = Delivery::with(['sender', 'vehicle'])->where('driver_id', $user->id)->paginate(20);
        } else {
            $deliveries = Delivery::with(['driver', 'vehicle'])->where('sender_id', $user->id)->paginate(20);
        }

        return $this->success(['deliveries' => $deliveries]);
    }

    public function history(Request $request)
    {
        return $this->index($request);
    }

    public function store(Request $request)
    {
        $user = $this->authUser($request);

        if (! $user) {
            return $this->unauthorized();
        }

        $data = $request->validate([
            'sender_name'     => 'required|string|max:255',
            'recipient_name'  => 'required|string|max:255',
            'recipient_phone' => 'required|string|max:24',
            'package_size'    => 'required|in:small,medium,large',
            'pickup_address'  => 'required|string|max:255',
            'dropoff_address' => 'required|string|max:255',
            'scheduled_at'    => 'nullable|date',
            'package_details' => 'nullable|string|max:500',
            'fee'             => 'nullable|numeric|min:0',
            'notes'           => 'nullable|string',
            'vehicle_id'      => 'nullable|exists:vehicles,id',
        ]);

        if (! empty($data['vehicle_id'])) {
            $vehicle = Vehicle::find($data['vehicle_id']);
            $data['driver_id'] = $vehicle?->user_id;
        }

        $delivery = Delivery::create(array_merge($data, [
            'sender_id' => $user->id,
            'status' => 'requested',
            'fee' => $data['fee'] ?? 0,
        ]));

        return $this->success(['delivery' => $delivery->load('driver', 'vehicle')], 201);
    }

    public function accept(Request $request, Delivery $delivery)
    {
        $user = $this->authUser($request);

        if (! $user || $user->role !== 'driver') {
            return $this->unauthorized();
        }

        if ($delivery->driver_id && $delivery->driver_id !== $user->id) {
            return response()->json(['message' => 'Delivery already claimed'], 422);
        }

        $delivery->update([
            'driver_id' => $user->id,
            'status' => 'accepted',
        ]);

        return $this->success(['delivery' => $delivery->fresh()->load('sender', 'vehicle')]);
    }

    public function estimate(Request $request)
    {
        $data = $request->validate([
            'distance' => 'nullable|numeric|min:0',
            'package_weight' => 'nullable|numeric|min:0',
        ]);

        $distance = $data['distance'] ?? 5;
        $weight = $data['package_weight'] ?? 2;
        $fee = round(3 + ($distance * 1.5) + ($weight * 0.5), 2);

        return $this->success(['estimated_fee' => $fee]);
    }

    public function track(Request $request, Delivery $delivery)
    {
        $user = $this->authUser($request);

        if (! $user || ! in_array($user->id, [$delivery->sender_id, $delivery->driver_id], true)) {
            return $this->unauthorized();
        }

        return $this->success([
            'delivery' => $delivery->load('driver', 'vehicle'),
            'tracking' => [
                'status' => $delivery->status,
                'eta_minutes' => 12,
                'driver' => $delivery->driver?->only(['id', 'name', 'phone']),
            ],
        ]);
    }

    public function cancel(Request $request, Delivery $delivery)
    {
        $user = $this->authUser($request);

        if (! $user || ! in_array($user->id, [$delivery->sender_id, $delivery->driver_id], true)) {
            return $this->unauthorized();
        }

        if (in_array($delivery->status, ['completed', 'cancelled'], true)) {
            return response()->json(['message' => 'Delivery cannot be cancelled'], 422);
        }

        $delivery->update(['status' => 'cancelled']);

        return $this->success(['delivery' => $delivery]);
    }

    public function confirm(Request $request, Delivery $delivery)
    {
        $user = $this->authUser($request);

        if (! $user || $delivery->sender_id !== $user->id) {
            return $this->unauthorized();
        }

        $delivery->update(['status' => 'completed']);

        return $this->success(['delivery' => $delivery]);
    }

    public function complete(Request $request, Delivery $delivery)
    {
        return $this->confirm($request, $delivery);
    }
}

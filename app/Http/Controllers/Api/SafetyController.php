<?php

namespace App\Http\Controllers\Api;

use App\Models\SafetyIncident;
use App\Models\SOSAlert;
use Illuminate\Http\Request;

class SafetyController extends ApiController
{
    public function index(Request $request)
    {
        $user = $this->authUser($request);

        if (! $user) {
            return $this->unauthorized();
        }

        $incidents = SafetyIncident::where('user_id', $user->id)->orderByDesc('reported_at')->get();

        return $this->success(['safety_incidents' => $incidents]);
    }

    public function store(Request $request)
    {
        $user = $this->authUser($request);

        if (! $user) {
            return $this->unauthorized();
        }

        $data = $request->validate([
            'incident_type' => 'required|string|max:120',
            'description' => 'required|string|max:1000',
            'ride_id' => 'nullable|exists:rides,id',
            'delivery_id' => 'nullable|exists:deliveries,id',
            'status' => 'nullable|string|in:pending,resolved',
        ]);

        $incident = SafetyIncident::create(array_merge($data, [
            'user_id' => $user->id,
            'reported_at' => now(),
            'status' => $data['status'] ?? 'pending',
        ]));

        return $this->success(['incident' => $incident], 201);
    }

    public function sos(Request $request)
    {
        $user = $this->authUser($request);

        if (! $user) {
            return $this->unauthorized();
        }

        $data = $request->validate([
            'ride_id' => 'nullable|exists:rides,id',
            'delivery_id' => 'nullable|exists:deliveries,id',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'message' => 'nullable|string|max:1000',
        ]);

        $alert = SOSAlert::create(array_merge($data, [
            'user_id' => $user->id,
            'status' => 'pending',
        ]));

        return $this->success(['alert' => $alert], 201);
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\UserSavedPlace;
use App\Models\UserEmergencyContact;
use Illuminate\Http\Request;

class UserProfileController extends Controller
{
    // ── Saved Places ──────────────────────────────────────────────────────────

    public function savedPlaces(Request $request)
    {
        return response()->json([
            'data' => $request->user()->savedPlaces()->orderByDesc('is_default')->orderBy('label')->get(),
        ]);
    }

    public function storeSavedPlace(Request $request)
    {
        $data = $request->validate([
            'label'      => 'required|string|max:64',
            'address'    => 'required|string|max:255',
            'lat'        => 'nullable|numeric',
            'lng'        => 'nullable|numeric',
            'icon'       => 'nullable|string|max:32',
            'is_default' => 'nullable|boolean',
        ]);

        $data['user_id'] = $request->user()->id;

        if (! empty($data['is_default'])) {
            $request->user()->savedPlaces()->update(['is_default' => false]);
        }

        $place = UserSavedPlace::create($data);

        return response()->json(['data' => $place], 201);
    }

    public function updateSavedPlace(Request $request, UserSavedPlace $place)
    {
        abort_if($place->user_id !== $request->user()->id, 403);

        $data = $request->validate([
            'label'      => 'sometimes|string|max:64',
            'address'    => 'sometimes|string|max:255',
            'lat'        => 'nullable|numeric',
            'lng'        => 'nullable|numeric',
            'icon'       => 'nullable|string|max:32',
            'is_default' => 'nullable|boolean',
        ]);

        if (! empty($data['is_default'])) {
            $request->user()->savedPlaces()->where('id', '!=', $place->id)->update(['is_default' => false]);
        }

        $place->update($data);

        return response()->json(['data' => $place->fresh()]);
    }

    public function destroySavedPlace(Request $request, UserSavedPlace $place)
    {
        abort_if($place->user_id !== $request->user()->id, 403);
        $place->delete();
        return response()->json(['message' => 'Deleted']);
    }

    // ── Emergency Contacts ────────────────────────────────────────────────────

    public function emergencyContacts(Request $request)
    {
        return response()->json([
            'data' => $request->user()->emergencyContacts()->get(),
        ]);
    }

    public function storeEmergencyContact(Request $request)
    {
        $data = $request->validate([
            'name'                 => 'required|string|max:100',
            'phone'                => 'required|string|max:24',
            'relationship'         => 'nullable|string|max:50',
            'notify_on_sos'        => 'nullable|boolean',
            'notify_on_trip_share' => 'nullable|boolean',
        ]);

        $data['user_id'] = $request->user()->id;

        $contact = UserEmergencyContact::create($data);

        return response()->json(['data' => $contact], 201);
    }

    public function updateEmergencyContact(Request $request, UserEmergencyContact $contact)
    {
        abort_if($contact->user_id !== $request->user()->id, 403);

        $data = $request->validate([
            'name'                 => 'sometimes|string|max:100',
            'phone'                => 'sometimes|string|max:24',
            'relationship'         => 'nullable|string|max:50',
            'notify_on_sos'        => 'nullable|boolean',
            'notify_on_trip_share' => 'nullable|boolean',
        ]);

        $contact->update($data);

        return response()->json(['data' => $contact->fresh()]);
    }

    public function destroyEmergencyContact(Request $request, UserEmergencyContact $contact)
    {
        abort_if($contact->user_id !== $request->user()->id, 403);
        $contact->delete();
        return response()->json(['message' => 'Deleted']);
    }
}

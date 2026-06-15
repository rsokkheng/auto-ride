<?php

namespace App\Http\Controllers\Api;

use App\Models\UserSavedPlace;
use App\Models\UserEmergencyContact;
use Illuminate\Http\Request;

class UserProfileController extends ApiController
{
    // ── Saved Places ──────────────────────────────────────────────────────────

    public function savedPlaces(Request $request)
    {
        $user = $this->authUser($request);
        if (! $user) return $this->unauthorized();

        return $this->success([
            'saved_places' => $user->savedPlaces()->orderByDesc('is_default')->orderBy('label')->get(),
        ]);
    }

    public function storeSavedPlace(Request $request)
    {
        $user = $this->authUser($request);
        if (! $user) return $this->unauthorized();

        $data = $request->validate([
            'label'      => 'required|string|max:64',
            'address'    => 'required|string|max:255',
            'lat'        => 'nullable|numeric',
            'lng'        => 'nullable|numeric',
            'icon'       => 'nullable|string|max:32',
            'is_default' => 'nullable|boolean',
        ]);

        $data['user_id'] = $user->id;

        if (! empty($data['is_default'])) {
            $user->savedPlaces()->update(['is_default' => false]);
        }

        $place = UserSavedPlace::create($data);

        return $this->success(['saved_place' => $place], 201);
    }

    public function updateSavedPlace(Request $request, UserSavedPlace $place)
    {
        $user = $this->authUser($request);
        if (! $user || $place->user_id !== $user->id) return $this->unauthorized();

        $data = $request->validate([
            'label'      => 'sometimes|string|max:64',
            'address'    => 'sometimes|string|max:255',
            'lat'        => 'nullable|numeric',
            'lng'        => 'nullable|numeric',
            'icon'       => 'nullable|string|max:32',
            'is_default' => 'nullable|boolean',
        ]);

        if (! empty($data['is_default'])) {
            $user->savedPlaces()->where('id', '!=', $place->id)->update(['is_default' => false]);
        }

        $place->update($data);

        return $this->success(['saved_place' => $place->fresh()]);
    }

    public function destroySavedPlace(Request $request, UserSavedPlace $place)
    {
        $user = $this->authUser($request);
        if (! $user || $place->user_id !== $user->id) return $this->unauthorized();

        $place->delete();

        return $this->success(['message' => 'Saved place deleted.']);
    }

    // ── Emergency Contacts ────────────────────────────────────────────────────

    public function emergencyContacts(Request $request)
    {
        $user = $this->authUser($request);
        if (! $user) return $this->unauthorized();

        return $this->success([
            'emergency_contacts' => $user->emergencyContacts()->get(),
        ]);
    }

    public function storeEmergencyContact(Request $request)
    {
        $user = $this->authUser($request);
        if (! $user) return $this->unauthorized();

        $data = $request->validate([
            'name'                 => 'required|string|max:100',
            'phone'                => 'required|string|max:24',
            'relationship'         => 'nullable|string|max:50',
            'notify_on_sos'        => 'nullable|boolean',
            'notify_on_trip_share' => 'nullable|boolean',
        ]);

        $data['user_id'] = $user->id;

        $contact = UserEmergencyContact::create($data);

        return $this->success(['emergency_contact' => $contact], 201);
    }

    public function updateEmergencyContact(Request $request, UserEmergencyContact $contact)
    {
        $user = $this->authUser($request);
        if (! $user || $contact->user_id !== $user->id) return $this->unauthorized();

        $data = $request->validate([
            'name'                 => 'sometimes|string|max:100',
            'phone'                => 'sometimes|string|max:24',
            'relationship'         => 'nullable|string|max:50',
            'notify_on_sos'        => 'nullable|boolean',
            'notify_on_trip_share' => 'nullable|boolean',
        ]);

        $contact->update($data);

        return $this->success(['emergency_contact' => $contact->fresh()]);
    }

    public function destroyEmergencyContact(Request $request, UserEmergencyContact $contact)
    {
        $user = $this->authUser($request);
        if (! $user || $contact->user_id !== $user->id) return $this->unauthorized();

        $contact->delete();

        return $this->success(['message' => 'Emergency contact deleted.']);
    }
}

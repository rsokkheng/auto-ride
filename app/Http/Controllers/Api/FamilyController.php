<?php

namespace App\Http\Controllers\Api;

use App\Models\FamilyGroup;
use App\Models\FamilyMember;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FamilyController extends ApiController
{
    // ── Get family group and members ──────────────────────────────────────────

    public function index(): JsonResponse
    {
        $user  = $this->authUser();
        $group = FamilyGroup::with('members')->where('owner_user_id', $user->id)->first();

        if (! $group) {
            return response()->json(['data' => null, 'message' => 'No family group yet.']);
        }

        return response()->json([
            'data' => [
                'id'      => $group->id,
                'name'    => $group->name,
                'members' => $group->members->map(fn ($m) => $this->formatMember($m)),
            ],
        ]);
    }

    // ── Create or rename family group ─────────────────────────────────────────

    public function setup(Request $request): JsonResponse
    {
        $user = $this->authUser();
        $data = $request->validate(['name' => 'required|string|max:80']);

        $group = FamilyGroup::firstOrCreate(
            ['owner_user_id' => $user->id],
            ['name' => $data['name']]
        );

        if (! $group->wasRecentlyCreated) {
            $group->update(['name' => $data['name']]);
        }

        return response()->json([
            'data'    => ['id' => $group->id, 'name' => $group->name],
            'message' => $group->wasRecentlyCreated ? 'Family group created.' : 'Family group updated.',
        ]);
    }

    // ── Add a family member ───────────────────────────────────────────────────

    public function addMember(Request $request): JsonResponse
    {
        $user = $this->authUser();
        $data = $request->validate([
            'name'         => 'required|string|max:100',
            'phone'        => 'required|string|max:20',
            'relationship' => 'nullable|string|max:40',
        ]);

        $group = FamilyGroup::firstOrCreate(
            ['owner_user_id' => $user->id],
            ['name' => 'My Family']
        );

        // Cap at 10 members
        if ($group->members()->count() >= 10) {
            return response()->json(['message' => 'Maximum of 10 family members allowed.'], 422);
        }

        // Link to existing user account if phone matches
        $linkedUser = \App\Models\User::where('phone', $data['phone'])->first();

        $member = FamilyMember::create([
            'family_group_id' => $group->id,
            'user_id'         => $linkedUser?->id,
            'name'            => $data['name'],
            'phone'           => $data['phone'],
            'relationship'    => $data['relationship'] ?? null,
        ]);

        return response()->json(['data' => $this->formatMember($member), 'message' => 'Family member added.'], 201);
    }

    // ── Update a family member ────────────────────────────────────────────────

    public function updateMember(Request $request, FamilyMember $member): JsonResponse
    {
        $user  = $this->authUser();
        $group = FamilyGroup::where('owner_user_id', $user->id)->first();

        if (! $group || $member->family_group_id !== $group->id) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $data = $request->validate([
            'name'         => 'sometimes|string|max:100',
            'phone'        => 'sometimes|string|max:20',
            'relationship' => 'nullable|string|max:40',
        ]);

        if (isset($data['phone'])) {
            $data['user_id'] = \App\Models\User::where('phone', $data['phone'])->value('id');
        }

        $member->update($data);

        return response()->json(['data' => $this->formatMember($member->fresh()), 'message' => 'Member updated.']);
    }

    // ── Remove a family member ────────────────────────────────────────────────

    public function removeMember(FamilyMember $member): JsonResponse
    {
        $user  = $this->authUser();
        $group = FamilyGroup::where('owner_user_id', $user->id)->first();

        if (! $group || $member->family_group_id !== $group->id) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $member->delete();

        return response()->json(['message' => 'Member removed.']);
    }

    // ── Helper ────────────────────────────────────────────────────────────────

    private function formatMember(FamilyMember $m): array
    {
        return [
            'id'           => $m->id,
            'name'         => $m->name,
            'phone'        => $m->phone,
            'relationship' => $m->relationship,
            'avatar_url'   => $m->avatar_url,
            'has_account'  => $m->user_id !== null,
        ];
    }
}

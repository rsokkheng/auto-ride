<?php

namespace App\Http\Controllers\Api;

use App\Models\BusinessAccount;
use App\Models\BusinessMember;
use App\Models\Ride;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BusinessController extends ApiController
{
    public function myAccount(Request $request): JsonResponse
    {
        $user = $this->authUser($request);
        if (! $user) return $this->unauthorized();

        $member = BusinessMember::with('account.owner')
            ->where('user_id', $user->id)
            ->where('active', true)
            ->first();

        if (! $member) {
            return response()->json(['data' => null, 'message' => 'You are not a member of any business account.'], 404);
        }

        $account = $member->account;

        return response()->json([
            'data' => [
                'account'        => $this->formatAccount($account),
                'my_role'        => $member->role,
                'my_department'  => $member->department,
                'my_cost_center' => $member->cost_center,
                'monthly_limit'  => $member->monthly_limit_khr,
                'joined_at'      => $member->joined_at,
            ],
        ]);
    }

    public function register(Request $request): JsonResponse
    {
        $user = $this->authUser($request);
        if (! $user) return $this->unauthorized();

        $existing = BusinessMember::where('user_id', $user->id)->where('active', true)->first();
        if ($existing) {
            return response()->json(['message' => 'You are already a member of a business account. Leave it first.'], 422);
        }

        $data = $request->validate([
            'name'          => 'required|string|max:150',
            'tax_id'        => 'nullable|string|max:50',
            'industry'      => 'nullable|string|max:60',
            'contact_name'  => 'nullable|string|max:100',
            'contact_phone' => 'nullable|string|max:20',
            'billing_email' => 'nullable|email|max:150',
            'billing_cycle' => 'in:weekly,monthly',
            'address'       => 'nullable|string|max:255',
        ]);

        $account = BusinessAccount::create([
            ...$data,
            'code'          => BusinessAccount::generateCode(),
            'owner_user_id' => $user->id,
        ]);

        BusinessMember::create([
            'business_account_id' => $account->id,
            'user_id'             => $user->id,
            'role'                => 'admin',
            'joined_at'           => now(),
        ]);

        return response()->json(['data' => $this->formatAccount($account), 'message' => 'Business account created.'], 201);
    }

    public function join(Request $request): JsonResponse
    {
        $user = $this->authUser($request);
        if (! $user) return $this->unauthorized();

        $data = $request->validate(['code' => 'required|string|size:8']);

        $existing = BusinessMember::where('user_id', $user->id)->where('active', true)->first();
        if ($existing) {
            return response()->json(['message' => 'Already a member of a business account.'], 422);
        }

        $account = BusinessAccount::where('code', strtoupper($data['code']))->where('active', true)->first();
        if (! $account) {
            return response()->json(['message' => 'Invalid or inactive business code.'], 404);
        }

        BusinessMember::create([
            'business_account_id' => $account->id,
            'user_id'             => $user->id,
            'role'                => 'member',
            'joined_at'           => now(),
        ]);

        return response()->json(['data' => $this->formatAccount($account), 'message' => 'Joined business account.']);
    }

    public function members(Request $request): JsonResponse
    {
        $user   = $this->authUser($request);
        if (! $user) return $this->unauthorized();

        $member = $this->requireAdminMember($user->id);
        if ($member instanceof JsonResponse) return $member;

        $members = BusinessMember::with('user:id,name,email,phone,avatar')
            ->where('business_account_id', $member->business_account_id)
            ->get()
            ->map(fn ($m) => [
                'id'            => $m->id,
                'user'          => $m->user ? ['id' => $m->user->id, 'name' => $m->user->name, 'email' => $m->user->email, 'phone' => $m->user->phone] : null,
                'role'          => $m->role,
                'department'    => $m->department,
                'cost_center'   => $m->cost_center,
                'employee_id'   => $m->employee_id,
                'monthly_limit' => $m->monthly_limit_khr,
                'active'        => $m->active,
                'joined_at'     => $m->joined_at,
            ]);

        return response()->json(['data' => $members]);
    }

    public function updateMember(Request $request, BusinessMember $bMember): JsonResponse
    {
        $user   = $this->authUser($request);
        if (! $user) return $this->unauthorized();

        $member = $this->requireAdminMember($user->id);
        if ($member instanceof JsonResponse) return $member;

        if ($bMember->business_account_id !== $member->business_account_id) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $data = $request->validate([
            'role'              => 'in:admin,member',
            'department'        => 'nullable|string|max:80',
            'cost_center'       => 'nullable|string|max:60',
            'employee_id'       => 'nullable|string|max:40',
            'monthly_limit_khr' => 'nullable|integer|min:0',
            'active'            => 'boolean',
        ]);

        $bMember->update($data);

        return response()->json(['data' => $bMember->fresh(), 'message' => 'Member updated.']);
    }

    public function removeMember(Request $request, BusinessMember $bMember): JsonResponse
    {
        $user   = $this->authUser($request);
        if (! $user) return $this->unauthorized();

        $member = $this->requireAdminMember($user->id);
        if ($member instanceof JsonResponse) return $member;

        if ($bMember->business_account_id !== $member->business_account_id) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        if ($bMember->user_id === $bMember->account->owner_user_id) {
            return response()->json(['message' => 'Cannot remove the account owner.'], 422);
        }

        $bMember->delete();

        return response()->json(['message' => 'Member removed.']);
    }

    public function leave(Request $request): JsonResponse
    {
        $user = $this->authUser($request);
        if (! $user) return $this->unauthorized();

        $member = BusinessMember::where('user_id', $user->id)->where('active', true)->first();
        if (! $member) {
            return response()->json(['message' => 'Not a member of any business account.'], 404);
        }

        if ($member->account->owner_user_id === $user->id) {
            return response()->json(['message' => 'Owner cannot leave. Transfer ownership or delete the account.'], 422);
        }

        $member->delete();

        return response()->json(['message' => 'Left business account.']);
    }

    public function trips(Request $request): JsonResponse
    {
        $user   = $this->authUser($request);
        if (! $user) return $this->unauthorized();

        $member = BusinessMember::where('user_id', $user->id)->where('active', true)->first();
        if (! $member) {
            return response()->json(['message' => 'Not a member of any business account.'], 404);
        }

        $query = Ride::with('passenger:id,name,phone')
            ->where('business_account_id', $member->business_account_id)
            ->where('status', 'completed');

        if ($request->filled('from')) {
            $query->where('completed_at', '>=', $request->input('from'));
        }
        if ($request->filled('to')) {
            $query->where('completed_at', '<=', $request->input('to'));
        }

        $rides    = $query->orderByDesc('completed_at')->paginate(20);
        $totalKhr = (clone $query)->sum('fare');

        return response()->json([
            'data'      => $rides,
            'total_khr' => (int) $totalKhr,
        ]);
    }

    public function updateAccount(Request $request): JsonResponse
    {
        $user   = $this->authUser($request);
        if (! $user) return $this->unauthorized();

        $member = $this->requireAdminMember($user->id);
        if ($member instanceof JsonResponse) return $member;

        $data = $request->validate([
            'name'          => 'sometimes|string|max:150',
            'tax_id'        => 'nullable|string|max:50',
            'industry'      => 'nullable|string|max:60',
            'contact_name'  => 'nullable|string|max:100',
            'contact_phone' => 'nullable|string|max:20',
            'billing_email' => 'nullable|email|max:150',
            'billing_cycle' => 'in:weekly,monthly',
            'address'       => 'nullable|string|max:255',
        ]);

        $member->account->update($data);

        return response()->json(['data' => $this->formatAccount($member->account->fresh()), 'message' => 'Account updated.']);
    }

    private function requireAdminMember(int $userId): BusinessMember|JsonResponse
    {
        $member = BusinessMember::with('account')
            ->where('user_id', $userId)
            ->where('role', 'admin')
            ->where('active', true)
            ->first();

        return $member ?? response()->json(['message' => 'Admin access required.'], 403);
    }

    private function formatAccount(BusinessAccount $account): array
    {
        return [
            'id'                       => $account->id,
            'name'                     => $account->name,
            'code'                     => $account->code,
            'industry'                 => $account->industry,
            'billing_cycle'            => $account->billing_cycle,
            'monthly_credit_limit_khr' => $account->monthly_credit_limit_khr,
            'used_credit_khr'          => $account->used_credit_khr,
            'remaining_credit_khr'     => $account->remainingCreditKhr(),
            'members_count'            => $account->members()->count(),
            'active'                   => $account->active,
        ];
    }
}

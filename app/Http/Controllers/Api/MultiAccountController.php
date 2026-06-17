<?php

namespace App\Http\Controllers\Api;

use App\Models\LinkedAccount;
use App\Models\User;
use Illuminate\Http\Request;

class MultiAccountController extends ApiController
{
    /** GET /v1/accounts — list accounts linked to the current user */
    public function index(Request $request)
    {
        $user = $this->authUser($request);
        if (! $user) return $this->unauthorized();

        $linked = LinkedAccount::with('linkedUser:id,name,email,role,avatar')
            ->where('primary_user_id', $user->id)
            ->get()
            ->map(fn ($la) => [
                'link_id'     => $la->id,
                'label'       => $la->label,
                'linked_at'   => $la->created_at->toISOString(),
                'user'        => array_merge($la->linkedUser->toArray(), [
                    'avatar_url' => $la->linkedUser->avatar_url,
                ]),
            ]);

        return $this->success($linked);
    }

    /**
     * POST /v1/accounts/link
     * Link another account using their email + password.
     * Body: { email, password, label? }
     */
    public function link(Request $request)
    {
        $user = $this->authUser($request);
        if (! $user) return $this->unauthorized();

        $data = $request->validate([
            'email'    => 'required|email',
            'password' => 'required|string',
            'label'    => 'nullable|string|max:60',
        ]);

        $target = User::where('email', $data['email'])->first();

        if (! $target || ! \Illuminate\Support\Facades\Hash::check($data['password'], $target->password)) {
            return response()->json(['data' => null, 'message' => 'Invalid credentials.'], 401);
        }

        if ($target->id === $user->id) {
            return response()->json(['data' => null, 'message' => 'Cannot link your own account.'], 422);
        }

        $existing = LinkedAccount::where('primary_user_id', $user->id)
            ->where('linked_user_id', $target->id)
            ->first();

        if ($existing) {
            return response()->json(['data' => null, 'message' => 'Account already linked.'], 422);
        }

        $la = LinkedAccount::create([
            'primary_user_id' => $user->id,
            'linked_user_id'  => $target->id,
            'label'           => $data['label'] ?? null,
        ]);

        return $this->success([
            'link_id' => $la->id,
            'label'   => $la->label,
            'user'    => ['id' => $target->id, 'name' => $target->name, 'role' => $target->role],
        ], 'Account linked.');
    }

    /**
     * POST /v1/accounts/switch/{link_id}
     * Switch to a linked account — returns a short-lived token for that user.
     */
    public function switchAccount(Request $request, int $linkId)
    {
        $user = $this->authUser($request);
        if (! $user) return $this->unauthorized();

        $la = LinkedAccount::where('id', $linkId)
            ->where('primary_user_id', $user->id)
            ->with('linkedUser')
            ->first();

        if (! $la) {
            return response()->json(['data' => null, 'message' => 'Linked account not found.'], 404);
        }

        $target = $la->linkedUser;
        $target->update([
            'api_token'                => bin2hex(random_bytes(40)),
            'token_expires_at'         => now()->addHours(8),
            'refresh_token'            => bin2hex(random_bytes(40)),
            'refresh_token_expires_at' => now()->addDays(1),
        ]);

        return $this->success([
            'access_token' => $target->api_token,
            'token_type'   => 'Bearer',
            'expires_in'   => 8 * 3600,
            'user'         => array_merge($target->fresh()->toArray(), ['avatar_url' => $target->avatar_url]),
        ]);
    }

    /** DELETE /v1/accounts/{link_id} — unlink an account */
    public function unlink(Request $request, int $linkId)
    {
        $user = $this->authUser($request);
        if (! $user) return $this->unauthorized();

        $deleted = LinkedAccount::where('id', $linkId)
            ->where('primary_user_id', $user->id)
            ->delete();

        if (! $deleted) {
            return response()->json(['data' => null, 'message' => 'Linked account not found.'], 404);
        }

        return $this->success(null, 'Account unlinked.');
    }
}

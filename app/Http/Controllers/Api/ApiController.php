<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ApiController extends Controller
{
    protected function authUser(Request $request): ?User
    {
        $token = $request->bearerToken() ?? $request->input('api_token');

        if (empty($token)) {
            return null;
        }

        $user = User::where('api_token', $token)->first();

        if (! $user) {
            return null;
        }

        // Reject expired access tokens
        if ($user->token_expires_at && now()->isAfter($user->token_expires_at)) {
            return null;
        }

        return $user;
    }

    /**
     * Who is placing a shop order: a logged-in user, or a guest identified by
     * the random guest_token their device keeps. A bearer token that no longer
     * works is still a 401 (so the app refreshes it) — never a silent guest.
     *
     * @return array{user: ?User, guest: ?array}|JsonResponse
     */
    protected function resolveShopper(Request $request): array|JsonResponse
    {
        if ($request->bearerToken()) {
            $user = $this->authUser($request);
            return $user ? ['user' => $user, 'guest' => null] : $this->unauthorized();
        }

        $guest = $request->validate([
            'guest_name'  => 'required|string|max:100',
            'guest_phone' => ['required', 'string', 'max:20', 'regex:/^\+?[0-9 ]{8,16}$/'],
            'guest_token' => 'required|string|min:20|max:64',
            'fcm_token'   => 'nullable|string|max:255',
        ]);

        return ['user' => null, 'guest' => $guest];
    }

    protected function guestColumns(?array $guest): array
    {
        if (! $guest) return ['entry_type' => 'user'];

        return [
            'entry_type'      => 'guest',
            'guest_name'      => trim($guest['guest_name']),
            'guest_phone'     => trim($guest['guest_phone']),
            'guest_token'     => $guest['guest_token'],
            'guest_fcm_token' => $guest['fcm_token'] ?? null,
        ];
    }

    protected function unauthorized(string $message = 'Unauthorized'): JsonResponse
    {
        return response()->json(['message' => $message], 401);
    }

    protected function success(array $data, int $status = 200): JsonResponse
    {
        return response()->json(['data' => $data], $status);
    }
}

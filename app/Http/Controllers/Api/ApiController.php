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

    protected function unauthorized(string $message = 'Unauthorized'): JsonResponse
    {
        return response()->json(['message' => $message], 401);
    }

    protected function success(array $data, int $status = 200): JsonResponse
    {
        return response()->json(['data' => $data], $status);
    }
}

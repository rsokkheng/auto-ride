<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves $request->user() from the app's own bearer api_token (the same
 * check ApiController::authUser() does), for framework routes that expect a
 * standard authenticated user — e.g. the broadcasting/auth endpoint.
 */
class AuthenticateApiToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();

        $user = $token ? User::where('api_token', $token)->first() : null;

        if (! $user || ($user->token_expires_at && now()->isAfter($user->token_expires_at))) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $request->setUserResolver(fn () => $user);

        return $next($request);
    }
}

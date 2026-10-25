<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Support\Localized;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Throwable;

class SetApiLocale
{
    public function handle(Request $request, Closure $next)
    {
        $lang = $request->query('lang') ?? $request->header('X-Locale');

        $suffix = Localized::suffix($lang ?: 'en');
        app()->setLocale(Localized::LOCALES[$suffix]);

        if ($lang) {
            $this->rememberForUser($request, $suffix);
        }

        return $next($request);
    }

    /**
     * Save the app's language on the signed-in user so pushes sent later (from
     * queues, other users' actions) arrive in that language. Cached per token
     * so this only touches the DB when the language actually changes.
     */
    private function rememberForUser(Request $request, string $suffix): void
    {
        $token = $request->bearerToken();
        if (! $token) return;

        $key = 'api_locale:' . sha1($token);
        if (Cache::get($key) === $suffix) return;

        try {
            $user = User::where('api_token', $token)->first();
            if (! $user) return;

            if ($user->preferredLocale() !== $suffix) {
                $user->update(['accessibility_settings' => array_merge(
                    $user->accessibility_settings ?? [],
                    ['language' => $suffix],
                )]);
            }

            Cache::put($key, $suffix, now()->addDay());
        } catch (Throwable $e) {
            report($e);
        }
    }
}

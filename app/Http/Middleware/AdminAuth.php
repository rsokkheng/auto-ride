<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Guards every /admin route (login/logout excepted — they live outside the
 * group), including controllers that don't check auth themselves such as
 * SettlementController and ReportExportController.
 */
class AdminAuth
{
    /** Roles allowed into the admin panel. ADMIN_TEST_MODE also lets drivers/passengers in. */
    public static function allowedRoles(): array
    {
        return config('app.admin_test_mode') ? ['admin', 'driver', 'passenger'] : ['admin'];
    }

    public function handle(Request $request, Closure $next)
    {
        if (! Auth::check() || ! in_array(Auth::user()->role, self::allowedRoles(), true)) {
            return $request->expectsJson()
                ? response()->json(['message' => 'Unauthenticated.'], 401)
                : redirect()->route('admin.login');
        }

        return $next($request);
    }
}

<?php

namespace App\Providers;

use App\Support\RealtimeHooks;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        Paginator::useBootstrapFour();

        $this->configureRateLimiting();

        RealtimeHooks::register();
    }

    private function configureRateLimiting(): void
    {
        // Applied to every /api route. Keyed on a hash of the bearer token so
        // it costs no DB lookup; the per-IP cap stops token-rotation bypass.
        // The IP cap is deliberately loose — Cambodian mobile carriers put
        // many subscribers behind one CGNAT address.
        RateLimiter::for('api', function (Request $request) {
            $limits = [Limit::perMinute(1200)->by('ip:' . $request->ip())];

            if ($token = $request->bearerToken()) {
                $limits[] = Limit::perMinute(300)->by('tok:' . sha1($token));
            }

            return $limits;
        });

        // SMS costs money — cap per phone and per IP.
        RateLimiter::for('otp-send', fn (Request $request) => [
            Limit::perHour(10)->by('otp-send:phone:' . self::phoneKey($request)),
            Limit::perMinute(20)->by('otp-send:ip:' . $request->ip()),
        ]);

        RateLimiter::for('otp-verify', fn (Request $request) => [
            Limit::perMinute(10)->by('otp-verify:phone:' . self::phoneKey($request)),
            Limit::perMinute(60)->by('otp-verify:ip:' . $request->ip()),
        ]);

        // Password login (API + admin panel).
        RateLimiter::for('login', function (Request $request) {
            $login = strtolower((string) ($request->input('login') ?? $request->input('email')));

            return [
                Limit::perMinute(10)->by('login:' . $login . '|' . $request->ip()),
                Limit::perMinute(60)->by('login:ip:' . $request->ip()),
            ];
        });
    }

    private static function phoneKey(Request $request): string
    {
        return preg_replace('/\D/', '', (string) $request->input('phone'));
    }
}

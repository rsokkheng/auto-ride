<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class SetApiLocale
{
    public function handle(Request $request, Closure $next)
    {
        $lang = $request->query('lang') ?? $request->header('X-Locale');

        $locale = match ($lang) {
            'km', 'kh' => 'km',
            'zh'       => 'zh',
            default    => 'en',
        };

        app()->setLocale($locale);

        return $next($request);
    }
}

<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PartnerAuth
{
    public function handle(Request $request, Closure $next)
    {
        if (! Auth::guard('partner')->check()) {
            return redirect()->route('partner.login');
        }

        return $next($request);
    }
}

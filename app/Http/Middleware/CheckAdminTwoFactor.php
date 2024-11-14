<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;  // Keep only this one

class CheckAdminTwoFactor
{
    public function handle(Request $request, Closure $next)
    {
        $user = Auth::user();

        if (Auth::check() && $user->user_type === 'admin' && !$request->session()->get('user.2fa.authenticated')) {
            return redirect()->route('2fa.show');
        }

        return $next($request);
    }
}

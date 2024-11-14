<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TwoFactorController extends Controller
{
    public function show()
    {
        return view('auth.two-factor-challenge');
    }

    public function verify(Request $request)
    {
        $request->validate([
            'two_factor_code' => 'required|string',
        ]);

        $user = Auth::user();

        if ($user->user_type !== 'admin') {
            return redirect()->route('dashboard');
        }

        if ($request->two_factor_code !== $user->two_factor_code) {
            return redirect()->back()->withErrors(['two_factor_code' => 'The code you entered is incorrect.']);
        }

        if ($user->two_factor_expires_at < now()) {
            return redirect()->back()->withErrors(['two_factor_code' => 'The code has expired. Please login again.']);
        }

        $user->resetTwoFactorCode();
        $request->session()->put('user.2fa.authenticated', true);

        return redirect()->intended(route('admin.dashboard'));
    }
}
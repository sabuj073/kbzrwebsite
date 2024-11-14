<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Foundation\Auth\AuthenticatesUsers;
use GeneaLabs\LaravelSocialiter\Facades\Socialiter;
use Socialite;
use App\Models\User;
use App\Models\Customer;
use App\Models\Cart;
use App\Services\SocialRevoke;
use Session;
use Illuminate\Http\Request;
use CoreComponentRepository;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use GuzzleHttp\Client;
use Auth;
use Storage;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class AdminAuthController extends Controller
{
    public function showLoginForm()
    {
        return view('admin.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        if (Auth::guard('admin')->attempt($credentials)) {
            $admin = Auth::guard('admin')->user();
            $code = Str::random(6);
            $admin->two_factor_code = $code;
            $admin->two_factor_expires_at = now()->addMinutes(10);
            $admin->save();

            Mail::to($admin->email)->send(new TwoFactorCode($code));

            return redirect()->route('admin.2fa');
        }

        return back()->withErrors(['email' => 'Invalid credentials']);
    }
}
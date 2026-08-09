<?php

namespace App\Http\Controllers\Landlord;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Socialite\Contracts\Factory as SocialiteFactory;

class AuthController extends Controller
{
    public function __construct(private SocialiteFactory $socialite) {}

    public function showLogin(): Response
    {
        return Inertia::render('Landlord/Login');
    }

    public function redirectToGoogle()
    {
        if (!config('services.google.client_id') || !config('services.google.client_secret')) {
            return redirect()->route('landlord.login')->with('error', 'Google OAuth is not configured yet. Set GOOGLE_CLIENT_ID and GOOGLE_CLIENT_SECRET in .env.');
        }

        return Inertia::location(
            $this->socialite->driver('google')
                ->redirectUrl(url('/landlord/auth/google/callback'))
                ->scopes(['openid', 'profile', 'email'])
                ->redirect()
                ->getTargetUrl()
        );
    }

    public function handleGoogleCallback(Request $request)
    {
        try {
            $googleUser = $this->socialite->driver('google')
                ->redirectUrl(url('/landlord/auth/google/callback'))
                ->user();
        } catch (\Throwable) {
            return redirect()->route('landlord.login')->with('error', 'Google authentication failed.');
        }

        $allowed = array_map('strtolower', config('landlord.admin_emails', []));
        if (!in_array(strtolower($googleUser->email), $allowed, true)) {
            return redirect()->route('landlord.login')->with('error', 'This Google account is not authorized for landlord access.');
        }

        $request->session()->regenerate();
        $request->session()->put('landlord_admin_email', $googleUser->email);
        $request->session()->put('landlord_admin_name', $googleUser->name);
        $request->session()->put('landlord_auth_at', now()->timestamp);

        // Redirect to intended URL if re-authenticating for a sensitive operation
        $intended = $request->session()->pull('landlord_intended_url');

        return redirect($intended ?? route('landlord.dashboard'));
    }

    public function logout(Request $request)
    {
        $request->session()->forget(['landlord_admin_email', 'landlord_admin_name']);
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('landlord.login');
    }
}

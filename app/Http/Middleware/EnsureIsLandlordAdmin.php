<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureIsLandlordAdmin
{
    /**
     * Maximum landlord session age in minutes before forced re-auth.
     */
    private const SESSION_TIMEOUT_MINUTES = 60;

    public function handle(Request $request, Closure $next)
    {
        $email = $request->session()->get('landlord_admin_email');
        $allowed = config('landlord.admin_emails', []);

        if (!$email || !in_array(strtolower($email), array_map('strtolower', $allowed), true)) {
            return redirect()->route('landlord.login')->with('error', 'Please sign in with an authorized Google account.');
        }

        // Check session timeout
        $authAt = $request->session()->get('landlord_auth_at');
        if (!$authAt || (now()->timestamp - $authAt) > (self::SESSION_TIMEOUT_MINUTES * 60)) {
            $request->session()->forget(['landlord_admin_email', 'landlord_admin_name', 'landlord_auth_at']);

            return redirect()->route('landlord.login')->with('error', 'Your session has expired. Please sign in again.');
        }

        return $next($request);
    }
}

<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureIsLandlordAdmin
{
    public function handle(Request $request, Closure $next)
    {
        $email = $request->session()->get('landlord_admin_email');
        $allowed = config('landlord.admin_emails', []);

        if (!$email || !in_array(strtolower($email), array_map('strtolower', $allowed), true)) {
            return redirect()->route('landlord.login')->with('error', 'Please sign in with an authorized Google account.');
        }

        return $next($request);
    }
}

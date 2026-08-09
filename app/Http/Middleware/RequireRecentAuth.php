<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Requires the landlord admin to have authenticated recently (within N minutes)
 * before accessing sensitive operations like database exports.
 *
 * If the auth is stale, redirects to Google OAuth re-authentication.
 */
class RequireRecentAuth
{
    /**
     * Maximum age of authentication in minutes before re-auth is required.
     */
    private const MAX_AUTH_AGE_MINUTES = 5;

    public function handle(Request $request, Closure $next): Response
    {
        $authAt = $request->session()->get('landlord_auth_at');

        if (!$authAt || (now()->timestamp - $authAt) > (self::MAX_AUTH_AGE_MINUTES * 60)) {
            // Store the intended URL so we can redirect back after re-auth
            $request->session()->put('landlord_intended_url', $request->fullUrl());

            return redirect()->route('landlord.auth.redirect')
                ->with('error', 'Please re-authenticate to access this resource. This is required for sensitive operations.');
        }

        return $next($request);
    }
}

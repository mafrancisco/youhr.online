<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Restricts access to ADMS (biometric device push) endpoints by IP address.
 *
 * Configure allowed IPs via the ADMS_ALLOWED_IPS environment variable
 * (comma-separated). If the variable is empty or not set, all IPs are allowed
 * (backward compatible for development/testing).
 */
class AdmsIpAllowlist
{
    public function handle(Request $request, Closure $next): Response
    {
        $allowedIps = array_filter(
            array_map('trim', explode(',', config('saas.adms_allowed_ips', '')))
        );

        // If no IPs configured, allow all (backward compat / dev mode)
        if (empty($allowedIps)) {
            return $next($request);
        }

        $clientIp = $request->ip();

        if (!in_array($clientIp, $allowedIps, true)) {
            return response('Forbidden', 403);
        }

        return $next($request);
    }
}

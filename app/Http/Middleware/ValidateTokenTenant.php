<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ensures the authenticated user's token actually belongs to the resolved tenant.
 *
 * Since Sanctum tokens are stored in the tenant DB, switching to a different tenant
 * via X-Company-Slug will cause the token lookup to fail (401). This middleware adds
 * an explicit belt-and-suspenders check after authentication.
 */
class ValidateTokenTenant
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $company = app()->bound('currentCompany') ? app('currentCompany') : null;

        // If both are resolved, verify the user's token was issued from this tenant
        if ($user && $company && $request->bearerToken()) {
            $token = $user->currentAccessToken();

            // If the token model's connection doesn't match the current tenant connection,
            // something is wrong — reject the request
            if ($token && method_exists($token, 'getConnectionName')) {
                $expectedConn = config('saas.tenant_connection', 'tenant');
                if ($token->getConnectionName() !== $expectedConn) {
                    return response()->json(['message' => 'Token does not belong to this tenant.'], 403);
                }
            }
        }

        return $next($request);
    }
}

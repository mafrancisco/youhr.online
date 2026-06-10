<?php

namespace App\Http\Middleware;

use App\Services\SaaS\TenantManager;
use Closure;
use Illuminate\Http\Request;

class EnsureTenantLicensed
{
    public function __construct(private TenantManager $tenants) {}

    public function handle(Request $request, Closure $next)
    {
        $company = $this->tenants->currentCompany();

        if (!$company) {
            return redirect()->route('login');
        }

        if (!$company->hasActiveLicense()) {
            if (!$request->routeIs('license.*') && !$request->routeIs('logout')) {
                if ($request->expectsJson() || $request->is('api/*')) {
                    return response()->json(['message' => 'Tenant license activation required.'], 402);
                }
                return redirect()->route('license.show')->with('error', 'License activation is required.');
            }
        }

        return $next($request);
    }
}

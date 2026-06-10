<?php

namespace App\Http\Middleware;

use App\Models\SaaS\Company;
use App\Services\SaaS\TenantManager;
use Closure;
use Illuminate\Http\Request;

class ResolveTenantContext
{
    public function __construct(private TenantManager $tenants) {}

    public function handle(Request $request, Closure $next)
    {
        // Skip tenant resolution for landlord routes and auth routes
        if ($request->is('landlord/*') || $request->is('auth/*') || $request->is('register-company*')) {
            return $next($request);
        }

        $sessionKey = $this->tenants->companySessionKey();

        // Try session first
        $companyId = $request->hasSession() ? $request->session()->get($sessionKey) : null;

        // Try API header
        if (!$companyId && $request->is('api/*')) {
            $slug = $request->header('X-Company-Slug');
            if ($slug) {
                $company = Company::where('slug', $slug)->where('status', 'active')->first();
                if ($company) {
                    $this->tenants->switchToCompany($company);
                    return $next($request);
                }
            }
        }

        // Resolve from session
        if ($companyId) {
            $company = Company::whereKey($companyId)->where('status', 'active')->first();
            if ($company) {
                $this->tenants->switchToCompany($company);
            }
        }

        return $next($request);
    }
}

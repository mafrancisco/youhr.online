<?php

namespace App\Http\Middleware;

use App\Services\SaaS\TenantManager;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class RequireTenantContext
{
    public function __construct(private TenantManager $tenants) {}

    public function handle(Request $request, Closure $next)
    {
        if (!$this->tenants->currentCompany()) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json(['message' => 'Tenant context missing. Provide X-Company-Slug.'], 422);
            }

            // Logout to prevent redirect loops (user is authed but has no tenant)
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->with('error', 'Session expired. Please sign in again.');
        }

        return $next($request);
    }
}

<?php

namespace App\Http\Middleware;

use App\Models\SaaS\CompanyModule;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckModuleAccess
{
    /**
     * Handle an incoming request.
     *
     * Usage in routes: middleware('module:leaves') or middleware('module:gate-passes')
     */
    public function handle(Request $request, Closure $next, string $module): Response
    {
        $company = app()->bound('currentCompany') ? app('currentCompany') : null;

        if (!$company) {
            // No tenant context, let other middleware handle it
            return $next($request);
        }

        if (!CompanyModule::isModuleEnabled($company->id, $module)) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'This module is not available for your organization.',
                ], 403);
            }

            return redirect()->route('dashboard')
                ->with('error', 'This module is not available for your organization. Contact your administrator.');
        }

        return $next($request);
    }
}

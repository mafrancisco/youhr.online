<?php

namespace App\Http\Controllers\Landlord;

use App\Http\Controllers\Controller;
use App\Models\SaaS\Company;
use App\Models\SaaS\CompanyLicense;
use App\Models\SaaS\LandlordAuditLog;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        $companies = Company::withCount('licenses')
            ->latest()
            ->get()
            ->map(fn ($company) => [
                'id' => $company->id,
                'name' => $company->name,
                'slug' => $company->slug,
                'database' => $company->database,
                'owner_google_email' => $company->owner_google_email,
                'status' => $company->status,
                'licensed' => $company->hasActiveLicense(),
                'licenses_count' => $company->licenses_count,
                'created_at' => $company->created_at?->toDateTimeString(),
            ]);

        $licenses = CompanyLicense::with('company')
            ->latest()
            ->limit(25)
            ->get()
            ->map(fn ($license) => [
                'id' => $license->id,
                'company' => $license->company?->name,
                'slug' => $license->company?->slug,
                'status' => $license->status,
                'bound_email' => $license->bound_email,
                'activated_by_email' => $license->activated_by_email,
                'activated_at' => $license->activated_at?->toDateTimeString(),
                'expires_at' => $license->expires_at?->toDateTimeString(),
            ]);

        $auditLogs = LandlordAuditLog::with(['company', 'license'])
            ->latest()
            ->limit(25)
            ->get()
            ->map(fn ($log) => [
                'id' => $log->id,
                'company' => $log->company?->name,
                'license' => $log->license?->id,
                'actor_email' => $log->actor_email,
                'action' => $log->action,
                'meta' => $log->meta,
                'created_at' => $log->created_at?->toDateTimeString(),
            ]);

        return Inertia::render('Landlord/Dashboard', [
            'admin' => [
                'email' => $request->session()->get('landlord_admin_email'),
                'name' => $request->session()->get('landlord_admin_name'),
            ],
            'companies' => $companies,
            'licenses' => $licenses,
            'auditLogs' => $auditLogs,
        ]);
    }
}

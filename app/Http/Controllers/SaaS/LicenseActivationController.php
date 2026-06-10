<?php

namespace App\Http\Controllers\SaaS;

use App\Http\Controllers\Controller;
use App\Models\SaaS\CompanyLicense;
use App\Services\SaaS\TenantManager;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LicenseActivationController extends Controller
{
    public function __construct(private TenantManager $tenants) {}

    public function show(Request $request): Response
    {
        $company = $this->tenants->currentCompany();

        return Inertia::render('SaaS/LicenseActivation', [
            'company' => [
                'name' => $company?->name,
                'slug' => $company?->slug,
                'licensed' => $company?->hasActiveLicense() ?? false,
            ],
            'userEmail' => $request->user()?->email,
        ]);
    }

    public function activate(Request $request)
    {
        $request->validate([
            'license_key' => ['required', 'string', 'min:16', 'max:255'],
        ]);

        $company = $this->tenants->currentCompany();
        if (!$company) {
            return redirect()->route('login')->with('error', 'Company session missing. Please login again.');
        }

        $licenses = CompanyLicense::where('company_id', $company->id)->get();

        $match = $licenses->first(fn (CompanyLicense $license) => $license->matchesKey($request->license_key));

        if (!$match) {
            return back()->withErrors(['license_key' => 'Invalid license key.']);
        }

        if ($match->bound_email && strcasecmp($match->bound_email, $request->user()->email) !== 0) {
            return back()->withErrors(['license_key' => 'This key is bound to a different Google account.']);
        }

        if ($match->expires_at && now()->greaterThan($match->expires_at)) {
            return back()->withErrors(['license_key' => 'This license key has expired.']);
        }

        $match->update([
            'status' => 'active',
            'activated_by_email' => $request->user()->email,
            'activated_at' => now(),
        ]);

        return redirect()->route('dashboard')->with('success', 'License activated. Welcome to your company workspace.');
    }
}

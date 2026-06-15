<?php

namespace App\Http\Controllers\Landlord;

use App\Http\Controllers\Controller;
use App\Models\SaaS\Company;
use App\Models\SaaS\CompanyLicense;
use App\Models\SaaS\LandlordAuditLog;
use App\Notifications\LicenseKeyGenerated;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

class LicenseController extends Controller
{
    public function generate(Request $request, Company $company)
    {
        $data = $request->validate([
            'bound_email' => ['nullable', 'email'],
            'expires_at' => ['nullable', 'date'],
        ]);

        $plain = strtoupper(Str::random(4) . '-' . Str::random(4) . '-' . Str::random(4) . '-' . Str::random(4));
        $targetEmail = $data['bound_email'] ?? $company->owner_google_email;

        CompanyLicense::create([
            'company_id' => $company->id,
            'license_key_hash' => hash('sha256', $plain),
            'bound_email' => $data['bound_email'] ?? null,
            'status' => 'pending',
            'expires_at' => !empty($data['expires_at']) ? Carbon::parse($data['expires_at']) : null,
            'metadata' => [
                'generated_by' => request()->session()->get('landlord_admin_email'),
                'generated_at' => now()->toIso8601String(),
            ],
        ]);

        if ($targetEmail) {
            try {
                Notification::route('mail', $targetEmail)
                    ->notify(new LicenseKeyGenerated($company, $plain, $data['expires_at'] ?? null));
            } catch (\Throwable) {
                // Email sending failed silently — key is still displayed in flash message
            }
        }

        LandlordAuditLog::create([
            'company_id' => $company->id,
            'actor_email' => $request->session()->get('landlord_admin_email'),
            'action' => 'license.generated',
            'subject_type' => CompanyLicense::class,
            'subject_id' => null,
            'meta' => [
                'bound_email' => $data['bound_email'] ?? null,
                'expires_at' => $data['expires_at'] ?? null,
            ],
        ]);

        return back()->with('success', 'License generated: ' . $plain);
    }

    public function activate(CompanyLicense $license)
    {
        $license->update(['status' => 'active']);

        LandlordAuditLog::create([
            'company_id' => $license->company_id,
            'license_id' => $license->id,
            'actor_email' => request()->session()->get('landlord_admin_email'),
            'action' => 'license.activated',
            'subject_type' => CompanyLicense::class,
            'subject_id' => $license->id,
            'meta' => ['status' => 'active'],
        ]);

        return back()->with('success', 'License activated.');
    }

    public function suspend(CompanyLicense $license)
    {
        $license->update(['status' => 'suspended']);

        LandlordAuditLog::create([
            'company_id' => $license->company_id,
            'license_id' => $license->id,
            'actor_email' => request()->session()->get('landlord_admin_email'),
            'action' => 'license.suspended',
            'subject_type' => CompanyLicense::class,
            'subject_id' => $license->id,
            'meta' => ['status' => 'suspended'],
        ]);

        return back()->with('success', 'License suspended.');
    }
}

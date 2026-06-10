<?php

namespace App\Http\Controllers\Landlord;

use App\Http\Controllers\Controller;
use App\Models\SaaS\Company;
use App\Models\SaaS\CompanyLicense;
use App\Models\SaaS\LandlordAuditLog;
use Illuminate\Http\Request;

class CompanyController extends Controller
{
    public function update(Request $request, Company $company)
    {
        $data = $request->validate([
            'status' => ['required', 'in:active,inactive'],
        ]);

        $company->update($data);

        LandlordAuditLog::create([
            'company_id' => $company->id,
            'actor_email' => $request->session()->get('landlord_admin_email'),
            'action' => 'company.status_updated',
            'subject_type' => Company::class,
            'subject_id' => $company->id,
            'meta' => ['status' => $data['status']],
        ]);

        if ($data['status'] === 'inactive') {
            CompanyLicense::where('company_id', $company->id)
                ->where('status', 'active')
                ->update(['status' => 'suspended']);

            LandlordAuditLog::create([
                'company_id' => $company->id,
                'actor_email' => $request->session()->get('landlord_admin_email'),
                'action' => 'company.licenses_suspended',
                'subject_type' => CompanyLicense::class,
                'subject_id' => null,
                'meta' => ['status' => 'suspended'],
            ]);
        }

        return back()->with('success', 'Company status updated.');
    }
}

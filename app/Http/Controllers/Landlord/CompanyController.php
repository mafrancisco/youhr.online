<?php

namespace App\Http\Controllers\Landlord;

use App\Http\Controllers\Controller;
use App\Models\SaaS\Company;
use App\Models\SaaS\CompanyLicense;
use App\Models\SaaS\LandlordAuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

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

    /**
     * Permanently delete a company: its tenant database, licenses, modules and the company row.
     * The audit log rows are kept (company_id is set to null) with a record of what was deleted.
     */
    public function destroy(Request $request, Company $company)
    {
        $request->validate([
            'confirm' => ['required', 'string'],
        ]);

        if (!hash_equals($company->slug, (string) $request->input('confirm'))) {
            return back()->with('error', 'Confirmation did not match the company code. Nothing was deleted.');
        }

        $database = (string) $company->database;
        $prefix = config('saas.tenant_database_prefix', 'tenant_');
        $connection = config('saas.landlord_connection', 'landlord');

        // Never drop anything that is not clearly a tenant database.
        $protected = array_filter([
            Config::get("database.connections.{$connection}.database"),
            Config::get('database.connections.mysql.database'),
            Config::get('database.connections.' . config('database.default') . '.database'),
        ]);

        if (
            !preg_match('/^[a-z0-9_\-]+$/i', $database)
            || !str_starts_with($database, $prefix)
            || in_array($database, $protected, true)
        ) {
            return back()->with('error', 'Refusing to delete: the company database name is not a safe tenant database.');
        }

        if (Company::where('database', $database)->where('id', '!=', $company->id)->exists()) {
            return back()->with('error', 'Refusing to delete: another company uses the same database.');
        }

        $meta = [
            'name' => $company->name,
            'slug' => $company->slug,
            'database' => $database,
            'owner_google_email' => $company->owner_google_email,
        ];

        try {
            DB::connection($connection)->statement("DROP DATABASE IF EXISTS `{$database}`");
        } catch (\Throwable $e) {
            Log::error("Landlord company delete failed for {$database}: " . $e->getMessage());
            return back()->with('error', 'Could not drop the company database. Nothing was deleted.');
        }

        LandlordAuditLog::create([
            'company_id' => null,
            'actor_email' => $request->session()->get('landlord_admin_email'),
            'action' => 'company.deleted',
            'subject_type' => Company::class,
            'subject_id' => $company->id,
            'meta' => $meta,
        ]);

        // Licenses and modules are removed by the cascading foreign keys.
        $company->delete();

        return back()->with('success', "Company \"{$meta['name']}\" was deleted.");
    }
}

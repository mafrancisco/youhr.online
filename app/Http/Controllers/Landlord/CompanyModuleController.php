<?php

namespace App\Http\Controllers\Landlord;

use App\Http\Controllers\Controller;
use App\Models\SaaS\Company;
use App\Models\SaaS\CompanyModule;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CompanyModuleController extends Controller
{
    /**
     * Show module management page for a specific company.
     */
    public function edit(Company $company): Response
    {
        $availableModules = CompanyModule::availableModules();
        $enabledModules = CompanyModule::enabledForCompany($company->id);

        // Build the modules list with enabled state
        $modules = collect($availableModules)->map(fn($label, $key) => [
            'key' => $key,
            'label' => $label,
            'enabled' => in_array($key, $enabledModules),
        ])->values()->all();

        return Inertia::render('Landlord/CompanyModules', [
            'company' => [
                'id' => $company->id,
                'name' => $company->name,
                'slug' => $company->slug,
            ],
            'modules' => $modules,
        ]);
    }

    /**
     * Update the enabled modules for a company.
     */
    public function update(Request $request, Company $company)
    {
        $validated = $request->validate([
            'modules' => 'required|array',
            'modules.*' => 'string|in:' . implode(',', array_keys(CompanyModule::availableModules())),
        ]);

        $enabledModules = $validated['modules'];
        $allModules = array_keys(CompanyModule::availableModules());

        foreach ($allModules as $module) {
            CompanyModule::updateOrCreate(
                ['company_id' => $company->id, 'module' => $module],
                ['enabled' => in_array($module, $enabledModules)]
            );
        }

        return redirect()->route('landlord.companies.modules', $company)
            ->with('success', "Modules updated for {$company->name}.");
    }
}

<?php

namespace App\Http\Middleware;

use App\Models\GatePass;
use App\Models\Leave;
use App\Models\SaaS\Company;
use App\Models\SaaS\CompanyModule;
use App\Models\Setting;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    public function share(Request $request): array
    {
        return array_merge(parent::share($request), [
            'auth' => fn() => $request->user() ? [
                'id'       => $request->user()->id,
                'fullname' => $request->user()->fullname,
                'email'    => $request->user()->email,
                'type'     => $request->user()->type,
                'isHR'     => $request->user()->isHR(),
            ] : null,

            'flash' => fn() => [
                'success' => $request->session()->get('success'),
                'error'   => $request->session()->get('error'),
            ],

            'pendingLeaves' => fn() => $request->user()?->isHR()
                ? Leave::where('status', 'Pending')->count()
                : 0,

            'pendingGatePasses' => fn() => $request->user()?->isHR()
                ? GatePass::where(fn($q) => $q->whereNull('date_time_approved')->orWhere('date_time_approved', ''))
                    ->where('status', '!=', 'Cancelled')->count()
                : 0,

            'appName' => config('app.name'),

            'settings' => function () {
                $tenantCompany = app()->bound('currentCompany') ? app('currentCompany') : null;
                if (!$tenantCompany) {
                    return [
                        'system_name' => config('app.name', 'DTR SaaS'),
                        'logo_url' => null,
                    ];
                }

                $s = Setting::current();
                return [
                    'system_name' => $s->system_name,
                    'logo_url'    => $s->logoUrl(),
                ];
            },

            'company' => function () {
                /** @var Company|null $company */
                $company = app()->bound('currentCompany') ? app('currentCompany') : null;

                if (!$company) {
                    return null;
                }

                return [
                    'id' => $company->id,
                    'name' => $company->name,
                    'slug' => $company->slug,
                    'licensed' => $company->hasActiveLicense(),
                ];
            },

            'enabledModules' => function () {
                $company = app()->bound('currentCompany') ? app('currentCompany') : null;

                if (!$company) {
                    return [];
                }

                return CompanyModule::enabledForCompany($company->id);
            },
        ]);
    }
}

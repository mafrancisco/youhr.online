<?php

namespace App\Http\Middleware;

use App\Models\GatePass;
use App\Models\Leave;
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
                ? GatePass::whereNull('date_time_approved')->where('status', '!=', 'Cancelled')->count()
                : 0,

            'appName' => config('app.name'),
        ]);
    }
}

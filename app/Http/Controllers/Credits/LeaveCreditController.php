<?php

namespace App\Http\Controllers\Credits;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\LeaveCredit;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LeaveCreditController extends Controller
{
    public function index(): Response
    {
        $credits = LeaveCredit::with('employee')
            ->orderBy('badgeID')
            ->get()
            ->map(fn($c) => [
                'id'          => $c->id,
                'badgeID'     => $c->badgeID,
                'empName'     => $c->employee?->empName,
                'vl'          => $c->vl,
                'sl'          => $c->sl,
                'maternity'   => $c->maternity,
                'paternity'   => $c->paternity,
                'spl'         => $c->spl,
                'forced'      => $c->forced,
                'wellness'    => $c->wellness,
                'ot'          => $c->ot,
                'service'     => $c->service,
                'dateupdated' => $c->dateupdated,
            ]);

        $employees = Employee::active()
            ->whereDoesntHave('leaveCredit')
            ->orderBy('empName')
            ->get(['badgeID', 'empName']);

        return Inertia::render('Credits/Index', [
            'credits'   => $credits,
            'employees' => $employees,
        ]);
    }

    public function store(Request $request)
    {
        $request->validate(['badgeID' => ['required', 'exists:employees,badgeID']]);
        LeaveCredit::firstOrCreate(
            ['badgeID' => $request->badgeID],
            [
                'vl' => 0, 'sl' => 0, 'maternity' => 0, 'paternity' => 0,
                'spl' => 0, 'forced' => 0, 'wellness' => 0,
                'ot' => 0, 'service' => 0, 'dateupdated' => now()->toDateString(),
            ]
        );
        return back()->with('success', 'Leave credit record created.');
    }

    public function update(Request $request, string $badgeID)
    {
        $request->validate([
            'vl'        => ['required', 'numeric', 'min:0'],
            'sl'        => ['required', 'numeric', 'min:0'],
            'maternity' => ['required', 'numeric', 'min:0'],
            'paternity' => ['required', 'numeric', 'min:0'],
            'spl'       => ['required', 'numeric', 'min:0'],
            'forced'    => ['required', 'numeric', 'min:0'],
            'wellness'  => ['required', 'numeric', 'min:0'],
            'ot'        => ['required', 'numeric', 'min:0'],
            'service'   => ['required', 'numeric', 'min:0'],
        ]);

        $credit = LeaveCredit::where('badgeID', $badgeID)->firstOrFail();
        $credit->update([
            ...$request->only(['vl', 'sl', 'maternity', 'paternity', 'spl', 'forced', 'wellness', 'ot', 'service']),
            'dateupdated' => now()->toDateString(),
        ]);
        return back()->with('success', 'Leave credits updated.');
    }
}

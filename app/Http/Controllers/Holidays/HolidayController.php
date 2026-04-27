<?php

namespace App\Http\Controllers\Holidays;

use App\Http\Controllers\Controller;
use App\Models\DateParameter;
use App\Models\HolType;
use App\Services\AdjustedFlagCeremonyService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class HolidayController extends Controller
{
    public function __construct(private AdjustedFlagCeremonyService $flagService) {}

    public function index(): Response
    {
        return Inertia::render('Holidays/Index', [
            'types'      => HolType::orderBy('type')->get(['id', 'type']),
            'parameters' => DateParameter::orderByDesc('actualDate')->get([
                'id', 'type', 'description', 'actualDate', 'timein', 'breakout', 'breakin', 'timeout',
            ]),
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'type'        => ['required', 'string'],
            'description' => ['required', 'string'],
            'actualDate'  => ['required', 'date_format:Y-m-d'],
            'timein'      => ['nullable', 'date_format:H:i'],
            'breakout'    => ['nullable', 'date_format:H:i'],
            'breakin'     => ['nullable', 'date_format:H:i'],
            'timeout'     => ['nullable', 'date_format:H:i'],
        ]);

        $param = DateParameter::create($request->only(['type','description','actualDate','timein','breakout','breakin','timeout']));

        if ($param->isAdjustedFlagCeremony() || $param->isOverrideOfficialTime()) {
            $this->flagService->recompute($param);
        }

        return back()->with('success', 'Date parameter saved.');
    }

    public function update(Request $request, DateParameter $holiday)
    {
        $request->validate([
            'type'        => ['required', 'string'],
            'description' => ['required', 'string'],
            'actualDate'  => ['required', 'date_format:Y-m-d'],
            'timein'      => ['nullable', 'date_format:H:i'],
            'breakout'    => ['nullable', 'date_format:H:i'],
            'breakin'     => ['nullable', 'date_format:H:i'],
            'timeout'     => ['nullable', 'date_format:H:i'],
        ]);

        $holiday->update($request->only(['type','description','actualDate','timein','breakout','breakin','timeout']));

        if ($holiday->isAdjustedFlagCeremony() || $holiday->isOverrideOfficialTime()) {
            $this->flagService->recompute($holiday->fresh());
        }

        return back()->with('success', 'Date parameter updated.');
    }

    public function destroy(DateParameter $holiday)
    {
        $holiday->delete();
        return back()->with('success', 'Date parameter deleted.');
    }
}

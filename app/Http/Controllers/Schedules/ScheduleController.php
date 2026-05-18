<?php

namespace App\Http\Controllers\Schedules;

use App\Http\Controllers\Controller;
use App\Models\Schedule;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ScheduleController extends Controller
{
    private array $days = ['m', 't', 'w', 'th', 'f', 'sat', 'sun'];

    public function index(): Response
    {
        return Inertia::render('Schedules/Index', [
            'schedules' => Schedule::orderBy('schedulename')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $request->validate(['schedulename' => ['required', 'string', 'unique:schedule,schedulename']]);

        Schedule::create($this->extractSlots($request));
        return back()->with('success', 'Schedule saved.');
    }

    public function update(Request $request, Schedule $sched)
    {
        $request->validate(['schedulename' => ['required', "unique:schedule,schedulename,{$sched->id}"]]);

        $sched->update($this->extractSlots($request));
        return back()->with('success', 'Schedule updated.');
    }

    public function destroy(Schedule $sched)
    {
        $sched->delete();
        return back()->with('success', 'Schedule deleted.');
    }

    private function extractSlots(Request $request): array
    {
        $data = ['schedulename' => $request->schedulename];
        foreach ($this->days as $d) {
            $data["{$d}_timein"]   = $request->input("{$d}_timein") ?? '';
            $data["{$d}_breakout"] = $request->input("{$d}_breakout") ?? '';
            $data["{$d}_breakin"]  = $request->input("{$d}_breakin") ?? '';
            $data["{$d}_timeout"]  = $request->input("{$d}_timeout") ?? '';
            $data["{$d}_crossday"] = $request->boolean("{$d}_crossday");
        }
        return $data;
    }
}

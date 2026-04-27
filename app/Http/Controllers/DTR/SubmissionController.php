<?php

namespace App\Http\Controllers\DTR;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\Submission;
use Illuminate\Http\Request;

class SubmissionController extends Controller
{
    public function store(Request $request)
    {
        $request->validate(['attRange' => ['required', 'regex:/^\d{4}-\d{2}$/']]);

        $employee = Employee::where('email', $request->user()->email)->firstOrFail();

        if (Submission::isLocked($employee->badgeID, $request->attRange)) {
            return back()->with('error', 'DTR already submitted for this period.');
        }

        Submission::create([
            'badgeID'        => $employee->badgeID,
            'attRange'       => $request->attRange,
            'date_submitted' => now()->toDateString(),
            'time_submitted' => now()->format('H:i:s'),
        ]);

        return back()->with('success', 'DTR submitted successfully.');
    }
}

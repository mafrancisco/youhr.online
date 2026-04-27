<?php

namespace App\Http\Controllers\DTR;

use App\Http\Controllers\Controller;
use App\Models\AttendanceRequest;
use App\Models\Employee;
use Illuminate\Http\Request;

class AdjustmentRequestController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'AttDate'    => ['required', 'string'],
            'attID'      => ['nullable', 'integer'],
            'StartTime1' => ['nullable', 'date_format:H:i'],
            'StartTime2' => ['nullable', 'date_format:H:i'],
            'StartTime3' => ['nullable', 'date_format:H:i'],
            'StartTime4' => ['nullable', 'date_format:H:i'],
            'remarks'    => ['nullable', 'string', 'max:255'],
        ]);

        $employee = Employee::where('email', $request->user()->email)->firstOrFail();

        AttendanceRequest::updateOrCreate(
            ['BadgeNumber' => $employee->badgeID, 'AttDate' => $request->AttDate],
            [
                'attID'      => $request->attID,
                'StartTime1' => $request->StartTime1,
                'StartTime2' => $request->StartTime2,
                'StartTime3' => $request->StartTime3,
                'StartTime4' => $request->StartTime4,
                'dateReq'    => now()->toDateString(),
                'remarks'    => $request->remarks,
            ]
        );

        return back()->with('success', 'Adjustment request submitted.');
    }

    public function destroy(Request $request, AttendanceRequest $req)
    {
        $employee = Employee::where('email', $request->user()->email)->firstOrFail();
        abort_unless($req->BadgeNumber === $employee->badgeID, 403);
        $req->delete();
        return back()->with('success', 'Adjustment request cancelled.');
    }
}

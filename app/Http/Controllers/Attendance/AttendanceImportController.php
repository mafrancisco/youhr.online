<?php

namespace App\Http\Controllers\Attendance;

use App\Http\Controllers\Controller;
use App\Services\AttendanceComputationService;
use App\Services\AttendanceImportService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AttendanceImportController extends Controller
{
    public function __construct(
        private AttendanceImportService $importer,
        private AttendanceComputationService $computer,
    ) {}

    public function index(): Response
    {
        return Inertia::render('Attendance/Upload');
    }

    public function import(Request $request)
    {
        $request->validate([
            'file'       => ['required', 'file'],
            'start_date' => ['required', 'date_format:Y-m-d'],
            'end_date'   => ['required', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'emp_status' => ['required', 'integer', 'in:1,2'],
        ]);

        $count = $this->importer->import(
            $request->file('file')->path(),
            $request->start_date,
            $request->end_date,
            (int) $request->emp_status,
        );

        // Run computation immediately after import
        $this->computer->compute($request->start_date, $request->end_date);

        return back()->with('success', "{$count} records imported and DTR computed.");
    }

    public function compute(Request $request)
    {
        $request->validate([
            'start_date' => ['required', 'date_format:Y-m-d'],
            'end_date'   => ['required', 'date_format:Y-m-d', 'after_or_equal:start_date'],
        ]);

        $this->computer->compute($request->start_date, $request->end_date);

        return back()->with('success', 'DTR computation finished.');
    }
}

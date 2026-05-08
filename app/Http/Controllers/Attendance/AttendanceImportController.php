<?php

namespace App\Http\Controllers\Attendance;

use App\Http\Controllers\Controller;
use App\Jobs\ComputeDTRJob;
use App\Services\AttendanceComputationService;
use App\Services\AttendanceImportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
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
        return Inertia::render('Attendance/Upload', [
            'computationStatus' => Cache::get('dtr_computation_status', 'idle'),
        ]);
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

        // Dispatch computation to background queue for faster response
        Cache::put('dtr_computation_status', 'queued', now()->addMinutes(10));
        ComputeDTRJob::dispatch($request->start_date, $request->end_date);

        return back()->with('success', "{$count} records imported. DTR computation is processing in the background.");
    }

    public function compute(Request $request)
    {
        $request->validate([
            'start_date' => ['required', 'date_format:Y-m-d'],
            'end_date'   => ['required', 'date_format:Y-m-d', 'after_or_equal:start_date'],
        ]);

        Cache::put('dtr_computation_status', 'queued', now()->addMinutes(10));
        ComputeDTRJob::dispatch($request->start_date, $request->end_date);

        return back()->with('success', 'DTR computation queued. Processing in the background.');
    }

    public function computationStatus()
    {
        return response()->json([
            'status' => Cache::get('dtr_computation_status', 'idle'),
        ]);
    }
}

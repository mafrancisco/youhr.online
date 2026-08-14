<?php

namespace App\Http\Controllers\Attendance;

use App\Http\Controllers\Controller;
use App\Models\EmployeeStatus;
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
        return Inertia::render('Attendance/Upload', [
            'employeeStatuses' => EmployeeStatus::orderBy('id')->get(['id', 'description']),
        ]);
    }

    public function import(Request $request)
    {
        $request->validate([
            'files'      => ['required', 'array', 'min:1'],
            'files.*'    => ['required', 'file', 'max:10240', 'mimes:csv,txt,dat'],
            'start_date' => ['required', 'date_format:Y-m-d'],
            'end_date'   => ['required', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'emp_status' => ['required', 'integer', 'exists:empstatus,id'],
        ]);

        // Collect all file paths
        $filePaths = array_map(
            fn($file) => $file->path(),
            $request->file('files')
        );

        // Run import across all files in one pass
        $count = $this->importer->import(
            $filePaths,
            $request->start_date,
            $request->end_date,
            (int) $request->emp_status,
        );

        // Run computation immediately
        $this->computer->compute($request->start_date, $request->end_date, (int) $request->emp_status);

        $fileCount = count($filePaths);
        $fileLabel = $fileCount > 1 ? "{$fileCount} files" : '1 file';

        return back()->with('success', "{$count} records imported from {$fileLabel} and DTR computed.");
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

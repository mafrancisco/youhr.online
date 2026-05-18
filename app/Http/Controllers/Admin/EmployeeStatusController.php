<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EmployeeStatus;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class EmployeeStatusController extends Controller
{
    public function index(): Response
    {
        $statuses = EmployeeStatus::orderBy('id')->get();

        return Inertia::render('Admin/EmployeeStatuses', [
            'statuses' => $statuses,
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'description' => ['required', 'string', 'max:50', 'unique:empstatus,description'],
        ]);

        EmployeeStatus::create(['description' => $request->description]);

        return back()->with('success', 'Employee status created.');
    }

    public function update(Request $request, EmployeeStatus $status)
    {
        $request->validate([
            'description' => ['required', 'string', 'max:50', "unique:empstatus,description,{$status->id}"],
        ]);

        $status->update(['description' => $request->description]);

        return back()->with('success', 'Employee status updated.');
    }

    public function destroy(EmployeeStatus $status)
    {
        $status->delete();

        return back()->with('success', 'Employee status deleted.');
    }
}

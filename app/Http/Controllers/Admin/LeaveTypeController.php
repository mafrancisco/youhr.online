<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LeaveType;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LeaveTypeController extends Controller
{
    public function index(): Response
    {
        $types = LeaveType::orderBy('id')->get();

        return Inertia::render('Admin/LeaveTypes', [
            'types' => $types,
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'leave_type'  => ['required', 'string', 'max:100', 'unique:leave_type,leave_type'],
            'description' => ['nullable', 'string', 'max:255'],
            'acronym'     => ['nullable', 'string', 'max:10'],
        ]);

        LeaveType::create([
            'leave_type'  => $request->leave_type,
            'description' => $request->description ?? '',
            'acronym'     => $request->acronym ?? '',
        ]);

        return back()->with('success', 'Leave type created.');
    }

    public function update(Request $request, LeaveType $leaveType)
    {
        $request->validate([
            'leave_type'  => ['required', 'string', 'max:100', "unique:leave_type,leave_type,{$leaveType->id}"],
            'description' => ['nullable', 'string', 'max:255'],
            'acronym'     => ['nullable', 'string', 'max:10'],
        ]);

        $leaveType->update([
            'leave_type'  => $request->leave_type,
            'description' => $request->description ?? '',
            'acronym'     => $request->acronym ?? '',
        ]);

        return back()->with('success', 'Leave type updated.');
    }

    public function destroy(LeaveType $leaveType)
    {
        $leaveType->delete();

        return back()->with('success', 'Leave type deleted.');
    }
}

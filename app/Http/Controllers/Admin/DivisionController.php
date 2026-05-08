<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Division;
use App\Models\Employee;
use App\Models\Unit;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DivisionController extends Controller
{
    public function index(): Response
    {
        $divisions = Division::with(['units.head', 'chief'])
            ->withCount('employees')
            ->orderBy('division_name')
            ->get()
            ->map(fn($d) => [
                'id'              => $d->id,
                'division_name'   => $d->division_name,
                'division_chief'  => $d->division_chief,
                'chief_name'      => $d->chief?->empName,
                'employees_count' => $d->employees_count,
                'units'           => $d->units->map(fn($u) => [
                    'id'        => $u->id,
                    'unit_name' => $u->unit_name,
                    'unit_head' => $u->unit_head,
                    'head_name' => $u->head?->empName,
                ])->values()->all(),
            ]);

        return Inertia::render('Admin/Divisions', [
            'divisions' => $divisions,
            'employees' => Employee::active()->orderBy('empName')->get(['badgeID', 'empName']),
        ]);
    }

    public function storeDivision(Request $request)
    {
        $request->validate([
            'division_name'  => ['required', 'string', 'max:100', 'unique:divisions,division_name'],
            'division_chief' => ['nullable', 'string', 'exists:employees,badgeID'],
        ]);

        Division::create($request->only(['division_name', 'division_chief']));
        return back()->with('success', 'Division created.');
    }

    public function updateDivision(Request $request, Division $division)
    {
        $request->validate([
            'division_name'  => ['required', 'string', 'max:100', "unique:divisions,division_name,{$division->id}"],
            'division_chief' => ['nullable', 'string', 'exists:employees,badgeID'],
        ]);

        $division->update($request->only(['division_name', 'division_chief']));
        return back()->with('success', 'Division updated.');
    }

    public function destroyDivision(Division $division)
    {
        $division->delete();
        return back()->with('success', 'Division deleted.');
    }

    public function storeUnit(Request $request)
    {
        $request->validate([
            'unit_name'   => ['required', 'string', 'max:100'],
            'division_id' => ['required', 'integer', 'exists:divisions,id'],
            'unit_head'   => ['nullable', 'string', 'exists:employees,badgeID'],
        ]);

        Unit::create($request->only(['unit_name', 'division_id', 'unit_head']));
        return back()->with('success', 'Unit created.');
    }

    public function updateUnit(Request $request, Unit $unit)
    {
        $request->validate([
            'unit_name'   => ['required', 'string', 'max:100'],
            'division_id' => ['required', 'integer', 'exists:divisions,id'],
            'unit_head'   => ['nullable', 'string', 'exists:employees,badgeID'],
        ]);

        $unit->update($request->only(['unit_name', 'division_id', 'unit_head']));
        return back()->with('success', 'Unit updated.');
    }

    public function destroyUnit(Unit $unit)
    {
        $unit->delete();
        return back()->with('success', 'Unit deleted.');
    }
}

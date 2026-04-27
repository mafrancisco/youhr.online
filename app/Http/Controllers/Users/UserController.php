<?php

namespace App\Http\Controllers\Users;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function index(): Response
    {
        $users = User::orderBy('fullname')->get(['id', 'username', 'fullname', 'email', 'type'])
            ->map(fn($u) => [
                'id'        => $u->id,
                'username'  => $u->username,
                'fullname'  => $u->fullname,
                'email'     => $u->email,
                'type'      => $u->type,
                'typeLabel' => $u->type === 1 ? 'HR Officer' : 'Employee',
            ]);

        $employees = Employee::active()->orderBy('empName')->get(['badgeID', 'empName', 'email']);

        return Inertia::render('Users/Index', ['users' => $users, 'employees' => $employees]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'badgeID'  => ['required', 'exists:employees,badgeID'],
            'username' => ['required', 'string', 'max:100', 'unique:users,username'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'type'     => ['required', 'in:1,2'],
        ]);

        $employee = Employee::where('badgeID', $request->badgeID)->firstOrFail();

        User::create([
            'username' => $request->username,
            'password' => Hash::make($request->password),
            'fullname' => $employee->empName,
            'email'    => $employee->email,
            'type'     => (int) $request->type,
        ]);

        return back()->with('success', 'User account created.');
    }

    public function destroy(Request $request, User $user)
    {
        abort_if($user->id === $request->user()->id, 403, 'Cannot delete your own account.');
        $user->delete();
        return back()->with('success', 'User account deleted.');
    }
}

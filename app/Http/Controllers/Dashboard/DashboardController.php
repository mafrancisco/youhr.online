<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        if ($request->user()->isHR()) {
            return Inertia::render('Dashboard/HR');
        }
        return Inertia::render('Dashboard/Employee');
    }
}

<?php

namespace App\Http\Controllers\DTR;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Services\DTRService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DTRController extends Controller
{
    public function __construct(private DTRService $dtrService) {}

    public function index(Request $request): Response
    {
        $employee = Employee::where('email', $request->user()->email)->firstOrFail();
        $month = $request->get('month', now()->format('Y-m'));

        $data = $this->dtrService->getMonthlyDTR($employee, $month);

        return Inertia::render('DTR/Index', $data);
    }
}

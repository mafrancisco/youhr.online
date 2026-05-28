<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\TimeDetectionSetting;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TimeDetectionController extends Controller
{
    public function index(): Response
    {
        $settings = TimeDetectionSetting::orderBy('id')->get();

        return Inertia::render('Admin/TimeDetection', [
            'settings' => $settings,
        ]);
    }

    public function update(Request $request, TimeDetectionSetting $setting)
    {
        $request->validate([
            'before_minutes' => ['required', 'integer', 'min:0', 'max:720'],
            'after_minutes'  => ['required', 'integer', 'min:0', 'max:720'],
            'pick_rule'      => ['required', 'in:earliest,latest'],
        ]);

        $setting->update($request->only(['before_minutes', 'after_minutes', 'pick_rule']));

        return back()->with('success', "{$setting->label} detection rules updated.");
    }
}

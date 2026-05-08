<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class SettingController extends Controller
{
    public function index(): Response
    {
        $settings = Setting::current();

        return Inertia::render('Admin/Settings', [
            'settings' => [
                'system_name'                    => $settings->system_name,
                'company_address'                => $settings->company_address,
                'authorized_signatory'           => $settings->authorized_signatory,
                'authorized_signatory_position'  => $settings->authorized_signatory_position,
                'logo_url'                       => $settings->logoUrl(),
            ],
        ]);
    }

    public function update(Request $request)
    {
        $request->validate([
            'system_name'                    => ['required', 'string', 'max:100'],
            'company_address'                => ['nullable', 'string', 'max:255'],
            'authorized_signatory'           => ['nullable', 'string', 'max:100'],
            'authorized_signatory_position'  => ['nullable', 'string', 'max:100'],
            'logo'                           => ['nullable', 'image', 'max:2048'],
        ]);

        $settings = Setting::current();
        $data = [
            'system_name'                    => $request->system_name,
            'company_address'                => $request->company_address   ?? '',
            'authorized_signatory'           => $request->authorized_signatory ?? '',
            'authorized_signatory_position'  => $request->authorized_signatory_position ?? '',
        ];

        if ($request->hasFile('logo')) {
            // Delete old logo
            if ($settings->logo_path) {
                Storage::disk('public')->delete($settings->logo_path);
            }
            $data['logo_path'] = $request->file('logo')->store('logos', 'public');
        }

        $settings->update($data);

        return back()->with('success', 'Settings saved successfully.');
    }

    public function deleteLogo()
    {
        $settings = Setting::current();
        if ($settings->logo_path) {
            Storage::disk('public')->delete($settings->logo_path);
            $settings->update(['logo_path' => '']);
        }
        return back()->with('success', 'Logo removed.');
    }
}

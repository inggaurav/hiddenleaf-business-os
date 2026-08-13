<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Setting;
use Inertia\Inertia;

class SettingController
{
    public function index()
    {
        $settings = Setting::all()->pluck('value', 'key');
        return Inertia::render('SuperAdmin/Settings/Index', [
            'settings' => $settings,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'site_name' => 'nullable|string|max:255',
            'default_currency' => 'nullable|string|max:10',
            'timezone' => 'nullable|string',
            'theme_color' => 'nullable|string',
        ]);

        foreach ($data as $key => $value) {
            Setting::updateOrCreate(
                ['key' => $key],
                ['value' => $value, 'created_by' => auth()->id()]
            );
        }

        return redirect()->back()->with('success', 'Settings updated successfully.');
    }
}

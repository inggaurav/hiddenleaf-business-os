<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class UpdateController extends Controller
{
    public function index()
    {
        $currentVersion = admin_setting('app_version', '1.0.0');

        return Inertia::render('Update/Index', [
            'currentVersion' => $currentVersion,
        ]);
    }

    public function update(Request $request)
    {
        $user = Auth::user();
        if ($user && ! $user->isSuperAdmin()) {
            abort(403, 'Unauthorized');
        }

        Artisan::call('migrate', ['--force' => true]);
        Artisan::call('cache:clear');
        Artisan::call('config:clear');
        Artisan::call('route:clear');
        Artisan::call('view:clear');

        Setting::updateOrCreate(
            ['key' => 'app_version', 'workspace_id' => null],
            ['value' => '1.1.0', 'created_by' => $user?->id]
        );

        return redirect()->back()->with('success', 'System updated to the latest version.');
    }
}

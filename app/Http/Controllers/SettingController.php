<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class SettingController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        $wsId = session('active_workspace_id');

        $systemSettings = Setting::whereNull('workspace_id')->pluck('value', 'key')->toArray();
        $workspaceSettings = $wsId ? Setting::where('workspace_id', $wsId)->pluck('value', 'key')->toArray() : [];

        return Inertia::render('Settings/Index', [
            'systemSettings' => $systemSettings,
            'workspaceSettings' => $workspaceSettings,
            'isSuperAdmin' => $user->isSuperAdmin(),
        ]);
    }

    public function store(Request $request)
    {
        $user = Auth::user();
        $wsId = session('active_workspace_id');
        $isTenant = ! $user->isSuperAdmin();

        $settings = $request->except(['_token', '_method']);

        foreach ($settings as $key => $value) {
            if ($request->hasFile($key)) {
                $file = $request->file($key);
                $path = $file->store('brand', 'public');
                $value = Storage::url($path);
            } elseif (is_array($value)) {
                $value = json_encode($value);
            }

            Setting::updateOrCreate(
                [
                    'key' => $key,
                    'workspace_id' => $isTenant ? $wsId : null,
                ],
                [
                    'value' => (string) $value,
                    'created_by' => $user->id,
                ]
            );
        }

        return redirect()->back()->with('success', 'Settings saved successfully.');
    }

    public function saveBrandSettings(Request $request)
    {
        return $this->store($request);
    }

    public function saveEmailSettings(Request $request)
    {
        return $this->store($request);
    }

    public function sendTestMail(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        try {
            Mail::raw('This is a test email sent from HiddenLeaf BusinessOS.', function ($message) use ($request) {
                $message->to($request->email)->subject('HiddenLeaf SMTP Test Mail');
            });

            return response()->json(['success' => true, 'message' => 'Test email sent successfully.']);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function saveStorageSettings(Request $request)
    {
        return $this->store($request);
    }

    public function saveBankTransferSettings(Request $request)
    {
        return $this->store($request);
    }

    public function saveCurrencySettings(Request $request)
    {
        return $this->store($request);
    }

    public function saveCookieSettings(Request $request)
    {
        return $this->store($request);
    }

    public function clearCache()
    {
        $user = Auth::user();
        if (! $user->isSuperAdmin()) {
            abort(403, 'Unauthorized');
        }

        Artisan::call('cache:clear');
        Artisan::call('config:clear');
        Artisan::call('route:clear');
        Artisan::call('view:clear');

        return redirect()->back()->with('success', 'Application cache cleared successfully.');
    }
}

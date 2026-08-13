<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use App\Models\Workspace;
use App\Services\HierarchicalSettingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class SettingController extends Controller
{
    public function __construct(private HierarchicalSettingService $settings) {}

    public function index()
    {
        $user = Auth::user();
        $wsId = session('active_workspace_id');
        $organization = Organization::find(session('active_organization_id'));
        $workspace = $wsId ? Workspace::find($wsId) : null;

        $systemSettings = $this->settings->values('platform', 0);
        $workspaceSettings = $workspace ? $this->settings->values('workspace', $workspace->id) : [];

        return Inertia::render('Settings/Index', [
            'systemSettings' => $systemSettings,
            'workspaceSettings' => $workspaceSettings,
            'resolvedSettings' => $this->settings->resolved($user, $organization, $workspace),
            'isSuperAdmin' => $user->isSuperAdmin(),
        ]);
    }

    public function store(Request $request)
    {
        $user = Auth::user();
        $wsId = session('active_workspace_id');
        $organization = Organization::find(session('active_organization_id'));
        $workspace = $wsId ? Workspace::find($wsId) : null;
        $scope = $request->input('_scope', $user->isSuperAdmin() ? 'platform' : 'workspace');
        $scopeId = $this->settings->authorize($user, $scope, $organization, $workspace);

        $settings = $request->except(['_token', '_method', '_scope']);

        foreach ($settings as $key => $value) {
            if ($request->hasFile($key)) {
                $file = $request->file($key);
                $request->validate([$key => ['file', 'max:5120', 'mimes:png,jpg,jpeg,webp,svg,ico']]);
                $path = $file->store('brand', 'public');
                $value = Storage::url($path);
            }

            $this->settings->put(
                $user,
                $scope,
                $scopeId,
                $key,
                $value,
                $organization,
                $workspace,
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

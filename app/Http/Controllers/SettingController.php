<?php

namespace App\Http\Controllers;

use App\Domain\Settings\SettingsSectionRegistry;
use App\Models\Organization;
use App\Models\Setting;
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
    public function __construct(
        private readonly HierarchicalSettingService $settings,
        private readonly SettingsSectionRegistry $sections,
    ) {}

    public function index()
    {
        $user = Auth::user();
        $wsId = session('active_workspace_id');
        $organization = Organization::find(session('active_organization_id'));
        $workspace = $wsId ? Workspace::find($wsId) : null;

        $systemSettings = $this->settings->values('platform', 0);
        $workspaceSettings = $workspace ? $this->settings->values('workspace', $workspace->id) : [];
        $visibleSections = $this->sections->visibleFor($user, $organization, $workspace);

        if (! $user->isSuperAdmin() && $workspace && $visibleSections === [] && ! $user->canInWorkspace('settings.view', $workspace)) {
            abort(403, 'You are not authorized to view administrative settings.');
        }

        return Inertia::render('Settings/Index', [
            'systemSettings' => $systemSettings,
            'workspaceSettings' => $workspaceSettings,
            'resolvedSettings' => $this->settings->resolved($user, $organization, $workspace),
            'isSuperAdmin' => $user->isSuperAdmin(),
            'settingsSections' => $visibleSections,
            'settingsLinks' => [
                'templates' => $user->isSuperAdmin() || ($workspace && $user->canInWorkspace('settings.notifications.manage', $workspace)),
                'webhooks' => $user->isSuperAdmin() || ($workspace && $user->canInWorkspace('webhooks.manage', $workspace)),
                'api' => $user->isSuperAdmin() || ($workspace && $user->canInWorkspace('settings.integrations.manage', $workspace)),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $user = Auth::user();
        $wsId = session('active_workspace_id');
        $organization = Organization::find(session('active_organization_id'));
        $workspace = $wsId ? Workspace::find($wsId) : null;
        $sectionId = $request->string('_section')->toString();

        if ($sectionId !== '') {
            $section = $this->sections->findVisible($sectionId, $user, $organization, $workspace);
            abort_unless($section, 403, 'This settings section is unavailable or unauthorized.');

            $scope = $section['scope'];
            $scopeId = $this->settings->authorize($user, $scope, $organization, $workspace, $section['permission']);
            $allowed = collect($section['fields'])->pluck('key')->all();
            $submitted = $request->input('values', []);
            abort_unless(is_array($submitted), 422, 'Settings values must be an object.');
            $settings = collect($submitted)->only($allowed)->all();

            foreach ($section['fields'] as $field) {
                $file = $request->file('values.'.$field['key']);
                if ($file) {
                    $request->validate(['values.'.$field['key'] => ['file', 'max:5120', 'mimes:png,jpg,jpeg,webp,svg,ico']]);
                    $settings[$field['key']] = Storage::url($file->store('brand', 'public'));
                }
            }
        } else {
            // Backward-compatible API for existing integrations. Scope authorization
            // remains strict; new UI and add-ons must use a declared section.
            $scope = $request->input('_scope', $user->isSuperAdmin() ? 'platform' : 'workspace');
            $permission = in_array($scope, ['organization', 'workspace'], true) ? 'settings.company.manage' : null;
            $scopeId = $this->settings->authorize($user, $scope, $organization, $workspace, $permission);
            $settings = $request->except(['_token', '_method', '_scope', '_section', 'values']);
        }

        foreach ($settings as $key => $value) {
            if ($sectionId === '' && $request->hasFile($key)) {
                $file = $request->file($key);
                $request->validate([$key => ['file', 'max:5120', 'mimes:png,jpg,jpeg,webp,svg,ico']]);
                $path = $file->store('brand', 'public');
                $value = Storage::url($path);
            }

            if ($value === '********' && Setting::where(['scope' => $scope, 'scope_id' => $scopeId, 'key' => $key, 'is_encrypted' => true])->exists()) {
                continue;
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
        $user = $request->user();
        $workspace = Workspace::find($request->session()->get('active_workspace_id'));
        abort_unless($user->isSuperAdmin() || ($workspace && $user->canInWorkspace('settings.notifications.manage', $workspace)), 403);

        $request->validate([
            'email' => 'required|email',
        ]);

        try {
            Mail::raw('This is a test email sent from HiddenLeaf BusinessOS.', function ($message) use ($request) {
                $message->to($request->email)->subject('HiddenLeaf SMTP Test Mail');
            });

            return back()->with('success', 'Test email sent successfully.');
        } catch (\Throwable $e) {
            return back()->withErrors(['email' => $e->getMessage()]);
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

<?php

namespace App\Http\Controllers\Onboarding;

use App\Domain\Settings\SettingsManager;
use App\Http\Controllers\Controller;
use App\Models\MrFoxBrandProfile;
use App\Models\Setting;
use App\Models\Workspace;
use App\Services\DemoDataSeederService;
use App\Services\TenantProvisioningService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OnboardingController extends Controller
{
    public function __construct(
        private TenantProvisioningService $provisioningService,
        private DemoDataSeederService $demoSeeder,
        private SettingsManager $settingsManager
    ) {}

    public function show(Request $request): Response|RedirectResponse
    {
        $user = $request->user();
        $wsId = (int) $request->session()->get('active_workspace_id');
        $workspace = Workspace::find($wsId) ?? $user->workspaces()->first();

        if (! $workspace) {
            // Self-provision if user has none
            $provisioned = $this->provisioningService->provision($user);
            $workspace = $provisioned['workspace'];
            $request->session()->put('active_workspace_id', $workspace->id);
            $request->session()->put('active_organization_id', $workspace->organization_id);
        }

        $brand = MrFoxBrandProfile::where('workspace_id', $workspace->id)->first();
        $isCompleted = (bool) Setting::where('workspace_id', $workspace->id)->where('key', 'onboarding_completed')->value('value');

        return Inertia::render('Onboarding/Wizard', [
            'workspace' => $workspace,
            'brand' => $brand,
            'isCompleted' => $isCompleted,
        ]);
    }

    public function updateStep(Request $request): JsonResponse
    {
        $user = $request->user();
        $wsId = (int) $request->session()->get('active_workspace_id');
        $workspace = Workspace::findOrFail($wsId);
        $step = (int) $request->input('step', 1);
        $data = $request->input('data', []);

        if ($step === 1) {
            // Business Details
            if (! empty($data['company_name'])) {
                $workspace->organization->update(['name' => $data['company_name']]);
                Setting::updateOrCreate(
                    ['scope' => 'workspace', 'scope_id' => $wsId, 'key' => 'company_name'],
                    ['organization_id' => $workspace->organization_id, 'workspace_id' => $wsId, 'value' => $data['company_name']]
                );
            }
            if (! empty($data['currency'])) {
                Setting::updateOrCreate(
                    ['scope' => 'workspace', 'scope_id' => $wsId, 'key' => 'site_currency'],
                    ['organization_id' => $workspace->organization_id, 'workspace_id' => $wsId, 'value' => $data['currency']]
                );
            }
            if (! empty($data['timezone'])) {
                Setting::updateOrCreate(
                    ['scope' => 'workspace', 'scope_id' => $wsId, 'key' => 'timezone'],
                    ['organization_id' => $workspace->organization_id, 'workspace_id' => $wsId, 'value' => $data['timezone']]
                );
            }
        } elseif ($step === 2) {
            // Brand Profile
            MrFoxBrandProfile::updateOrCreate([
                'organization_id' => $workspace->organization_id,
                'workspace_id' => $wsId,
            ], [
                'name' => $data['brand_name'] ?? $workspace->organization->name,
                'mission' => $data['company_description'] ?? '',
                'target_audience' => [$data['target_audience'] ?? 'General Business Clients'],
                'tone_of_voice' => [$data['tone_of_voice'] ?? 'Professional'],
                'is_default' => true,
                'created_by' => $user->id,
            ]);
        } elseif ($step === 5) {
            // Mr. Fox Settings
            if (! empty($data['default_model'])) {
                Setting::updateOrCreate(
                    ['scope' => 'workspace', 'scope_id' => $wsId, 'key' => 'mrfox_default_model'],
                    ['organization_id' => $workspace->organization_id, 'workspace_id' => $wsId, 'value' => $data['default_model']]
                );
            }
        }

        Setting::updateOrCreate(
            ['scope' => 'workspace', 'scope_id' => $wsId, 'key' => 'onboarding_current_step'],
            ['organization_id' => $workspace->organization_id, 'workspace_id' => $wsId, 'value' => (string) $step]
        );

        return response()->json(['success' => true]);
    }

    public function complete(Request $request): JsonResponse
    {
        $wsId = (int) $request->session()->get('active_workspace_id');
        $workspace = Workspace::findOrFail($wsId);

        Setting::updateOrCreate(
            ['scope' => 'workspace', 'scope_id' => $wsId, 'key' => 'onboarding_completed'],
            ['organization_id' => $workspace->organization_id, 'workspace_id' => $wsId, 'value' => '1']
        );

        return response()->json(['success' => true, 'redirect' => route('command-center.index')]);
    }

    public function loadDemoData(Request $request): JsonResponse
    {
        $user = $request->user();
        $wsId = (int) $request->session()->get('active_workspace_id');
        $workspace = Workspace::findOrFail($wsId);

        $result = $this->demoSeeder->seedDemoData($user, $workspace);

        return response()->json(['success' => true, 'data' => $result]);
    }

    public function resetDemoData(Request $request): JsonResponse
    {
        $wsId = (int) $request->session()->get('active_workspace_id');
        $workspace = Workspace::findOrFail($wsId);

        $deleted = $this->demoSeeder->resetDemoData($workspace);

        return response()->json(['success' => true, 'deleted_count' => $deleted]);
    }
}

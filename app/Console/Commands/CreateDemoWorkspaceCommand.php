<?php

namespace App\Console\Commands;

use App\Models\Plan;
use App\Models\Setting;
use App\Models\Subscription;
use App\Models\User;
use App\Models\UserActiveModule;
use App\Models\Workspace;
use App\Services\DemoDataSeederService;
use App\Services\TenantProvisioningService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class CreateDemoWorkspaceCommand extends Command
{
    protected $signature = 'hiddenleaf:demo-workspace
        {--email=demo@hiddenleaf.local : Demo owner email}
        {--password= : Set/replace the demo owner password; generated for a new owner when omitted}
        {--name=HiddenLeaf Demo : Demo organization name}
        {--reset : Remove existing demo business records and seed them again}';

    protected $description = 'Create or refresh a complete HiddenLeaf demo tenant using the canonical provisioning and demo-data services.';

    /** @var array<int, string> */
    private const DEMO_MODULES = [
        'core',
        'account',
        'sales',
        'procurement',
        'crm',
        'lead',
        'hrm',
        'productservice',
        'pos',
        'taskly',
        'landingpage',
        'helpdesk',
        'media',
        'communications',
        'automations',
        'missions',
        'command_center',
        'knowledge',
    ];

    public function handle(TenantProvisioningService $provisioner, DemoDataSeederService $demoData): int
    {
        $email = strtolower(trim((string) $this->option('email')));
        $companyName = trim((string) $this->option('name')) ?: 'HiddenLeaf Demo';
        $requestedPassword = $this->option('password');

        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error('A valid --email is required.');

            return self::FAILURE;
        }

        $generatedPassword = null;

        try {
            [$user, $workspace, $plan, $created] = DB::transaction(function () use ($email, $companyName, $requestedPassword, $provisioner, &$generatedPassword): array {
                $user = User::withTrashed()->where('email', $email)->first();
                $created = false;

                if (! $user) {
                    $generatedPassword = is_string($requestedPassword) && $requestedPassword !== ''
                        ? $requestedPassword
                        : Str::password(20);

                    $user = User::create([
                        'name' => 'HiddenLeaf Demo Owner',
                        'email' => $email,
                        'password' => Hash::make($generatedPassword),
                        'role' => 'company',
                        'is_active' => true,
                    ]);
                    $user->forceFill(['email_verified_at' => now()])->save();
                    $created = true;
                } else {
                    if (method_exists($user, 'trashed') && $user->trashed()) {
                        $user->restore();
                    }
                    $user->forceFill([
                        'is_active' => true,
                        'email_verified_at' => $user->email_verified_at ?: now(),
                        'role' => 'company',
                    ]);
                    if (is_string($requestedPassword) && $requestedPassword !== '') {
                        $user->password = Hash::make($requestedPassword);
                    }
                    $user->save();
                }

                $workspace = Workspace::query()
                    ->whereHas('organization', fn ($query) => $query->where('owner_id', $user->id)->where('name', $companyName))
                    ->oldest('id')
                    ->first();

                if (! $workspace) {
                    $provisioned = $provisioner->provision($user, [
                        'company_name' => $companyName,
                        'currency' => 'USD',
                        'currency_symbol' => '$',
                        'timezone' => 'UTC',
                        'industry' => 'Technology & Business Services',
                        'description' => 'Demonstration workspace for testing the complete HiddenLeaf Business OS and Mr. Fox.',
                        'target_audience' => 'SMBs, agencies and operations teams',
                    ]);
                    $workspace = $provisioned['workspace'];
                    $workspace->update(['name' => 'Demo Workspace']);
                }

                $plan = Plan::firstOrCreate(
                    ['name' => 'Demo'],
                    [
                        'description' => 'Internal demo plan with all currently testable HiddenLeaf modules enabled.',
                        'package_price_monthly' => 0,
                        'package_price_yearly' => 0,
                        'price_per_user_monthly' => 0,
                        'price_per_user_yearly' => 0,
                        'price_per_storage_monthly' => 0,
                        'price_per_storage_yearly' => 0,
                        'number_of_users' => 25,
                        'storage_limit' => 50,
                        'workspace_limit' => 1,
                        'modules' => self::DEMO_MODULES,
                        'trial' => true,
                        'trial_days' => 3650,
                        'free_plan' => true,
                        'status' => true,
                        'custom_plan' => false,
                        'created_by' => $user->id,
                    ]
                );
                $plan->forceFill([
                    'modules' => self::DEMO_MODULES,
                    'status' => true,
                    'number_of_users' => 25,
                    'workspace_limit' => 1,
                    'storage_limit' => 50,
                ])->save();

                $workspace->organization()->update([
                    'plan_id' => $plan->id,
                    'plan_expires_at' => now()->addYears(10),
                    'is_active' => true,
                ]);

                Subscription::updateOrCreate(
                    ['organization_id' => $workspace->organization_id],
                    [
                        'plan_id' => $plan->id,
                        'status' => 'active',
                        'starts_at' => now(),
                        'expires_at' => now()->addYears(10),
                    ]
                );

                foreach (self::DEMO_MODULES as $module) {
                    UserActiveModule::firstOrCreate([
                        'workspace_id' => $workspace->id,
                        'module_name' => $module,
                    ]);
                }

                foreach ([
                    'onboarding_completed' => '1',
                    'onboarding_current_step' => '6',
                    'mrfox_enabled' => '1',
                ] as $key => $value) {
                    Setting::updateOrCreate(
                        ['scope' => 'workspace', 'scope_id' => $workspace->id, 'key' => $key],
                        [
                            'organization_id' => $workspace->organization_id,
                            'workspace_id' => $workspace->id,
                            'value' => $value,
                            'is_encrypted' => false,
                        ]
                    );
                }

                return [$user, $workspace->fresh('organization'), $plan, $created];
            });

            if ($this->option('reset')) {
                $deleted = $demoData->resetDemoData($workspace);
                $this->line("Reset {$deleted} existing demo record(s).");
            }

            $counts = $demoData->seedDemoData($user, $workspace);

            $this->newLine();
            $this->info('HiddenLeaf demo workspace is ready.');
            $this->table(['Field', 'Value'], [
                ['Owner', $user->name],
                ['Email', $user->email],
                ['Organization', $workspace->organization->name],
                ['Workspace', $workspace->name],
                ['Workspace ID', (string) $workspace->id],
                ['Plan', $plan->name],
                ['Modules', implode(', ', self::DEMO_MODULES)],
                ['CRM leads', (string) ($counts['leads_count'] ?? 0)],
                ['Deals', (string) ($counts['deals_count'] ?? 0)],
                ['Products', (string) ($counts['products_count'] ?? 0)],
                ['Invoices', (string) ($counts['invoices_count'] ?? 0)],
                ['Purchase invoices', (string) ($counts['purchase_invoices_count'] ?? 0)],
                ['Employees', (string) ($counts['employees_count'] ?? 0)],
                ['Tickets', (string) ($counts['tickets_count'] ?? 0)],
                ['Inbox conversations', (string) ($counts['conversations_count'] ?? 0)],
                ['Tasks', (string) ($counts['tasks_count'] ?? 0)],
            ]);

            if ($created && $generatedPassword !== null) {
                $this->warn('Generated demo password (shown once): '.$generatedPassword);
            } elseif (is_string($requestedPassword) && $requestedPassword !== '') {
                $this->line('Demo password was set from --password.');
            } else {
                $this->line('Existing demo password was preserved.');
            }

            $this->newLine();
            $this->line('Re-run with --reset to refresh business demo records without deleting the workspace.');

            return self::SUCCESS;
        } catch (\Throwable $exception) {
            report($exception);
            $this->error('Unable to create demo workspace: '.$exception->getMessage());

            return self::FAILURE;
        }
    }
}

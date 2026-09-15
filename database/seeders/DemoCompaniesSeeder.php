<?php

namespace Database\Seeders;

use App\Domain\HRM\IndiaStatutoryCalculator;
use App\Models\AccountCustomer;
use App\Models\AccountVendor;
use App\Models\Addon;
use App\Models\CommunicationAccount;
use App\Models\CommunicationConversation;
use App\Models\CommunicationMessage;
use App\Models\CrmDeal;
use App\Models\CrmLead;
use App\Models\CrmPipeline;
use App\Models\CrmStage;
use App\Models\HelpdeskCategory;
use App\Models\HelpdeskTicket;
use App\Models\HrEmployee;
use App\Models\Organization;
use App\Models\Permission;
use App\Models\Plan;
use App\Models\ProductServiceCategory;
use App\Models\ProductServiceItem;
use App\Models\PurchaseInvoice;
use App\Models\Role;
use App\Models\SalesInvoice;
use App\Models\SalesInvoiceItem;
use App\Models\SalesProposal;
use App\Models\Subscription;
use App\Models\TasklyProject;
use App\Models\TasklyStage;
use App\Models\TasklyTask;
use App\Models\User;
use App\Models\UserActiveModule;
use App\Models\Warehouse;
use App\Models\WarehouseStock;
use App\Models\Workspace;
use App\Models\WorkspaceAddon;
use HiddenLeaf\CrmDealsKanban\Models\CrmDealActivity;
use HiddenLeaf\NoticeBoard\Models\Notice;
use HiddenLeaf\SuggestionBox\Models\Suggestion;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DemoCompaniesSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Ensure Super Administrator Exists
        $superAdmin = User::firstOrCreate(
            ['email' => 'superadmin@hiddenleaf.io'],
            [
                'name' => 'Super Administrator',
                'password' => Hash::make('password'),
                'role' => 'super_admin',
                'is_active' => true,
                'email_verified_at' => now(),
            ]
        );

        // 2. Ensure Demo User (demo@gmail.com / demo@123)
        $demoUser = User::updateOrCreate(
            ['email' => 'demo@gmail.com'],
            [
                'name' => 'Demo Executive',
                'password' => Hash::make('demo@123'),
                'role' => 'company_admin',
                'is_active' => true,
                'email_verified_at' => now(),
            ]
        );

        // 3. Enterprise Plan with all capabilities
        $allModuleAliases = [
            'account', 'hrm', 'lead', 'crm', 'crm-deals-kanban', 'taskly', 'pos',
            'landingpage', 'productservice', 'sales', 'procurement', 'portal',
            'notice-board', 'suggestion-box', 'ai-advisor', 'smart-analytics', 'sms',
        ];

        $plan = Plan::firstOrCreate(
            ['name' => 'Enterprise Full Suite'],
            [
                'description' => 'Unlimited access to all modules, plugins, multi-currency, and statutory payroll',
                'package_price_monthly' => 99.00,
                'package_price_yearly' => 990.00,
                'number_of_users' => 100,
                'workspace_limit' => 20,
                'storage_limit' => 20480,
                'modules' => $allModuleAliases,
                'status' => true,
            ]
        );

        // 4. Admin Role for Workspace
        $adminRole = Role::firstOrCreate(
            ['name' => 'workspace-admin', 'organization_id' => null],
            ['display_name' => 'Workspace Admin', 'is_system' => true]
        );
        $adminRole->permissions()->sync(Permission::pluck('id')->toArray());

        // 5. Build 3 distinct demo companies
        $companiesData = [
            [
                'name' => 'Apex Cloud Technologies',
                'slug' => 'apex-cloud',
                'brand_name' => 'Apex Cloud',
                'brand_primary_color' => '#6366f1',
                'brand_footer_text' => 'Cloud Infrastructure & Enterprise Solutions',
                'workspaces' => [
                    ['name' => 'Apex Global HQ', 'slug' => 'apex-hq'],
                    ['name' => 'Cloud Research Labs', 'slug' => 'apex-labs'],
                ],
                'currency' => 'USD',
                'country' => 'United States',
            ],
            [
                'name' => 'Zenith Retail & Logistics',
                'slug' => 'zenith-retail',
                'brand_name' => 'Zenith Retail',
                'brand_primary_color' => '#10b981',
                'brand_footer_text' => 'Omnichannel POS & Supply Chain Network',
                'workspaces' => [
                    ['name' => 'Flagship Megastore (POS)', 'slug' => 'zenith-pos'],
                    ['name' => 'Central Logistics Hub', 'slug' => 'zenith-logistics'],
                ],
                'currency' => 'EUR',
                'country' => 'Germany',
            ],
            [
                'name' => 'Bharat Innovations Pvt Ltd',
                'slug' => 'bharat-innovations',
                'brand_name' => 'Bharat Innovations',
                'brand_primary_color' => '#f59e0b',
                'brand_footer_text' => 'Digital Transformation & Indian Statutory Payroll Hub',
                'workspaces' => [
                    ['name' => 'Bengaluru Tech Park', 'slug' => 'bharat-bengaluru'],
                    ['name' => 'Mumbai Operations Center', 'slug' => 'bharat-mumbai'],
                ],
                'currency' => 'INR',
                'country' => 'India',
            ],
        ];

        foreach ($companiesData as $cIndex => $companyInfo) {
            $org = Organization::updateOrCreate(
                ['slug' => $companyInfo['slug']],
                [
                    'name' => $companyInfo['name'],
                    'owner_id' => $demoUser->id,
                    'plan_id' => $plan->id,
                    'plan_expires_at' => now()->addYear(),
                    'is_active' => true,
                    'brand_name' => $companyInfo['brand_name'],
                    'brand_primary_color' => $companyInfo['brand_primary_color'],
                    'brand_footer_text' => $companyInfo['brand_footer_text'],
                    'settings' => [
                        'currency' => $companyInfo['currency'],
                        'country' => $companyInfo['country'],
                    ],
                ]
            );

            // Attach demoUser as owner
            $org->members()->syncWithoutDetaching([
                $demoUser->id => ['role' => 'owner'],
            ]);

            Subscription::updateOrCreate(
                ['organization_id' => $org->id],
                [
                    'plan_id' => $plan->id,
                    'status' => 'active',
                    'starts_at' => now(),
                    'expires_at' => now()->addYear(),
                ]
            );

            foreach ($companyInfo['workspaces'] as $wIndex => $wsInfo) {
                $ws = Workspace::updateOrCreate(
                    ['organization_id' => $org->id, 'slug' => $wsInfo['slug']],
                    [
                        'name' => $wsInfo['name'],
                        'created_by' => $demoUser->id,
                    ]
                );

                $ws->members()->syncWithoutDetaching([
                    $demoUser->id => ['role_id' => $adminRole->id],
                ]);

                // Activate all modules
                foreach ($allModuleAliases as $mod) {
                    UserActiveModule::firstOrCreate([
                        'workspace_id' => $ws->id,
                        'module_name' => $mod,
                    ]);
                }

                // Activate Addon records if Addons exist
                foreach (['crm-deals-kanban', 'hrm', 'notice-board', 'suggestion-box', 'ai-advisor', 'smart-analytics', 'sms'] as $addonAlias) {
                    $addon = Addon::where('alias', $addonAlias)->first();
                    if ($addon) {
                        WorkspaceAddon::updateOrCreate(
                            ['workspace_id' => $ws->id, 'addon_id' => $addon->id],
                            ['is_active' => true]
                        );
                    }
                }

                // Populate Comprehensive Demo Data for each workspace
                $this->seedRichWorkspaceData($demoUser, $org, $ws, $companyInfo, $cIndex, $wIndex);
            }
        }
    }

    private function seedRichWorkspaceData(User $user, Organization $org, Workspace $ws, array $comp, int $cIdx, int $wIdx): void
    {
        $orgId = $org->id;
        $wsId = $ws->id;
        $isIndia = ($comp['country'] === 'India');

        // 1. Accounting: Customers, Vendors, Invoices & Proposals
        $cust1 = AccountCustomer::updateOrCreate([
            'organization_id' => $orgId,
            'workspace_id' => $wsId,
            'email' => "billing@client-{$cIdx}-{$wIdx}.com",
        ], [
            'name' => "Starlight Global Corp ({$ws->name})",
            'contact' => '+1 555-0199',
            'billing_address' => '100 Enterprise Way, Suite 400',
            'billing_city' => 'Metropolis',
            'billing_country' => $comp['country'],
            'is_active' => true,
            'created_by' => $user->id,
        ]);

        $vendor1 = AccountVendor::updateOrCreate([
            'organization_id' => $orgId,
            'workspace_id' => $wsId,
            'email' => "procurement@vendor-{$cIdx}-{$wIdx}.com",
        ], [
            'name' => "Apex Hardware & Semiconductor Supply",
            'contact' => '+1 555-0822',
            'billing_address' => '500 Tech Blvd',
            'billing_city' => 'Silicon City',
            'billing_country' => $comp['country'],
            'is_active' => true,
            'created_by' => $user->id,
        ]);

        // 2. Catalog & Inventory
        $category = ProductServiceCategory::updateOrCreate([
            'organization_id' => $orgId,
            'workspace_id' => $wsId,
            'name' => 'Enterprise Products & Cloud Services',
        ], [
            'type' => 'product',
            'color' => $comp['brand_primary_color'],
        ]);

        $item1 = ProductServiceItem::updateOrCreate([
            'organization_id' => $orgId,
            'workspace_id' => $wsId,
            'sku' => "SKU-PROD-{$cIdx}-{$wIdx}",
        ], [
            'category_id' => $category->id,
            'name' => "High-Throughput Edge Server Node {$cIdx}",
            'type' => 'product',
            'reorder_level' => 15,
            'sale_price' => $isIndia ? 145000.00 : 2850.00,
            'purchase_price' => $isIndia ? 95000.00 : 1800.00,
            'is_active' => true,
            'created_by' => $user->id,
        ]);

        $item2 = ProductServiceItem::updateOrCreate([
            'organization_id' => $orgId,
            'workspace_id' => $wsId,
            'sku' => "SKU-SRV-{$cIdx}-{$wIdx}",
        ], [
            'category_id' => $category->id,
            'name' => 'Annual 24/7 SLA Support Agreement',
            'type' => 'service',
            'reorder_level' => 0,
            'sale_price' => $isIndia ? 250000.00 : 5000.00,
            'purchase_price' => 0.00,
            'is_active' => true,
            'created_by' => $user->id,
        ]);

        $warehouse = Warehouse::updateOrCreate([
            'organization_id' => $orgId,
            'workspace_id' => $wsId,
            'name' => "Main Distribution Depot - {$ws->name}",
        ], [
            'address' => 'Depot Complex 4',
            'city' => $isIndia ? 'Bengaluru' : 'Frankfurt',
            'city_zip' => '560001',
        ]);

        WarehouseStock::updateOrCreate([
            'product_id' => $item1->id,
            'warehouse_id' => $warehouse->id,
        ], [
            'quantity' => 28,
        ]);

        // 3. Sales Invoices & Proposals
        $invoice = SalesInvoice::updateOrCreate([
            'organization_id' => $orgId,
            'workspace_id' => $wsId,
            'invoice_id' => "INV-{$comp['slug']}-{$wIdx}-101",
        ], [
            'customer_id' => $cust1->id,
            'issue_date' => now()->subDays(15)->toDateString(),
            'due_date' => now()->addDays(15)->toDateString(),
            'total_amount' => $isIndia ? 395000.00 : 7850.00,
            'status' => 1,
        ]);

        SalesInvoiceItem::updateOrCreate([
            'invoice_id' => $invoice->id,
        ], [
            'product_id' => $item1->id,
            'item_name' => $item1->name,
            'quantity' => 1,
            'price' => $isIndia ? 145000.00 : 2850.00,
        ]);

        SalesProposal::updateOrCreate([
            'organization_id' => $orgId,
            'workspace_id' => $wsId,
            'proposal_id' => "PROP-{$comp['slug']}-{$wIdx}-201",
        ], [
            'customer_id' => $cust1->id,
            'issue_date' => now()->subDays(5)->toDateString(),
            'status' => 'sent',
            'total_amount' => $isIndia ? 500000.00 : 10000.00,
        ]);

        PurchaseInvoice::updateOrCreate([
            'organization_id' => $orgId,
            'workspace_id' => $wsId,
            'invoice_id' => "PINV-{$comp['slug']}-{$wIdx}-301",
        ], [
            'vendor_id' => $vendor1->id,
            'warehouse_id' => $warehouse->id,
            'purchase_date' => now()->subDays(8)->toDateString(),
            'due_date' => now()->addDays(20)->toDateString(),
            'total_amount' => $isIndia ? 190000.00 : 3600.00,
            'status' => 1,
            'created_by' => $user->id,
        ]);

        // 4. CRM: Pipelines, Stages, Leads, Deals Kanban & Activities
        $pipeline = CrmPipeline::updateOrCreate([
            'organization_id' => $orgId,
            'workspace_id' => $wsId,
            'name' => 'Strategic Enterprise Pipeline',
        ], ['is_default' => true]);

        $stage1 = CrmStage::updateOrCreate(['pipeline_id' => $pipeline->id, 'name' => 'Discovery'], ['position' => 0, 'probability' => 20]);
        $stage2 = CrmStage::updateOrCreate(['pipeline_id' => $pipeline->id, 'name' => 'Proposal Sent'], ['position' => 1, 'probability' => 60]);
        $stageWon = CrmStage::updateOrCreate(['pipeline_id' => $pipeline->id, 'name' => 'Won'], ['position' => 2, 'probability' => 100, 'outcome' => 'won', 'is_closed' => true]);

        $lead = CrmLead::updateOrCreate([
            'organization_id' => $orgId,
            'workspace_id' => $wsId,
            'email' => "vp.tech@client-lead-{$cIdx}.com",
        ], [
            'pipeline_id' => $pipeline->id,
            'stage_id' => $stage1->id,
            'name' => "Global Cloud Expansion - Lead {$cIdx}",
            'company' => 'Aetherius Aerospace',
            'estimated_value' => $isIndia ? 1200000.00 : 45000.00,
            'status' => 'open',
            'created_by' => $user->id,
        ]);

        $deal = CrmDeal::updateOrCreate([
            'organization_id' => $orgId,
            'workspace_id' => $wsId,
            'name' => "Mission-Critical Infrastructure Contract {$cIdx}-{$wIdx}",
        ], [
            'lead_id' => $lead->id,
            'pipeline_id' => $pipeline->id,
            'stage_id' => $stage2->id,
            'value' => $isIndia ? 2500000.00 : 85000.00,
            'probability' => 60,
            'expected_close_date' => now()->addDays(30)->toDateString(),
            'source' => 'Direct Referral',
            'status' => 'open',
            'position' => 0,
            'assigned_to' => $user->id,
        ]);

        CrmDealActivity::updateOrCreate([
            'deal_id' => $deal->id,
            'subject' => 'Executive Architecture Review Meeting',
        ], [
            'user_id' => $user->id,
            'type' => 'meeting',
            'body' => 'Reviewed high-availability failover topology and pricing model with client executive team.',
            'scheduled_at' => now()->addDays(2),
        ]);

        // 5. HRM: Employees, India Statutory Fields, Payslips & Attendance
        $emp1 = HrEmployee::updateOrCreate([
            'organization_id' => $orgId,
            'workspace_id' => $wsId,
            'employee_number' => "EMP-{$cIdx}-{$wIdx}-001",
        ], [
            'name' => $isIndia ? 'Rajesh Kumar' : 'Alexander Schmidt',
            'email' => "emp1.{$comp['slug']}.{$wIdx}@hiddenleaf.local",
            'joined_at' => now()->subMonths(14)->toDateString(),
            'basic_salary' => $isIndia ? 85000.00 : 6500.00,
            'status' => 'active',
            'country' => $comp['country'],
            'pf_number' => $isIndia ? 'PF/BOM/0019283/000' : null,
            'uan_number' => $isIndia ? '100982347123' : null,
            'esi_number' => $isIndia ? '31000987654321' : null,
            'pan_number' => $isIndia ? 'ABCDE1234F' : null,
        ]);

        $emp2 = HrEmployee::updateOrCreate([
            'organization_id' => $orgId,
            'workspace_id' => $wsId,
            'employee_number' => "EMP-{$cIdx}-{$wIdx}-002",
        ], [
            'name' => $isIndia ? 'Ananya Sen' : 'Elena Rostova',
            'email' => "emp2.{$comp['slug']}.{$wIdx}@hiddenleaf.local",
            'joined_at' => now()->subMonths(8)->toDateString(),
            'basic_salary' => $isIndia ? 55000.00 : 4800.00,
            'status' => 'active',
            'country' => $comp['country'],
            'pf_number' => $isIndia ? 'PF/BOM/0019284/000' : null,
            'uan_number' => $isIndia ? '100982347124' : null,
            'esi_number' => $isIndia ? '31000987654322' : null,
        ]);

        // 6. Taskly: Projects & Kanban Tasks
        $project = TasklyProject::updateOrCreate([
            'organization_id' => $orgId,
            'workspace_id' => $wsId,
            'name' => "{$comp['brand_name']} Platform Deployment Phase {$wIdx}",
        ], [
            'status' => 'in_progress',
            'created_by' => $user->id,
        ]);

        $stageTodo = TasklyStage::updateOrCreate(['project_id' => $project->id, 'name' => 'To Do'], ['position' => 0]);
        $stageDoing = TasklyStage::updateOrCreate(['project_id' => $project->id, 'name' => 'In Progress'], ['position' => 1]);
        $stageDone = TasklyStage::updateOrCreate(['project_id' => $project->id, 'name' => 'Completed'], ['position' => 2]);

        TasklyTask::updateOrCreate([
            'organization_id' => $orgId,
            'workspace_id' => $wsId,
            'project_id' => $project->id,
            'title' => "Deploy Hybrid Multi-Cloud Cluster {$cIdx}",
        ], [
            'stage_id' => $stageDoing->id,
            'priority' => 'high',
            'due_on' => now()->addDays(14)->toDateString(),
        ]);

        // 7. Helpdesk & Unified Communications
        $tktCat = HelpdeskCategory::firstOrCreate([
            'name' => 'Infrastructure & Cloud Support',
        ]);

        HelpdeskTicket::updateOrCreate([
            'organization_id' => $orgId,
            'workspace_id' => $wsId,
            'ticket_id' => "TKT-{$comp['slug']}-{$wIdx}-101",
        ], [
            'category_id' => $tktCat->id,
            'name' => $cust1->name,
            'email' => $cust1->email,
            'subject' => 'SSL Certificate Provisioning & Subdomain Routing',
            'status' => 'open',
            'priority' => 'medium',
            'description' => 'Verify DNS propagation and ensure automatic SSL renewal is active across edge reverse proxies.',
            'created_by' => $user->id,
        ]);

        $commAccount = CommunicationAccount::updateOrCreate([
            'organization_id' => $orgId,
            'workspace_id' => $wsId,
            'provider' => 'internal',
            'external_account_id' => "comm_inbox_{$cIdx}_{$wIdx}",
        ], [
            'display_name' => "{$ws->name} Communication Hub",
            'status' => 'connected',
        ]);

        $conversation = CommunicationConversation::updateOrCreate([
            'organization_id' => $orgId,
            'workspace_id' => $wsId,
            'external_thread_id' => "thread_{$cIdx}_{$wIdx}_sla",
        ], [
            'account_id' => $commAccount->id,
            'provider' => 'email',
            'subject' => "Enterprise SLA & Response Matrix - {$comp['name']}",
            'participant_name' => 'Dr. Aris Thorne',
            'participant_identifier' => 'athorne@synergy.global',
            'last_message_preview' => 'Our legal and technical teams have green-lit the updated deployment schedule.',
            'last_message_at' => now()->subHours(2),
            'priority_score' => 92,
            'status' => 'open',
            'unread_count' => 1,
        ]);

        CommunicationMessage::updateOrCreate([
            'organization_id' => $orgId,
            'workspace_id' => $wsId,
            'conversation_id' => $conversation->id,
            'provider_message_id' => "msg_{$cIdx}_{$wIdx}_001",
        ], [
            'direction' => 'inbound',
            'sender_name' => 'Dr. Aris Thorne',
            'sender_identifier' => 'athorne@synergy.global',
            'body_text' => 'Our legal and technical teams have green-lit the updated deployment schedule. Looking forward to kick-off!',
            'delivery_status' => 'delivered',
            'sent_at' => now()->subHours(2),
        ]);

        // 8. Notice Board Announcements
        Notice::updateOrCreate([
            'organization_id' => $orgId,
            'workspace_id' => $wsId,
            'title' => "Quarterly All-Hands & Technical Milestone Celebration ({$ws->name})",
        ], [
            'description' => 'Join the executive leadership team this Friday as we review quarterly OKRs and celebrate our recent platform release.',
            'start_date' => now()->subDays(2)->toDateString(),
            'expiry_date' => now()->addDays(30)->toDateString(),
            'is_pinned' => true,
            'priority' => 'urgent',
            'target_type' => 'all',
            'status' => 'published',
            'creator_id' => $user->id,
        ]);

        // 9. Suggestion Box Ideas
        Suggestion::updateOrCreate([
            'organization_id' => $orgId,
            'workspace_id' => $wsId,
            'title' => 'Introduce AI-Powered Autonomous Lead Qualification Assistant',
        ], [
            'description' => 'Integrate Mr. Fox AI directly with inbound webform submissions to instantly score deals and suggest optimal pipeline stages.',
            'status' => 'accepted',
            'user_id' => $user->id,
            'is_anonymous' => false,
            'votes_count' => 18,
            'views_count' => 74,
            'admin_response' => 'Approved and scheduled for implementation in the upcoming release cycle!',
            'responded_by' => $user->id,
            'responded_at' => now()->subDays(1),
        ]);
    }
}

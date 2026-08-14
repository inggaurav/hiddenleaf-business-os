<?php

namespace Tests\Feature;

use App\Models\Domain\SaaS\Order;
use App\Models\Domain\SaaS\Plan;
use App\Models\Domain\SaaS\Subscription;
use App\Models\Organization;
use App\Models\ProductServiceItem;
use App\Models\TasklyProject;
use App\Models\TasklyTask;
use App\Models\User;
use App\Models\UserActiveModule;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DashboardContractsTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_dashboard_uses_real_platform_metrics(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin']);
        $owner = User::factory()->create();
        $plan = Plan::create(['name' => 'Growth', 'status' => true, 'modules' => ['taskly'], 'created_by' => $admin->id]);
        $organization = Organization::factory()->create(['owner_id' => $owner->id, 'plan_id' => $plan->id]);
        $workspace = Workspace::factory()->create(['organization_id' => $organization->id, 'created_by' => $owner->id]);
        Subscription::create(['organization_id' => $organization->id, 'plan_id' => $plan->id, 'status' => 'active', 'starts_at' => now(), 'expires_at' => now()->addMonth()]);
        Order::create(['order_id' => 'DASH-PAID-1', 'organization_id' => $organization->id, 'user_id' => $owner->id, 'plan_id' => $plan->id, 'price' => 125, 'currency' => 'USD', 'payment_status' => 'paid']);
        UserActiveModule::create(['workspace_id' => $workspace->id, 'module_name' => 'taskly']);

        $this->actingAs($admin)->get('/super-admin/dashboard')->assertInertia(fn (Assert $page) => $page
            ->component('SuperAdmin/Dashboard')
            ->where('metrics.organizations', 1)
            ->where('metrics.workspaces', 1)
            ->where('metrics.active_subscriptions', 1)
            ->where('metrics.revenue', 125)
            ->where('metrics.active_modules', 1));
    }

    public function test_workspace_and_module_metrics_are_real_and_tenant_scoped(): void
    {
        $owner = User::factory()->create();
        $plan = Plan::create(['name' => 'Modules', 'status' => true, 'modules' => ['account', 'hrm', 'lead', 'taskly', 'pos', 'productservice'], 'created_by' => $owner->id]);
        $organization = Organization::factory()->create(['owner_id' => $owner->id, 'plan_id' => $plan->id]);
        $workspace = Workspace::factory()->create(['organization_id' => $organization->id, 'created_by' => $owner->id]);
        $organization->members()->attach($owner, ['role' => 'owner']);
        $workspace->members()->attach($owner);
        foreach ($plan->modules as $module) {
            UserActiveModule::create(['workspace_id' => $workspace->id, 'module_name' => $module]);
        }

        ProductServiceItem::create(['name' => 'Local Product', 'sku' => 'LOCAL-1', 'type' => 'product', 'sale_price' => 20, 'purchase_price' => 10, 'organization_id' => $organization->id, 'workspace_id' => $workspace->id, 'created_by' => $owner->id]);
        $project = TasklyProject::create(['organization_id' => $organization->id, 'workspace_id' => $workspace->id, 'name' => 'Local Project', 'status' => 'active', 'created_by' => $owner->id]);
        $stage = DB::table('taskly_stages')->insertGetId(['project_id' => $project->id, 'name' => 'Backlog', 'position' => 0, 'is_complete' => false, 'created_at' => now(), 'updated_at' => now()]);
        TasklyTask::create(['organization_id' => $organization->id, 'workspace_id' => $workspace->id, 'project_id' => $project->id, 'stage_id' => $stage, 'title' => 'Local Task', 'priority' => 'medium', 'created_by' => $owner->id]);

        $foreignOwner = User::factory()->create();
        $foreignOrganization = Organization::factory()->create(['owner_id' => $foreignOwner->id]);
        $foreignWorkspace = Workspace::factory()->create(['organization_id' => $foreignOrganization->id, 'created_by' => $foreignOwner->id]);
        ProductServiceItem::create(['name' => 'Foreign Product', 'sku' => 'FOREIGN-1', 'type' => 'product', 'sale_price' => 999, 'purchase_price' => 999, 'organization_id' => $foreignOrganization->id, 'workspace_id' => $foreignWorkspace->id, 'created_by' => $foreignOwner->id]);

        $request = $this->actingAs($owner)->withSession(['active_organization_id' => $organization->id, 'active_workspace_id' => $workspace->id]);
        $request->get('/dashboard')->assertInertia(fn (Assert $page) => $page->where('metrics.products', 1));
        $request->get('/product-service')->assertInertia(fn (Assert $page) => $page->where('metrics.products', 1));
        $request->get('/taskly')->assertInertia(fn (Assert $page) => $page->where('metrics.projects', 1)->where('metrics.tasks', 1));
        $request->get('/hrm')->assertInertia(fn (Assert $page) => $page->has('metrics'));
        $request->get('/crm')->assertInertia(fn (Assert $page) => $page->has('metrics'));
        $request->get('/pos')->assertInertia(fn (Assert $page) => $page->has('metrics'));
        $request->get('/accounting/accounts')->assertInertia(fn (Assert $page) => $page->has('metrics'));
        $request->get('/sales-invoices')->assertInertia(fn (Assert $page) => $page->has('metrics'));
    }
}

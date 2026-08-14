<?php

namespace Tests\Feature\Dashboards;

use App\Models\HelpdeskTicket;
use App\Models\Organization;
use App\Models\Plan;
use App\Models\PurchaseInvoice;
use App\Models\SalesInvoice;
use App\Models\TasklyProject;
use App\Models\TasklyTask;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ExecutiveDashboardSemanticsTest extends TestCase
{
    use RefreshDatabase;

    public function test_executive_dashboard_metrics_and_semantics_match_exact_domains(): void
    {
        $user = User::factory()->create(['name' => 'CEO User', 'role' => 'company_admin']);
        $plan = Plan::create(['name' => 'Scale', 'status' => true, 'created_by' => $user->id]);
        $org = Organization::factory()->create(['owner_id' => $user->id, 'plan_id' => $plan->id]);
        $ws = Workspace::factory()->create(['organization_id' => $org->id, 'created_by' => $user->id]);
        $org->members()->attach($user, ['role' => 'owner']);
        $ws->members()->attach($user);

        // Sales Invoices
        SalesInvoice::create([
            'organization_id' => $org->id,
            'workspace_id' => $ws->id,
            'invoice_id' => 1001,
            'status' => 'paid',
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addDays(30)->toDateString(),
            'total_amount' => 12500.50,
            'created_by' => $user->id,
        ]);

        // Purchase Invoices
        PurchaseInvoice::create([
            'organization_id' => $org->id,
            'workspace_id' => $ws->id,
            'invoice_id' => 2001,
            'status' => 'posted',
            'purchase_date' => now()->toDateString(),
            'due_date' => now()->addDays(15)->toDateString(),
            'total_amount' => 4500.00,
            'created_by' => $user->id,
        ]);

        // Projects & Open Tasks
        $project = TasklyProject::create([
            'organization_id' => $org->id,
            'workspace_id' => $ws->id,
            'name' => 'Project Alpha',
            'status' => 'active',
            'created_by' => $user->id,
        ]);

        $stageId = DB::table('taskly_stages')->insertGetId([
            'project_id' => $project->id,
            'name' => 'In Progress',
            'position' => 0,
            'is_complete' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        TasklyTask::create([
            'organization_id' => $org->id,
            'workspace_id' => $ws->id,
            'project_id' => $project->id,
            'stage_id' => $stageId,
            'title' => 'Open Task 1',
            'priority' => 'high',
            'completed_at' => null,
            'created_by' => $user->id,
        ]);

        TasklyTask::create([
            'organization_id' => $org->id,
            'workspace_id' => $ws->id,
            'project_id' => $project->id,
            'stage_id' => $stageId,
            'title' => 'Completed Task 1',
            'priority' => 'low',
            'completed_at' => now(),
            'created_by' => $user->id,
        ]);

        // Tickets
        HelpdeskTicket::create([
            'organization_id' => $org->id,
            'workspace_id' => $ws->id,
            'ticket_id' => 'TICK-1001',
            'name' => 'Alice Customer',
            'email' => 'alice@customer.local',
            'subject' => 'Need Help with Setup',
            'description' => 'Detailed description of ticket',
            'status' => 'open',
            'priority' => 'medium',
            'created_by' => $user->id,
        ]);

        $response = $this->actingAs($user)
            ->withSession(['active_organization_id' => $org->id, 'active_workspace_id' => $ws->id])
            ->get('/dashboard');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->where('metrics.sales', 12500.5)
            ->where('metrics.paid_invoices', 1)
            ->where('metrics.purchases', 4500)
            ->where('metrics.posted_purchases', 1)
            ->where('metrics.active_projects', 1)
            ->where('metrics.open_tasks', 1)
            ->where('metrics.open_tickets', 1)
            ->where('metrics.resolved_tickets', 0)
        );
    }
}

<?php

namespace Tests\Feature\Parity;

use App\Models\AccountCustomer;
use App\Models\AccountVendor;
use App\Models\HrEmployee;
use App\Models\Organization;
use App\Models\Role;
use App\Models\SalesProposal;
use App\Models\TasklyProject;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class WorkdoRemainingSurfaceParityTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;
    private Organization $organization;
    private Workspace $workspace;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->owner = User::factory()->create(['role' => 'company_admin']);
        $this->organization = Organization::factory()->create(['owner_id' => $this->owner->id]);
        $this->workspace = Workspace::factory()->create(['organization_id' => $this->organization->id, 'created_by' => $this->owner->id]);
        $this->organization->members()->attach($this->owner, ['role' => 'owner']);
        $this->workspace->members()->attach($this->owner);
        $this->entitleWorkspaceModules($this->organization, $this->workspace, $this->owner, ['account', 'hrm', 'lead', 'taskly', 'landingpage', 'sales', 'procurement']);
    }

    public function test_client_and_vendor_portals_are_party_scoped_and_operational(): void
    {
        $client = User::factory()->create(['role' => 'client']);
        $clientRole = Role::where('name', 'client')->whereNull('organization_id')->firstOrFail();
        $this->organization->members()->attach($client, ['role' => 'client']);
        $this->workspace->members()->attach($client, ['role_id' => $clientRole->id]);
        $customer = AccountCustomer::create(['organization_id' => $this->organization->id, 'workspace_id' => $this->workspace->id, 'user_id' => $client->id, 'name' => 'Portal Client', 'email' => $client->email, 'balance' => 125, 'is_active' => true, 'created_by' => $this->owner->id]);
        $proposal = SalesProposal::create(['proposal_id' => 'PROP-PORTAL', 'customer_id' => $customer->id, 'issue_date' => today(), 'total_amount' => 500, 'status' => 'sent', 'organization_id' => $this->organization->id, 'workspace_id' => $this->workspace->id, 'created_by' => $this->owner->id]);
        $project = TasklyProject::create(['organization_id' => $this->organization->id, 'workspace_id' => $this->workspace->id, 'name' => 'Client Delivery', 'status' => 'active', 'created_by' => $this->owner->id]);
        $project->members()->attach($client, ['role' => 'client', 'hourly_rate' => 0]);

        $this->as($client)->get('/portal/dashboard')->assertOk()->assertInertia(fn ($page) => $page->component('Portal/Dashboard')->where('portalRole', 'client')->where('party.id', $customer->id));
        $this->as($client)->post("/portal/proposals/{$proposal->id}/decision", ['decision' => 'accepted'])->assertSessionHasNoErrors();
        $this->assertSame('accepted', $proposal->refresh()->status);
        $this->as($client)->post('/portal/project-payments', ['project_id' => $project->id, 'payment_date' => '2026-08-16', 'amount' => 100, 'payment_method' => 'online'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('taskly_project_payments', ['project_id' => $project->id, 'customer_id' => $customer->id, 'status' => 'submitted']);
        $this->as($client)->get('/accounting/customers')->assertRedirect('/portal/dashboard');
        $this->as($client)->post('/accounting/customers', ['name' => 'Unauthorized'])->assertForbidden();

        $vendor = User::factory()->create(['role' => 'vendor']);
        $vendorRole = Role::where('name', 'vendor')->whereNull('organization_id')->firstOrFail();
        $this->organization->members()->attach($vendor, ['role' => 'vendor']);
        $this->workspace->members()->attach($vendor, ['role_id' => $vendorRole->id]);
        $party = AccountVendor::create(['organization_id' => $this->organization->id, 'workspace_id' => $this->workspace->id, 'user_id' => $vendor->id, 'name' => 'Portal Vendor', 'email' => $vendor->email, 'balance' => 75, 'is_active' => true, 'created_by' => $this->owner->id]);
        $this->as($vendor)->get('/portal/dashboard')->assertOk()->assertInertia(fn ($page) => $page->component('Portal/Dashboard')->where('portalRole', 'vendor')->where('party.id', $party->id));
    }

    public function test_hrm_lifecycle_salary_leave_and_policy_surfaces(): void
    {
        $employee = HrEmployee::create(['organization_id' => $this->organization->id, 'workspace_id' => $this->workspace->id, 'user_id' => $this->owner->id, 'employee_number' => 'HL-100', 'name' => 'Lifecycle Employee', 'joined_at' => '2026-01-01', 'basic_salary' => 1000, 'status' => 'active']);
        $this->request()->post('/hrm/lifecycle/award/types', ['name' => 'Employee of the Month'])->assertSessionHasNoErrors();
        $type = DB::table('hr_event_types')->where('kind', 'award')->first();
        $this->request()->post('/hrm/lifecycle/award', ['employee_id' => $employee->id, 'event_type_id' => $type->id, 'title' => 'August Award', 'event_date' => '2026-08-16'])->assertSessionHasNoErrors();
        $event = DB::table('hr_employee_events')->where('kind', 'award')->first();
        $this->request()->post("/hrm/lifecycle/events/{$event->id}/review", ['status' => 'completed', 'response' => 'Presented'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('hr_employee_events', ['id' => $event->id, 'status' => 'completed', 'response' => 'Presented']);
        $this->request()->put("/hrm/employees/{$employee->id}/salary", ['basic_salary' => 2500])->assertSessionHasNoErrors();
        $this->assertSame('2500.00', $employee->refresh()->basic_salary);
        $this->request()->post('/hrm/announcements', ['title' => 'Town Hall', 'content' => 'Quarterly meeting', 'status' => 'published'])->assertSessionHasNoErrors();
        $this->request()->post('/hrm/policies', ['title' => 'Security Policy', 'version' => '2.0', 'content' => 'Use MFA', 'requires_acknowledgement' => true, 'status' => 'active'])->assertSessionHasNoErrors();
        $policy = DB::table('hr_policies')->first();
        $this->request()->post("/hrm/policies/{$policy->id}/acknowledge")->assertSessionHasNoErrors();
        $this->assertDatabaseHas('hr_policy_acknowledgements', ['policy_id' => $policy->id, 'employee_id' => $employee->id]);
        $this->request()->get('/hrm/leave-balances')->assertOk();
        $this->request()->get('/hrm/communications')->assertOk();
    }

    public function test_taskly_payments_reports_setup_and_cms_editors(): void
    {
        $customer = AccountCustomer::create(['organization_id' => $this->organization->id, 'workspace_id' => $this->workspace->id, 'name' => 'Taskly Client', 'is_active' => true, 'created_by' => $this->owner->id]);
        $project = TasklyProject::create(['organization_id' => $this->organization->id, 'workspace_id' => $this->workspace->id, 'name' => 'Parity Project', 'budget' => 1000, 'status' => 'active', 'created_by' => $this->owner->id]);
        $this->request()->post('/taskly/project-payments', ['project_id' => $project->id, 'customer_id' => $customer->id, 'payment_date' => '2026-08-16', 'amount' => 300, 'payment_method' => 'bank'])->assertSessionHasNoErrors();
        $payment = DB::table('taskly_project_payments')->first();
        $this->request()->post("/taskly/project-payments/{$payment->id}/review", ['status' => 'paid'])->assertSessionHasNoErrors();
        $this->request()->post('/taskly/setup/project-statuses', ['name' => 'On Hold', 'color' => '#f59e0b', 'is_closed' => false, 'position' => 2])->assertSessionHasNoErrors();
        $this->request()->post('/taskly/setup/stage-templates', ['name' => 'Quality Review', 'is_complete' => false, 'position' => 3])->assertSessionHasNoErrors();
        $this->request()->get('/taskly/reports')->assertOk()->assertInertia(fn ($page) => $page->component('Taskly/Reports'));

        $this->request()->post('/landing/sites', ['name' => 'Main Site', 'slug' => 'main-site', 'title' => 'Main Site', 'locale' => 'en', 'seo_keywords' => []])->assertSessionHasNoErrors();
        $site = DB::table('landing_sites')->first();
        $this->request()->post("/landing/sites/{$site->id}/sections", ['type' => 'hero', 'heading' => 'Welcome', 'position' => 0, 'is_visible' => true])->assertSessionHasNoErrors();
        $this->request()->post("/landing/sites/{$site->id}/pages", ['slug' => 'about', 'title' => 'About', 'content' => 'About us', 'is_published' => true, 'position' => 0])->assertSessionHasNoErrors();
        $this->request()->post('/landing/marketplace', ['name' => 'CRM Add-on', 'url' => 'https://example.test/crm', 'position' => 0, 'is_visible' => true])->assertSessionHasNoErrors();
        $this->request()->post('/landing/subscribers', ['name' => 'Reader', 'email' => 'reader@example.test'])->assertSessionHasNoErrors();
        $this->request()->get('/landing')->assertOk()->assertInertia(fn ($page) => $page->component('Landing/Manage')->has('marketplaceItems', 1)->has('subscribers', 1));
    }

    private function request(): self
    {
        return $this->as($this->owner);
    }

    private function as(User $user): self
    {
        return $this->actingAs($user)->withSession(['active_organization_id' => $this->organization->id, 'active_workspace_id' => $this->workspace->id, 'active_workspace_title' => $this->workspace->name]);
    }
}

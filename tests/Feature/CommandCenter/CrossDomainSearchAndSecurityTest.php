<?php

namespace Tests\Feature\CommandCenter;

use App\Domain\CommandCenter\Search\BusinessSearchService;
use App\Models\CrmLead;
use App\Models\CrmPipeline;
use App\Models\CrmStage;
use App\Models\HrEmployee;
use App\Models\Organization;
use App\Models\Plan;
use App\Models\SalesInvoice;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CrossDomainSearchAndSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_cross_domain_search_rbac_and_tenant_isolation(): void
    {
        $this->seed();

        // Workspace A
        $userA = User::factory()->create(['role' => 'super_admin']);
        $planA = Plan::create(['name' => 'Plan A', 'modules' => ['account', 'crm', 'hrm'], 'status' => true, 'created_by' => $userA->id]);
        $orgA = Organization::factory()->create(['owner_id' => $userA->id, 'plan_id' => $planA->id]);
        $wsA = Workspace::factory()->create(['organization_id' => $orgA->id, 'created_by' => $userA->id]);

        $pipeA = CrmPipeline::create(['organization_id' => $orgA->id, 'workspace_id' => $wsA->id, 'name' => 'Pipe A', 'is_default' => true]);
        $stageA = CrmStage::create(['pipeline_id' => $pipeA->id, 'name' => 'Stage A', 'position' => 0]);

        // Workspace B
        $userB = User::factory()->create(['role' => 'super_admin']);
        $planB = Plan::create(['name' => 'Plan B', 'modules' => ['account', 'crm', 'hrm'], 'status' => true, 'created_by' => $userB->id]);
        $orgB = Organization::factory()->create(['owner_id' => $userB->id, 'plan_id' => $planB->id]);
        $wsB = Workspace::factory()->create(['organization_id' => $orgB->id, 'created_by' => $userB->id]);

        $pipeB = CrmPipeline::create(['organization_id' => $orgB->id, 'workspace_id' => $wsB->id, 'name' => 'Pipe B', 'is_default' => true]);
        $stageB = CrmStage::create(['pipeline_id' => $pipeB->id, 'name' => 'Stage B', 'position' => 0]);

        // Lead in Workspace A
        CrmLead::create([
            'organization_id' => $orgA->id,
            'workspace_id' => $wsA->id,
            'pipeline_id' => $pipeA->id,
            'stage_id' => $stageA->id,
            'name' => 'Omega Technologies',
            'email' => 'contact@omega.tech',
            'company' => 'Omega Corp',
            'status' => 'open',
        ]);

        // Lead in Workspace B
        CrmLead::create([
            'organization_id' => $orgB->id,
            'workspace_id' => $wsB->id,
            'pipeline_id' => $pipeB->id,
            'stage_id' => $stageB->id,
            'name' => 'Omega Secret Defense',
            'email' => 'contact@secret.tech',
            'company' => 'Defense Corp',
            'status' => 'open',
        ]);

        $searchService = app(BusinessSearchService::class);

        // User A searching in Workspace A
        $resultsA = $searchService->search($userA, $wsA, 'Omega');
        $this->assertCount(1, $resultsA);
        $this->assertEquals('Lead: Omega Technologies', $resultsA[0]->title);

        // Security check: Employee visibility strictly gated by HR permission
        $regularUser = User::factory()->create(['role' => 'member']);
        $orgA->members()->attach($regularUser, ['role' => 'member']);
        $wsA->members()->attach($regularUser);

        HrEmployee::create([
            'organization_id' => $orgA->id,
            'workspace_id' => $wsA->id,
            'employee_number' => 'EMP-001',
            'name' => 'Omega Specialist John',
            'email' => 'john@omega.com',
            'joined_at' => now()->toDateString(),
            'is_active' => true,
        ]);

        // Regular user searching must NOT see employee record
        $resultsReg = $searchService->search($regularUser, $wsA, 'Omega');
        $typesReg = array_map(fn ($r) => $r->type, $resultsReg);
        $this->assertNotContains('employee', $typesReg);

        // Super Admin searching CAN see employee record
        $resultsAdmin = $searchService->search($userA, $wsA, 'Omega');
        $typesAdmin = array_map(fn ($r) => $r->type, $resultsAdmin);
        $this->assertContains('employee', $typesAdmin);
    }
}

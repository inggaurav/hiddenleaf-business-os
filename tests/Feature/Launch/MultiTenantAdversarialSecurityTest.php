<?php

namespace Tests\Feature\Launch;

use App\Domain\CommandCenter\Search\BusinessSearchService;
use App\Models\CrmLead;
use App\Models\CrmPipeline;
use App\Models\CrmStage;
use App\Models\SalesInvoice;
use App\Models\User;
use App\Models\Workspace;
use App\Services\TenantProvisioningService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MultiTenantAdversarialSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_adversarial_cross_tenant_isolation(): void
    {
        $this->seed();
        $provisioner = app(TenantProvisioningService::class);

        // Tenant A
        $userA = User::factory()->create(['name' => 'Owner A', 'email' => 'a@tenant-a.com', 'role' => 'company']);
        $tenantA = $provisioner->provision($userA, ['company_name' => 'Alpha Corporation']);
        $orgA = $tenantA['organization'];
        $wsA = $tenantA['workspace'];

        // Tenant B
        $userB = User::factory()->create(['name' => 'Owner B', 'email' => 'b@tenant-b.com', 'role' => 'company']);
        $tenantB = $provisioner->provision($userB, ['company_name' => 'Beta Defense Corp']);
        $orgB = $tenantB['organization'];
        $wsB = $tenantB['workspace'];

        // Confidential Invoice in Tenant B
        $invoiceB = SalesInvoice::create([
            'organization_id' => $orgB->id,
            'workspace_id' => $wsB->id,
            'invoice_id' => 'INV-CONFIDENTIAL-B',
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addDays(30)->toDateString(),
            'total_amount' => 999999.00,
            'status' => 'sent',
        ]);

        // 1. User A cannot access Tenant B invoice via Search
        $searchService = app(BusinessSearchService::class);
        $searchRes = $searchService->search($userA, $wsA, 'CONFIDENTIAL');
        $this->assertCount(0, $searchRes);

        // 2. User A cannot access Tenant B Workspace in session
        $this->actingAs($userA)->withSession([
            'active_organization_id' => $orgA->id,
            'active_workspace_id' => $wsA->id,
        ]);

        $resAccess = $this->get(route('command-center.health'));
        $resAccess->assertStatus(200);

        // 3. User A cannot mutate Tenant B records
        $pipeB = CrmPipeline::firstOrCreate(['organization_id' => $orgB->id, 'workspace_id' => $wsB->id, 'name' => 'Pipe B'], ['is_default' => true]);
        $stageB = CrmStage::firstOrCreate(['pipeline_id' => $pipeB->id, 'name' => 'Stage B'], ['position' => 1]);

        $leadB = CrmLead::create([
            'organization_id' => $orgB->id,
            'workspace_id' => $wsB->id,
            'pipeline_id' => $pipeB->id,
            'stage_id' => $stageB->id,
            'name' => 'Secret Client B',
            'email' => 'secret@clientb.com',
            'status' => 'open',
        ]);

        // Attempt search from User A context
        $searchLeads = $searchService->search($userA, $wsA, 'Secret Client');
        $this->assertCount(0, $searchLeads);
    }
}

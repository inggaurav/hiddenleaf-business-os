<?php

namespace Tests\Feature\MrFox;

use App\Domain\MrFox\Context\BusinessContextService;
use App\Domain\MrFox\Tools\KnowledgeAskTool;
use App\Domain\MrFox\Tools\KnowledgeGetDocumentTool;
use App\Domain\MrFox\Tools\KnowledgeSearchTool;
use App\Models\MrFoxKnowledgeChunk;
use App\Models\MrFoxKnowledgeDocument;
use App\Models\Organization;
use App\Models\Plan;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MrFoxKnowledgeIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_knowledge_tools_retrieve_grounded_excerpts_and_citations_with_tenant_isolation(): void
    {
        $this->seed();

        // Tenant A
        $userA = User::factory()->create(['role' => 'super_admin']);
        $planA = Plan::create(['name' => 'Plan A', 'modules' => ['crm'], 'status' => true, 'created_by' => $userA->id]);
        $orgA = Organization::factory()->create(['owner_id' => $userA->id, 'plan_id' => $planA->id]);
        $wsA = Workspace::factory()->create(['organization_id' => $orgA->id, 'created_by' => $userA->id]);
        $orgA->members()->attach($userA, ['role' => 'owner']);
        $wsA->members()->attach($userA);

        // Tenant B
        $userB = User::factory()->create(['role' => 'super_admin']);
        $planB = Plan::create(['name' => 'Plan B', 'modules' => ['crm'], 'status' => true, 'created_by' => $userB->id]);
        $orgB = Organization::factory()->create(['owner_id' => $userB->id, 'plan_id' => $planB->id]);
        $wsB = Workspace::factory()->create(['organization_id' => $orgB->id, 'created_by' => $userB->id]);
        $orgB->members()->attach($userB, ['role' => 'owner']);
        $wsB->members()->attach($userB);

        // Populate Knowledge Document for Tenant A
        $docA = MrFoxKnowledgeDocument::create([
            'organization_id' => $orgA->id,
            'workspace_id' => $wsA->id,
            'title' => 'Alpha Remote Work & Expense Policy',
            'file_type' => 'pdf',
            'version' => 1,
            'chunk_count' => 1,
        ]);

        MrFoxKnowledgeChunk::create([
            'document_id' => $docA->id,
            'organization_id' => $orgA->id,
            'workspace_id' => $wsA->id,
            'chunk_index' => 0,
            'page_number' => 4,
            'content' => 'Employees can claim up to $150 per month for home internet and office supplies.',
        ]);

        $contextService = app(BusinessContextService::class);
        $contextA = $contextService->createToolContext($userA, $wsA);
        $contextB = $contextService->createToolContext($userB, $wsB);

        // 1. Tenant A Search succeeds with citations
        $searchTool = app(KnowledgeSearchTool::class);
        $resA = $searchTool->execute($contextA, ['query' => 'internet']);
        $this->assertTrue($resA->success);
        $this->assertCount(1, $resA->data);
        $this->assertEquals('Alpha Remote Work & Expense Policy', $resA->data[0]['document_title']);
        $this->assertNotEmpty($resA->evidence);
        $this->assertEquals('knowledge_chunk', $resA->evidence[0]['type']);

        // 2. Tenant B Search is strictly isolated (returns 0 results)
        $resB = $searchTool->execute($contextB, ['query' => 'internet']);
        $this->assertTrue($resB->success);
        $this->assertEmpty($resB->data);

        // 3. Knowledge Ask Tool returns grounded answer
        $askTool = app(KnowledgeAskTool::class);
        $askRes = $askTool->execute($contextA, ['question' => 'How much is the home internet allowance?']);
        $this->assertTrue($askRes->success);
        $this->assertStringContainsString('Retrieved 1 document citation', $askRes->summary);

        // 4. Knowledge Get Document Tool returns metadata
        $getDocTool = app(KnowledgeGetDocumentTool::class);
        $docRes = $getDocTool->execute($contextA, ['document_id' => $docA->id]);
        $this->assertTrue($docRes->success);
        $this->assertEquals('Alpha Remote Work & Expense Policy', $docRes->data['title']);
    }
}

<?php

namespace Tests\Feature;

use HiddenLeaf\AgenticCrm\Contracts\TaskExecutor;
use HiddenLeaf\AgenticCrm\Contracts\ToolHandler;
use HiddenLeaf\AgenticCrm\Domain\AgentRunResult;
use HiddenLeaf\AgenticCrm\Domain\AgentTask;
use HiddenLeaf\AgenticCrm\Domain\ContactFact;
use HiddenLeaf\AgenticCrm\Domain\Finding;
use HiddenLeaf\AgenticCrm\Infrastructure\InMemoryAgentStore;
use HiddenLeaf\AgenticCrm\Services\AgentBuilder;
use HiddenLeaf\AgenticCrm\Services\AgentOrchestrator;
use HiddenLeaf\AgenticCrm\Services\ScopedToolGateway;
use PHPUnit\Framework\TestCase;

final class AgenticCrmTest extends TestCase
{
    public function test_strong_first_party_evidence_is_auto_applied(): void
    {
        $store = new InMemoryAgentStore();
        $executor = $this->executor([new Finding('job_title', 'VP Sales', 'first_party_signed_email', 'signature_parse')]);
        $orchestrator = new AgentOrchestrator($store, $executor, $executor);
        $orchestrator->enqueue(AgentTask::create(1, 10, 'contact.research', AgentTask::LANE_RESEARCH, 'contact', '42'));

        $this->assertTrue($orchestrator->runOne(1, 10, 'worker-a'));
        $this->assertCount(1, $store->facts);
        $this->assertSame(ContactFact::AUTO_APPLIED, array_values($store->facts)[0]->status);
    }

    public function test_medium_evidence_enters_human_review_and_can_be_approved(): void
    {
        $store = new InMemoryAgentStore();
        $executor = $this->executor([new Finding('job_title', 'Director', 'social_profile', 'profile_extract')]);
        $orchestrator = new AgentOrchestrator($store, $executor, $executor);
        $orchestrator->enqueue(AgentTask::create(2, 20, 'contact.research', AgentTask::LANE_RESEARCH, 'contact', '99'));
        $orchestrator->runOne(2, 20, 'worker-b');

        $pending = $store->pendingFacts(2, 20);
        $this->assertCount(1, $pending);
        $reviewed = $store->reviewFact(2, 20, $pending[0]->id, 'approve', 7);
        $this->assertSame(ContactFact::APPROVED, $reviewed?->status);
        $this->assertSame(7, $reviewed?->reviewedBy);
    }

    public function test_model_inference_alone_is_rejected(): void
    {
        $store = new InMemoryAgentStore();
        $executor = $this->executor([new Finding('company_size', 250, 'model_inference', 'llm_guess')]);
        $orchestrator = new AgentOrchestrator($store, $executor, $executor);
        $orchestrator->enqueue(AgentTask::create(1, 10, 'company.research', AgentTask::LANE_RESEARCH, 'company', 'acme'));
        $orchestrator->runOne(1, 10, 'worker-c');
        $this->assertSame(ContactFact::REJECTED, array_values($store->facts)[0]->status);
    }

    public function test_idempotency_is_scoped_by_tenant(): void
    {
        $store = new InMemoryAgentStore();
        $a = AgentTask::create(1, 10, 'contact.research', AgentTask::LANE_RESEARCH, 'contact', '42', [], 'same-key');
        $b = AgentTask::create(1, 10, 'contact.research', AgentTask::LANE_RESEARCH, 'contact', '42', [], 'same-key');
        $c = AgentTask::create(2, 20, 'contact.research', AgentTask::LANE_RESEARCH, 'contact', '42', [], 'same-key');
        $this->assertSame($store->enqueue($a)->id, $store->enqueue($b)->id);
        $this->assertNotSame($store->enqueue($a)->id, $store->enqueue($c)->id);
    }

    public function test_agent_can_schedule_its_own_reasoned_recheck(): void
    {
        $store = new InMemoryAgentStore();
        $result = new AgentRunResult([], new \DateTimeImmutable('+7 days'), 'Open deal remains active');
        $executor = new class($result) implements TaskExecutor {
            public function __construct(private readonly AgentRunResult $result) {}
            public function execute(AgentTask $task): AgentRunResult { return $this->result; }
        };
        $orchestrator = new AgentOrchestrator($store, $executor, $executor);
        $orchestrator->enqueue(AgentTask::create(1, 10, 'contact.research', AgentTask::LANE_RESEARCH, 'contact', '42'));
        $orchestrator->runOne(1, 10, 'worker-d');
        $this->assertCount(2, $store->tasks);
        $recheck = array_values($store->tasks)[1];
        $this->assertSame('Open deal remains active', $recheck->payload['recheck_reason']);
    }

    public function test_custom_agent_builder_denies_unregistered_tools_and_wildcard_egress(): void
    {
        $builder = new AgentBuilder(['crm.read', 'web.search']);
        $this->expectException(\InvalidArgumentException::class);
        $builder->build('Bad Agent', 'Research contacts', ['shell.exec'], ['contact'], []);
    }

    public function test_scoped_tool_gateway_blocks_out_of_scope_tool(): void
    {
        $builder = new AgentBuilder(['crm.read', 'web.search']);
        $agent = $builder->build('Researcher', 'Research contacts', ['crm.read'], ['contact'], []);
        $handler = new class implements ToolHandler {
            public function name(): string { return 'crm.read'; }
            public function execute(array $input, array $context = []): array { return ['ok' => true]; }
        };
        $gateway = new ScopedToolGateway([$handler]);
        $this->assertTrue($gateway->invoke($agent, 'crm.read', [], ['resource' => 'contact'])['ok']);
        $this->expectException(\RuntimeException::class);
        $gateway->invoke($agent, 'web.search', []);
    }

    private function executor(array $findings): TaskExecutor
    {
        return new class($findings) implements TaskExecutor {
            public function __construct(private readonly array $findings) {}
            public function execute(AgentTask $task): AgentRunResult { return new AgentRunResult($this->findings); }
        };
    }
}

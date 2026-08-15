<?php

namespace App\Domain\MrFox\Agent;

use App\Domain\MrFox\Approvals\ActionApprovalService;
use App\Domain\MrFox\Context\BusinessContextService;
use App\Domain\MrFox\DTO\AiRequest;
use App\Domain\MrFox\DTO\ToolContext;
use App\Domain\MrFox\DTO\ToolResult;
use App\Domain\MrFox\Observability\MrFoxAuditService;
use App\Domain\MrFox\Observability\MrFoxUsageService;
use App\Domain\MrFox\Providers\ProviderRouter;
use App\Domain\MrFox\Tools\MrFoxToolRegistry;
use App\Models\User;
use App\Models\Workspace;

class MrFoxAgent
{
    public function __construct(
        private ProviderRouter $providerRouter,
        private MrFoxToolRegistry $toolRegistry,
        private BusinessContextService $contextService,
        private ActionApprovalService $approvalService,
        private MrFoxAuditService $auditService,
        private MrFoxUsageService $usageService
    ) {}

    public function handle(
        User $user,
        ?Workspace $workspace,
        array $messages,
        string $conversationId = '',
        string $activePage = 'Dashboard'
    ): array {
        $toolContext = $this->contextService->createToolContext($user, $workspace, $conversationId);
        $businessContext = $this->contextService->build($user, $workspace, $activePage);
        $provider = $this->providerRouter->resolve($workspace);

        $systemPrompt = $this->buildSystemPrompt($businessContext);
        $toolsDefinition = $this->toolRegistry->toOpenAiTools($toolContext);

        $aiRequest = new AiRequest(
            messages: $messages,
            tools: $toolsDefinition,
            systemPrompt: $systemPrompt,
            context: $businessContext
        );

        $startTime = microtime(true);
        $aiResponse = $provider->chat($aiRequest);
        $durationMs = (int) round((microtime(true) - $startTime) * 1000);

        // Record token consumption
        if ($aiResponse->inputTokens > 0 || $aiResponse->outputTokens > 0) {
            $this->usageService->recordUsage(
                $toolContext,
                $aiResponse->provider,
                $aiResponse->model ?? 'default',
                $aiResponse->inputTokens,
                $aiResponse->outputTokens
            );
        }

        $toolResults = [];
        $evidenceCollected = [];
        $actionProposals = [];

        if ($aiResponse->hasToolCalls()) {
            foreach ($aiResponse->toolCalls as $call) {
                $toolName = $call['name'] ?? '';
                $toolInput = $call['arguments'] ?? [];
                $tool = $this->toolRegistry->get($toolName);

                if (! $tool) {
                    $toolResults[] = [
                        'tool' => $toolName,
                        'success' => false,
                        'summary' => "Tool '{$toolName}' is not recognized.",
                    ];
                    continue;
                }

                $risk = $tool->riskLevel();
                $toolStartTime = microtime(true);

                // High / Critical risk requires approval proposal
                if ($risk->requiresApproval()) {
                    $humanSummary = "Proposed execution of {$toolName} with input: " . json_encode($toolInput);
                    $proposal = $this->approvalService->createProposal(
                        $toolContext,
                        $toolName,
                        $toolInput,
                        $humanSummary,
                        $risk
                    );

                    $actionProposals[] = [
                        'proposal_id' => $proposal->id,
                        'tool_name' => $toolName,
                        'human_summary' => $humanSummary,
                        'risk_level' => $risk->value,
                        'status' => 'pending',
                    ];

                    $result = ToolResult::proposed($proposal->id, $humanSummary);
                } else {
                    $result = $tool->execute($toolContext, $toolInput);
                }

                $toolDuration = (int) round((microtime(true) - $toolStartTime) * 1000);

                // Audit logging
                $this->auditService->logToolExecution(
                    $toolContext,
                    $toolName,
                    $toolInput,
                    $result,
                    $risk,
                    $toolDuration,
                    $aiResponse->provider,
                    $aiResponse->model
                );

                $toolResults[] = [
                    'tool' => $toolName,
                    'success' => $result->success,
                    'summary' => $result->summary,
                    'requires_approval' => $result->requiresApproval,
                    'proposal_id' => $result->proposalId,
                ];

                foreach ($result->evidence as $ev) {
                    $evidenceCollected[] = $ev;
                }
            }
        }

        return [
            'reply' => $aiResponse->content,
            'provider' => $aiResponse->provider,
            'model' => $aiResponse->model,
            'tools_executed' => $toolResults,
            'action_proposals' => $actionProposals,
            'evidence' => array_values(array_unique($evidenceCollected, SORT_REGULAR)),
            'duration_ms' => $durationMs,
        ];
    }

    private function buildSystemPrompt(array $ctx): string
    {
        $currencyCode = $ctx['currency']['code'] ?? 'USD';
        $currencySymbol = $ctx['currency']['symbol'] ?? '$';
        $modules = implode(', ', $ctx['enabled_modules'] ?? []);

        return <<<EOT
You are Mr. Fox, the executive intelligence layer for HiddenLeaf BusinessOS.
You assist organizational leaders and teams by analyzing business performance, retrieving real-time ERP data, surfacing operational risks, and safely executing authorized actions.

OPERATIONAL BOUNDARIES:
1. TENANT ISOLATION: You are strictly scoped to Organization '{$ctx['organization']['name']}' (ID: {$ctx['organization']['id']}) and Workspace '{$ctx['workspace']['name']}' (ID: {$ctx['workspace']['id']}).
2. CURRENT USER: {$ctx['user']['name']} ({$ctx['user']['email']}).
3. ACTIVE PAGE CONTEXT: The user is currently on the '{$ctx['active_page']}' screen.
4. ENABLED MODULES: {$modules}.
5. CURRENCY: {$currencyCode} ({$currencySymbol}).
6. SAFETY & RISK: You interact with the ERP strictly through typed tools. High-risk operations (such as financial postings, money transfers, or destructive deletions) will be queued as action proposals for explicit user approval.
7. GROUNDING: Ground all quantitative insights in verified ERP data returned by tools.
EOT;
    }
}

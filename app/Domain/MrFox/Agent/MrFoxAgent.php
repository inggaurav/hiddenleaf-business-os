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
use App\Domain\MrFox\Validation\ToolInputValidator;
use App\Models\User;
use App\Models\Workspace;
use App\Services\PermissionService;
use Illuminate\Support\Facades\Log;

class MrFoxAgent
{
    /** Maximum number of tool calls permitted in a single agent turn */
    public const MAX_TOOL_CALLS = 5;

    public function __construct(
        private ProviderRouter $providerRouter,
        private MrFoxToolRegistry $toolRegistry,
        private BusinessContextService $contextService,
        private ActionApprovalService $approvalService,
        private MrFoxAuditService $auditService,
        private MrFoxUsageService $usageService,
        private ToolInputValidator $validator,
        private PermissionService $permissionService
    ) {}

    public function handle(
        User $user,
        ?Workspace $workspace,
        array $messages,
        string $conversationId = '',
        string $activePage = 'Dashboard'
    ): array {
        // Enforce server-side context authority
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
            $callCount = 0;

            foreach ($aiResponse->toolCalls as $call) {
                if (++$callCount > self::MAX_TOOL_CALLS) {
                    $toolResults[] = [
                        'tool' => $call['name'] ?? 'unknown',
                        'success' => false,
                        'summary' => 'Tool execution limit reached for this interaction turn.',
                    ];
                    break;
                }

                $toolName = $call['name'] ?? '';
                $rawInput = $call['arguments'] ?? [];
                $tool = $this->toolRegistry->get($toolName);

                if (! $tool) {
                    $toolResults[] = [
                        'tool' => $toolName,
                        'success' => false,
                        'summary' => "Tool '{$toolName}' is not recognized in registry.",
                    ];
                    continue;
                }

                // 1. Server-side RBAC Permission Verification
                $reqPermission = $tool->requiredPermission();
                $isSuperAdmin = method_exists($user, 'isSuperAdmin') ? $user->isSuperAdmin() : ($user->role === 'super_admin');

                if (! $isSuperAdmin && $reqPermission !== null && $workspace !== null) {
                    if (! $this->permissionService->allows($user, $workspace, $reqPermission)) {
                        $toolResults[] = [
                            'tool' => $toolName,
                            'success' => false,
                            'summary' => "Access denied: Missing required permission '{$reqPermission}'.",
                        ];
                        continue;
                    }
                }

                // 2. Server-side Schema & Security Validation
                $validation = $this->validator->validate($tool, $rawInput);
                if (! $validation['valid']) {
                    $toolResults[] = [
                        'tool' => $toolName,
                        'success' => false,
                        'summary' => 'Invalid parameters: ' . json_encode($validation['errors']),
                    ];
                    continue;
                }
                $toolInput = $validation['sanitized'];

                $risk = $tool->riskLevel();
                $toolStartTime = microtime(true);

                try {
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
                } catch (\Throwable $e) {
                    Log::error("MrFox tool execution failed: {$toolName}", ['error' => $e->getMessage()]);
                    $result = ToolResult::error("Execution error encountered while running {$toolName}.");
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

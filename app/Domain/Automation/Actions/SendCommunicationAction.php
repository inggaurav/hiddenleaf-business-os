<?php

namespace App\Domain\Automation\Actions;

use App\Domain\Automation\Contracts\AutomationActionContract;
use App\Domain\Communications\Actions\CommunicationSendService;
use App\Domain\MrFox\Approvals\ActionApprovalService;
use App\Domain\MrFox\Context\BusinessContextService;
use App\Domain\MrFox\RiskLevel;
use App\Models\AutomationRun;
use App\Models\CommunicationConversation;

class SendCommunicationAction implements AutomationActionContract
{
    public function __construct(
        private ?CommunicationSendService $sendService = null,
        private ?ActionApprovalService $approvalService = null,
        private ?BusinessContextService $contextService = null
    ) {
        $this->sendService = $sendService ?? app(CommunicationSendService::class);
        $this->approvalService = $approvalService ?? app(ActionApprovalService::class);
        $this->contextService = $contextService ?? app(BusinessContextService::class);
    }

    public function name(): string
    {
        return 'communications.send_message';
    }

    public function description(): string
    {
        return 'Deliver an external reply via Gmail, WhatsApp, or Slack (High Risk - Approval Governed).';
    }

    public function requiredPermission(): ?string
    {
        return null;
    }

    public function riskLevel(): RiskLevel
    {
        return RiskLevel::HIGH;
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['conversation_id', 'message_body'],
            'properties' => [
                'conversation_id' => ['type' => 'integer'],
                'message_body' => ['type' => 'string'],
            ],
        ];
    }

    public function execute(AutomationRun $run, array $input): array
    {
        $wsId = $run->workspace_id;
        $convId = (int) ($input['conversation_id'] ?? data_get($run->trigger_payload, 'conversation_id') ?? 0);
        $body = (string) ($input['message_body'] ?? '');

        $conv = CommunicationConversation::where('workspace_id', $wsId)->where('id', $convId)->first();
        if (! $conv) {
            return ['success' => false, 'data' => [], 'error' => "Conversation #{$convId} not found."];
        }

        $context = $this->contextService->createToolContext($run->rule?->creator, $run->workspace);

        $proposal = $this->approvalService->createProposal(
            $context,
            'communications.send.reply',
            ['conversation_id' => $conv->id, 'message_body' => $body],
            "Automation: Deliver outbound message to {$conv->participant_name}",
            RiskLevel::HIGH
        );

        return [
            'success' => true,
            'requires_approval' => true,
            'proposal_id' => $proposal->id,
            'data' => [
                'proposal_id' => $proposal->id,
                'status' => 'waiting_for_approval',
                'recipient' => $conv->participant_name,
            ],
            'error' => null,
        ];
    }
}

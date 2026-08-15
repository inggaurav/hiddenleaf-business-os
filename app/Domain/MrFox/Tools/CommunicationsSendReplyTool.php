<?php

namespace App\Domain\MrFox\Tools;

use App\Domain\Communications\Actions\CommunicationSendService;
use App\Domain\MrFox\Contracts\MrFoxToolContract;
use App\Domain\MrFox\DTO\ToolContext;
use App\Domain\MrFox\DTO\ToolResult;
use App\Domain\MrFox\RiskLevel;
use App\Models\CommunicationConversation;

class CommunicationsSendReplyTool implements MrFoxToolContract
{
    public function __construct(private ?CommunicationSendService $sendService = null)
    {
        $this->sendService = $sendService ?? app(CommunicationSendService::class);
    }

    public function name(): string
    {
        return 'communications.send.reply';
    }

    public function description(): string
    {
        return 'Send an approved message/reply to a customer or lead across Gmail, WhatsApp, Slack, or Social channels.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['conversation_id', 'message_body'],
            'properties' => [
                'conversation_id' => ['type' => 'integer', 'description' => 'Target conversation ID'],
                'message_body' => ['type' => 'string', 'description' => 'The message text to deliver'],
                'template_name' => ['type' => 'string', 'description' => 'Optional WhatsApp template name'],
                'template_parameters' => ['type' => 'array', 'description' => 'Optional template parameter values'],
            ],
        ];
    }

    public function requiredPermission(): ?string
    {
        return null;
    }

    public function requiredModule(): ?string
    {
        return null;
    }

    public function riskLevel(): RiskLevel
    {
        return RiskLevel::HIGH;
    }

    public function execute(ToolContext $context, array $input): ToolResult
    {
        $wsId = $context->getWorkspaceId();
        $convId = (int) ($input['conversation_id'] ?? 0);
        $messageBody = (string) ($input['message_body'] ?? '');
        $templateName = $input['template_name'] ?? null;
        $templateParams = is_array($input['template_parameters'] ?? null) ? $input['template_parameters'] : [];

        $conv = CommunicationConversation::where('workspace_id', $wsId)->where('id', $convId)->first();
        if (! $conv) {
            return ToolResult::error("Conversation #{$convId} not found in this workspace.");
        }

        $result = $this->sendService->sendReply($conv, $messageBody, null, $templateName, $templateParams);

        if (! $result->success) {
            return ToolResult::error("Failed to deliver message: {$result->errorMessage}");
        }

        $data = [
            'conversation_id' => $conv->id,
            'recipient' => $conv->participant_name,
            'provider_message_id' => $result->providerMessageId,
            'status' => $result->deliveryStatus,
        ];

        return ToolResult::success($data, "Successfully delivered reply to {$conv->participant_name} via {$conv->provider}.", [
            ['type' => 'communication_message', 'id' => $conv->id, 'label' => "Sent: {$conv->participant_name}", 'route' => '/inbox'],
        ]);
    }
}

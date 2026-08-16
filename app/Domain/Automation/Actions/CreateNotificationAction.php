<?php

namespace App\Domain\Automation\Actions;

use App\Domain\Automation\Contracts\AutomationActionContract;
use App\Domain\MrFox\RiskLevel;
use App\Models\AutomationRun;
use App\Models\User;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Str;

class CreateNotificationAction implements AutomationActionContract
{
    public function name(): string
    {
        return 'notifications.create';
    }

    public function description(): string
    {
        return 'Send an in-app system notification to workspace users or assignees.';
    }

    public function requiredPermission(): ?string
    {
        return null;
    }

    public function riskLevel(): RiskLevel
    {
        return RiskLevel::SAFE;
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['message'],
            'properties' => [
                'user_id' => ['type' => 'integer'],
                'title' => ['type' => 'string'],
                'message' => ['type' => 'string'],
            ],
        ];
    }

    public function execute(AutomationRun $run, array $input): array
    {
        $wsId = $run->workspace_id;
        $orgId = $run->organization_id;
        $title = (string) ($input['title'] ?? 'Automation Alert');
        $message = (string) ($input['message'] ?? 'Action triggered by automation.');
        $userId = ! empty($input['user_id']) ? (int) $input['user_id'] : User::where('current_workspace_id', $wsId)->value('id');

        $notif = DatabaseNotification::create([
            'id' => (string) Str::uuid(),
            'type' => 'App\Notifications\AutomationTriggeredNotification',
            'notifiable_type' => User::class,
            'notifiable_id' => $userId ?: 1,
            'organization_id' => $orgId,
            'workspace_id' => $wsId,
            'data' => [
                'title' => $title,
                'message' => $message,
                'run_id' => $run->id,
                'workspace_id' => $wsId,
            ],
        ]);

        return [
            'success' => true,
            'data' => ['notification_id' => $notif->id, 'user_id' => $userId],
            'error' => null,
        ];
    }
}

<?php

namespace App\Domain\MrFox\Observability;

use App\Domain\MrFox\DTO\ToolContext;
use App\Models\MrFoxUsageRecord;
use Illuminate\Support\Facades\Log;

class MrFoxUsageService
{
    public function recordUsage(
        ToolContext $context,
        string $provider,
        string $model,
        int $inputTokens,
        int $outputTokens,
        string $capability = 'chat',
        ?float $cost = null
    ): ?MrFoxUsageRecord {
        try {
            return MrFoxUsageRecord::create([
                'organization_id' => $context->getOrganizationId(),
                'workspace_id' => $context->getWorkspaceId(),
                'user_id' => $context->user->id,
                'provider' => $provider,
                'model' => $model,
                'capability' => $capability,
                'input_tokens' => $inputTokens,
                'output_tokens' => $outputTokens,
                'cost' => $cost,
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('Failed to record MrFox usage record', ['error' => $e->getMessage()]);

            return null;
        }
    }
}

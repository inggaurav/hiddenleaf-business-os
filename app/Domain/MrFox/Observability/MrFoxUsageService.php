<?php

namespace App\Domain\MrFox\Observability;

use App\Domain\MrFox\DTO\ToolContext;
use App\Models\MrFoxUsageRecord;
use App\Models\Workspace;
use Illuminate\Support\Facades\Log;

class MrFoxUsageService
{
    /** Default monthly soft token ceiling per workspace */
    public const DEFAULT_MONTHLY_TOKEN_QUOTA = 10_000_000;

    public function recordUsage(
        ToolContext $context,
        string $provider,
        string $model,
        int $inputTokens,
        int $outputTokens,
        string $capability = 'chat',
        ?float $cost = null
    ): ?MrFoxUsageRecord {
        $safeInput = max(0, $inputTokens);
        $safeOutput = max(0, $outputTokens);

        try {
            return MrFoxUsageRecord::create([
                'organization_id' => $context->getOrganizationId(),
                'workspace_id' => $context->getWorkspaceId(),
                'user_id' => $context->user->id,
                'provider' => $provider,
                'model' => $model,
                'capability' => $capability,
                'input_tokens' => $safeInput,
                'output_tokens' => $safeOutput,
                'cost' => $cost !== null ? max(0.0, $cost) : null,
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('Failed to record MrFox usage record', ['error' => $e->getMessage()]);

            return null;
        }
    }

    public function getMonthlyUsage(int $workspaceId): array
    {
        $records = MrFoxUsageRecord::where('workspace_id', $workspaceId)
            ->where('created_at', '>=', now()->startOfMonth())
            ->get();

        $inputSum = (int) $records->sum('input_tokens');
        $outputSum = (int) $records->sum('output_tokens');

        return [
            'total_tokens' => $inputSum + $outputSum,
            'input_tokens' => $inputSum,
            'output_tokens' => $outputSum,
            'requests_count' => $records->count(),
            'monthly_quota' => self::DEFAULT_MONTHLY_TOKEN_QUOTA,
            'quota_remaining' => max(0, self::DEFAULT_MONTHLY_TOKEN_QUOTA - ($inputSum + $outputSum)),
        ];
    }

    public function hasExceededMonthlyQuota(int $workspaceId): bool
    {
        $usage = $this->getMonthlyUsage($workspaceId);

        return $usage['total_tokens'] >= $usage['monthly_quota'];
    }
}

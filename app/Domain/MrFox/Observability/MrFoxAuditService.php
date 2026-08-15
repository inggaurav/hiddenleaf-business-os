<?php

namespace App\Domain\MrFox\Observability;

use App\Domain\MrFox\DTO\ToolContext;
use App\Domain\MrFox\DTO\ToolResult;
use App\Domain\MrFox\RiskLevel;
use App\Models\MrFoxAuditLog;
use Illuminate\Support\Facades\Log;

class MrFoxAuditService
{
    public function logToolExecution(
        ToolContext $context,
        string $toolName,
        array $inputPayload,
        ToolResult $result,
        RiskLevel $riskLevel,
        int $durationMs,
        ?string $provider = null,
        ?string $model = null
    ): MrFoxAuditLog {
        // Redact sensitive keys from input payload
        $sanitizedInput = $this->sanitizePayload($inputPayload);

        try {
            return MrFoxAuditLog::create([
                'organization_id' => $context->getOrganizationId(),
                'workspace_id' => $context->getWorkspaceId(),
                'user_id' => $context->user->id,
                'conversation_id' => $context->conversationId,
                'tool_name' => $toolName,
                'input_payload' => $sanitizedInput,
                'output_summary' => substr($result->summary, 0, 1000),
                'risk_level' => $riskLevel->value,
                'execution_status' => $result->success ? 'success' : 'failed',
                'duration_ms' => $durationMs,
                'provider' => $provider,
                'model' => $model,
                'error' => $result->error,
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed to write MrFox audit log', ['error' => $e->getMessage()]);

            // Return unpersisted instance fallback
            return new MrFoxAuditLog();
        }
    }

    private function sanitizePayload(array $payload): array
    {
        $sensitiveKeys = ['password', 'secret', 'token', 'key', 'api_key', 'card', 'cvv'];
        $sanitized = [];

        foreach ($payload as $k => $v) {
            if (in_array(strtolower($k), $sensitiveKeys, true)) {
                $sanitized[$k] = '[REDACTED]';
            } elseif (is_array($v)) {
                $sanitized[$k] = $this->sanitizePayload($v);
            } else {
                $sanitized[$k] = $v;
            }
        }

        return $sanitized;
    }
}

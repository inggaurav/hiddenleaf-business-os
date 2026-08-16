<?php

namespace App\Domain\MrFox\Observability;

use App\Domain\MrFox\DTO\ToolContext;
use App\Domain\MrFox\DTO\ToolResult;
use App\Domain\MrFox\RiskLevel;
use App\Models\MrFoxAuditLog;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class MrFoxAuditService
{
    /**
     * Keys whose values must always be sanitized in audit logs.
     */
    private const SENSITIVE_KEYS = [
        'password',
        'password_confirmation',
        'secret',
        'token',
        'key',
        'api_key',
        'apikey',
        'auth_token',
        'access_token',
        'refresh_token',
        'card',
        'card_number',
        'cvv',
        'cvc',
        'stripe_secret',
        'paypal_secret',
        'twilio_token',
        'smtp_password',
        'bearer',
    ];

    public function logToolExecution(
        ToolContext $context,
        string $toolName,
        array $inputPayload,
        ToolResult $result,
        RiskLevel $riskLevel,
        int $durationMs,
        ?string $provider = null,
        ?string $model = null,
        ?string $traceId = null
    ): MrFoxAuditLog {
        $traceId = $traceId ?: (string) Str::uuid();
        $sanitizedInput = $this->sanitizePayload($inputPayload);
        $sanitizedSummary = $this->sanitizeString($result->summary);

        try {
            return MrFoxAuditLog::create([
                'organization_id' => $context->getOrganizationId(),
                'workspace_id' => $context->getWorkspaceId(),
                'user_id' => $context->user->id,
                'conversation_id' => $context->conversationId,
                'tool_name' => $toolName,
                'input_payload' => $sanitizedInput,
                'output_summary' => substr($sanitizedSummary, 0, 1000),
                'risk_level' => $riskLevel->value,
                'execution_status' => $result->success ? 'success' : 'failed',
                'duration_ms' => $durationMs,
                'provider' => $provider,
                'model' => $model,
                'error' => $result->error ? $this->sanitizeString($result->error) : null,
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed to write MrFox audit log', ['error' => $e->getMessage()]);

            return new MrFoxAuditLog;
        }
    }

    public function sanitizePayload(array $payload): array
    {
        $sanitized = [];

        foreach ($payload as $k => $v) {
            $keyLower = strtolower((string) $k);

            if (in_array($keyLower, self::SENSITIVE_KEYS, true) || Str::contains($keyLower, ['secret', 'password', 'token', 'key'])) {
                $sanitized[$k] = '[REDACTED]';
            } elseif (is_array($v)) {
                $sanitized[$k] = $this->sanitizePayload($v);
            } elseif (is_string($v)) {
                $sanitized[$k] = $this->sanitizeString($v);
            } else {
                $sanitized[$k] = $v;
            }
        }

        return $sanitized;
    }

    public function sanitizeString(string $text): string
    {
        // Redact Bearer tokens
        $text = preg_replace('/Bearer\s+[A-Za-z0-9\-\._~\+\/]+=*/i', 'Bearer [REDACTED]', $text);

        // Redact OpenAI / Stripe / standard secret key patterns
        $text = preg_replace('/sk-[A-Za-z0-9_\-]{20,}/', 'sk-[REDACTED]', $text);
        $text = preg_replace('/(AIza[0-9A-Za-z-_]{35})/', 'AIza[REDACTED]', $text);

        return $text;
    }
}

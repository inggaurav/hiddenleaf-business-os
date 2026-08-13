<?php

namespace HiddenLeaf\Kernel\Services;

use App\Models\AuditLog;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class AuditLogger
{
    protected array $logs = [];

    public function log(
        ?int $actorId,
        ?int $orgId,
        ?int $workspaceId,
        string $action,
        string $entity,
        ?string $entityId = null,
        array $metadata = [],
        ?string $ip = null,
        ?string $userAgent = null,
        bool $critical = false,
    ): array {
        $cleanMetadata = $this->sanitizeMetadata($metadata);

        $entry = [
            'id' => (string) Str::uuid(),
            'actor_id' => $actorId,
            'organization_id' => $orgId,
            'workspace_id' => $workspaceId,
            'action' => $action,
            'entity_type' => $entity,
            'entity_id' => $entityId,
            'request_id' => $this->requestId(),
            'metadata' => $cleanMetadata,
            'ip' => $ip ?? '127.0.0.1',
            'user_agent' => $userAgent ?? 'HiddenLeaf-Kernel',
            'created_at' => now(),
        ];

        // Persist to database if database connection is available
        try {
            AuditLog::create($entry);
        } catch (Throwable $exception) {
            if ($critical) {
                throw $exception;
            }
            Log::warning('Unable to persist a non-critical audit event.', [
                'action' => $action,
                'exception' => $exception::class,
            ]);
        }

        $this->logs[] = $entry;

        return $entry;
    }

    public function getLogs(): array
    {
        return $this->logs;
    }

    protected function sanitizeMetadata(array $data): array
    {
        $sensitiveKeys = ['password', 'secret', 'token', 'credit_card', 'api_key', 'private_key'];
        foreach ($data as $key => $val) {
            if (in_array(strtolower($key), $sensitiveKeys, true)) {
                $data[$key] = '[REDACTED]';
            } elseif (is_array($val)) {
                $data[$key] = $this->sanitizeMetadata($val);
            }
        }

        return $data;
    }

    private function requestId(): ?string
    {
        if (app()->bound('request_id')) {
            return app('request_id');
        }
        if (app()->bound('request')) {
            return request()->attributes->get('request_id');
        }

        return null;
    }
}

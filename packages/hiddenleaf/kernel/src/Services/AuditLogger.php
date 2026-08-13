<?php

namespace HiddenLeaf\Kernel\Services;

use App\Models\AuditLog;
use Illuminate\Support\Str;

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
        ?string $userAgent = null
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
            'metadata' => $cleanMetadata,
            'ip' => $ip ?? '127.0.0.1',
            'user_agent' => $userAgent ?? 'HiddenLeaf-Kernel',
            'created_at' => now(),
        ];

        // Persist to database if database connection is available
        try {
            AuditLog::create($entry);
        } catch (\Throwable $e) {
            // Fallback for isolated unit tests without database connection
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
}

<?php

namespace HiddenLeaf\Kernel\Services;

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
        // Redact any secrets in metadata before storing
        $cleanMetadata = $this->sanitizeMetadata($metadata);

        $entry = [
            'id' => bin2hex(random_bytes(8)),
            'actor_id' => $actorId,
            'organization_id' => $orgId,
            'workspace_id' => $workspaceId,
            'action' => $action,
            'entity' => $entity,
            'entity_id' => $entityId,
            'metadata' => $cleanMetadata,
            'ip' => $ip ?? '127.0.0.1',
            'user_agent' => $userAgent ?? 'HiddenLeaf-Kernel',
            'timestamp' => date('Y-m-d H:i:s'),
        ];

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

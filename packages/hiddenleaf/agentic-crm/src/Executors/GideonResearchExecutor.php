<?php

namespace HiddenLeaf\AgenticCrm\Executors;

use HiddenLeaf\AgenticCrm\Contracts\TaskExecutor;
use HiddenLeaf\AgenticCrm\Domain\AgentRunResult;
use HiddenLeaf\AgenticCrm\Domain\AgentTask;
use HiddenLeaf\AgenticCrm\Domain\Finding;

final class GideonResearchExecutor implements TaskExecutor
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly string $sharedSecret,
        private readonly int $timeoutSeconds = 45,
        private readonly bool $allowInsecureHttp = false,
    ) {
        if (!$allowInsecureHttp && !str_starts_with(strtolower($baseUrl), 'https://')) {
            throw new \InvalidArgumentException('Gideon research endpoint must use HTTPS.');
        }
        if (strlen($sharedSecret) < 32) {
            throw new \InvalidArgumentException('Gideon HMAC secret must be at least 32 characters.');
        }
    }

    public function execute(AgentTask $task): AgentRunResult
    {
        $body = json_encode([
            'task_id' => $task->id,
            'organization_id' => $task->organizationId,
            'workspace_id' => $task->workspaceId,
            'kind' => $task->kind,
            'entity' => ['type' => $task->entityType, 'id' => $task->entityId],
            'payload' => $task->payload,
            'requirements' => ['evidence_required' => true, 'model_confidence_forbidden' => true],
        ], JSON_THROW_ON_ERROR);

        $timestamp = (string) time();
        $signature = hash_hmac('sha256', $timestamp . '.' . $body, $this->sharedSecret);
        $ch = curl_init(rtrim($this->baseUrl, '/') . '/v1/agent/research');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $this->timeoutSeconds,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'X-HiddenLeaf-Timestamp: ' . $timestamp,
                'X-HiddenLeaf-Signature: ' . $signature,
            ],
        ]);
        $raw = curl_exec($ch);
        if ($raw === false) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new \RuntimeException('Gideon research request failed: ' . $error);
        }
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);
        if ($status < 200 || $status >= 300) throw new \RuntimeException("Gideon research returned HTTP {$status}.");

        $data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        $findings = [];
        foreach (($data['findings'] ?? []) as $item) {
            if (!isset($item['field'], $item['source_type'], $item['method']) || !array_key_exists('value', $item)) continue;
            $findings[] = new Finding(
                (string) $item['field'], $item['value'], (string) $item['source_type'], (string) $item['method'],
                isset($item['source_url']) ? (string) $item['source_url'] : null,
                isset($item['observed_at']) ? new \DateTimeImmutable($item['observed_at']) : null,
                max(1, (int) ($item['corroboration_count'] ?? 1)), (bool) ($item['conflicted'] ?? false), (array) ($item['metadata'] ?? [])
            );
        }

        return new AgentRunResult(
            $findings,
            isset($data['recheck_at']) ? new \DateTimeImmutable($data['recheck_at']) : null,
            isset($data['recheck_reason']) ? (string) $data['recheck_reason'] : null,
            (array) ($data['metadata'] ?? [])
        );
    }
}

<?php

namespace HiddenLeaf\AgenticCrm\Executors;

use HiddenLeaf\AgenticCrm\Contracts\TaskExecutor;
use HiddenLeaf\AgenticCrm\Domain\AgentRunResult;
use HiddenLeaf\AgenticCrm\Domain\AgentTask;
use HiddenLeaf\AgenticCrm\Domain\Finding;

final class DeterministicExecutor implements TaskExecutor
{
    public function execute(AgentTask $task): AgentRunResult
    {
        return match ($task->kind) {
            'contact.normalize_email' => $this->normalizeEmail($task),
            'contact.normalize_phone' => $this->normalizePhone($task),
            'company.normalize_domain' => $this->normalizeDomain($task),
            default => throw new \RuntimeException("Unsupported deterministic task: {$task->kind}"),
        };
    }

    private function normalizeEmail(AgentTask $task): AgentRunResult
    {
        $email = strtolower(trim((string) ($task->payload['email'] ?? '')));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) throw new \InvalidArgumentException('Invalid email address.');
        return new AgentRunResult([new Finding('email', $email, 'first_party_crm_history', 'deterministic_normalization', null, null, 1, false, ['source_attested' => true, 'attested_by' => 'deterministic_executor'])]);
    }

    private function normalizePhone(AgentTask $task): AgentRunResult
    {
        $raw = (string) ($task->payload['phone'] ?? '');
        $leadingPlus = str_starts_with(trim($raw), '+');
        $digits = preg_replace('/\D+/', '', $raw) ?? '';
        if (strlen($digits) < 7 || strlen($digits) > 15) throw new \InvalidArgumentException('Invalid phone number.');
        return new AgentRunResult([new Finding('phone', ($leadingPlus ? '+' : '') . $digits, 'first_party_crm_history', 'deterministic_normalization', null, null, 1, false, ['source_attested' => true, 'attested_by' => 'deterministic_executor'])]);
    }

    private function normalizeDomain(AgentTask $task): AgentRunResult
    {
        $value = trim(strtolower((string) ($task->payload['domain'] ?? '')));
        if (!str_contains($value, '://')) $value = 'https://' . $value;
        $host = parse_url($value, PHP_URL_HOST);
        if (!$host) throw new \InvalidArgumentException('Invalid domain.');
        $host = preg_replace('/^www\./', '', $host) ?? $host;
        return new AgentRunResult([new Finding('domain', $host, 'first_party_crm_history', 'deterministic_normalization', null, null, 1, false, ['source_attested' => true, 'attested_by' => 'deterministic_executor'])]);
    }
}

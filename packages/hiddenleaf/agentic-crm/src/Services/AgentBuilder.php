<?php

namespace HiddenLeaf\AgenticCrm\Services;

use HiddenLeaf\AgenticCrm\Domain\AgentDefinition;

final class AgentBuilder
{
    public function __construct(private readonly array $toolRegistry)
    {
    }

    public function build(string $name, string $instructions, array $allowedTools, array $allowedResources = [], array $allowedHosts = []): AgentDefinition
    {
        if (trim($name) === '' || trim($instructions) === '') {
            throw new \InvalidArgumentException('Agent name and instructions are required.');
        }

        foreach ($allowedTools as $tool) {
            if (!in_array($tool, $this->toolRegistry, true)) {
                throw new \InvalidArgumentException("Unknown or unauthorized tool: {$tool}");
            }
        }

        foreach ($allowedHosts as $host) {
            if ($host === '*' || str_contains($host, '/')) {
                throw new \InvalidArgumentException('Sandbox egress hosts must be explicit hostnames; wildcards and URLs are not allowed.');
            }
        }

        sort($allowedTools);
        sort($allowedResources);
        sort($allowedHosts);
        $hash = hash('sha256', json_encode([$name, $instructions, $allowedTools, $allowedResources, $allowedHosts], JSON_THROW_ON_ERROR));

        return new AgentDefinition($name, $instructions, $allowedTools, $allowedResources, $allowedHosts, $hash);
    }

    public function canUseTool(AgentDefinition $agent, string $tool): bool
    {
        return in_array($tool, $agent->allowedTools, true);
    }

    public function canAccessResource(AgentDefinition $agent, string $resource): bool
    {
        return in_array($resource, $agent->allowedResources, true);
    }

    public function canEgressTo(AgentDefinition $agent, string $host): bool
    {
        return in_array(strtolower($host), array_map('strtolower', $agent->allowedHosts), true);
    }
}

<?php

namespace HiddenLeaf\AgenticCrm\Services;

use HiddenLeaf\AgenticCrm\Contracts\ToolHandler;
use HiddenLeaf\AgenticCrm\Domain\AgentDefinition;

final class ScopedToolGateway
{
    /** @var array<string, ToolHandler> */
    private array $handlers = [];

    /** @param ToolHandler[] $handlers */
    public function __construct(array $handlers)
    {
        foreach ($handlers as $handler) {
            $this->handlers[$handler->name()] = $handler;
        }
    }

    public function invoke(AgentDefinition $agent, string $tool, array $input, array $context = []): array
    {
        if (!in_array($tool, $agent->allowedTools, true)) {
            throw new \RuntimeException("Agent is not authorized to invoke tool: {$tool}");
        }
        $handler = $this->handlers[$tool] ?? null;
        if (!$handler) {
            throw new \RuntimeException("Tool is not registered: {$tool}");
        }

        $resource = $context['resource'] ?? null;
        if ($resource !== null && !in_array($resource, $agent->allowedResources, true)) {
            throw new \RuntimeException("Agent is not authorized for resource: {$resource}");
        }

        return $handler->execute($input, $context);
    }
}

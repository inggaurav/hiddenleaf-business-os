<?php

namespace App\Domain\Automation\Missions;

use App\Domain\MrFox\DTO\AiRequest;
use App\Domain\MrFox\DTO\ToolContext;
use App\Domain\MrFox\Providers\ProviderRouter;
use App\Domain\MrFox\Tools\MrFoxToolRegistry;
use App\Models\MrFoxMission;
use App\Models\MrFoxMissionStep;

class MissionPlanner
{
    public function __construct(
        private ?ProviderRouter $providerRouter = null
    ) {
        $this->providerRouter = $providerRouter ?? app(ProviderRouter::class);
    }

    /**
     * Generate or refine a step-by-step plan for a mission.
     *
     * @return array{steps: array, plan_summary: string}
     */
    public function plan(ToolContext $context, MrFoxMission $mission): array
    {
        $toolRegistry = app(MrFoxToolRegistry::class);
        $allowedTools = $mission->allowed_tools ?: array_keys($toolRegistry->all());
        $toolsListStr = implode(', ', $allowedTools);

        $prompt = "You are the Mr. Fox Executive Mission Planner.\n" .
            "Objective: {$mission->objective}\n" .
            "Allowed Tools: {$toolsListStr}\n\n" .
            "Generate a sequential plan with up to {$mission->max_steps} steps to accomplish this objective.\n" .
            "Respond ONLY with a JSON array of step objects, where each object has:\n" .
            "- \"tool\": exact tool name from the allowed list\n" .
            "- \"params\": object containing the tool input arguments\n" .
            "- \"description\": brief human-readable description of this step\n\n" .
            "Do not include explanation outside the JSON array.";

        $provider = $this->providerRouter->resolve($context->workspace);
        $aiRes = $provider->chat(new AiRequest(messages: [
            ['role' => 'user', 'content' => $prompt],
        ]));

        $rawJson = trim($aiRes->content);
        if (str_starts_with($rawJson, '```json')) {
            $rawJson = trim(substr($rawJson, 7, -3));
        } elseif (str_starts_with($rawJson, '```')) {
            $rawJson = trim(substr($rawJson, 3, -3));
        }

        $steps = json_decode($rawJson, true);
        if (! is_array($steps)) {
            $steps = [
                ['tool' => $allowedTools[0] ?? 'business.dashboard.summary', 'params' => [], 'description' => 'Gather initial business telemetry'],
            ];
        }

        // Validate steps against registered tools and allowlist
        $validatedSteps = [];
        foreach ($steps as $idx => $s) {
            $toolName = $s['tool'] ?? '';
            if (! $toolRegistry->has($toolName) || ! in_array($toolName, $allowedTools, true)) {
                continue;
            }
            $validatedSteps[] = [
                'sequence' => count($validatedSteps) + 1,
                'tool' => $toolName,
                'params' => $s['params'] ?? [],
                'description' => $s['description'] ?? "Execute {$toolName}",
            ];
        }

        // Persist steps to database
        $mission->steps()->delete();
        foreach ($validatedSteps as $vStep) {
            MrFoxMissionStep::create([
                'mission_id' => $mission->id,
                'sequence' => $vStep['sequence'],
                'tool_name' => $vStep['tool'],
                'input_params' => $vStep['params'],
                'status' => 'pending',
                'observation' => $vStep['description'],
            ]);
        }

        $mission->update([
            'plan_json' => $validatedSteps,
            'status' => 'queued',
            'progress_summary' => 'Plan generated with ' . count($validatedSteps) . ' step(s).',
        ]);

        return [
            'steps' => $validatedSteps,
            'plan_summary' => "Generated {$mission->name} plan with " . count($validatedSteps) . ' validated steps.',
        ];
    }
}

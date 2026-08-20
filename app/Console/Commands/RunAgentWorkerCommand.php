<?php

namespace HiddenLeaf\Console\Commands;

use HiddenLeaf\AgenticCrm\Services\AgentOrchestrator;
use Illuminate\Console\Command;

final class RunAgentWorkerCommand extends Command
{
    protected $signature = 'agent:work {organization} {workspace} {--max=25} {--worker=}';
    protected $description = 'Process durable HiddenLeaf agent tasks for one tenant-scoped workspace';

    public function __construct(private readonly AgentOrchestrator $orchestrator)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $organizationId = (int) $this->argument('organization');
        $workspaceId = (int) $this->argument('workspace');
        $max = max(1, min(1000, (int) $this->option('max')));
        $workerId = (string) ($this->option('worker') ?: gethostname() . ':' . getmypid());
        $processed = 0;
        while ($processed < $max && $this->orchestrator->runOne($organizationId, $workspaceId, $workerId)) {
            $processed++;
        }
        $this->info("Processed {$processed} agent task(s).");
        return Command::SUCCESS;
    }
}

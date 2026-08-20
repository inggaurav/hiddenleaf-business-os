<?php

namespace HiddenLeaf\AgenticCrm;

use HiddenLeaf\AgenticCrm\Contracts\AgentDefinitionStore;
use HiddenLeaf\AgenticCrm\Contracts\AgentStore;
use HiddenLeaf\AgenticCrm\Executors\DeterministicExecutor;
use HiddenLeaf\AgenticCrm\Executors\GideonResearchExecutor;
use HiddenLeaf\AgenticCrm\Infrastructure\PostgresAgentDefinitionStore;
use HiddenLeaf\AgenticCrm\Infrastructure\PostgresAgentStore;
use HiddenLeaf\AgenticCrm\Services\AgentOrchestrator;
use HiddenLeaf\Kernel\Registries\ModuleRegistry;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;

final class AgenticCrmServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/agentic-crm.php', 'agentic-crm');

        $this->app->singleton(AgentStore::class, fn () => new PostgresAgentStore(DB::connection()->getPdo()));
        $this->app->singleton(AgentDefinitionStore::class, fn () => new PostgresAgentDefinitionStore(DB::connection()->getPdo()));
        $this->app->singleton(DeterministicExecutor::class, fn () => new DeterministicExecutor());
        $this->app->singleton(GideonResearchExecutor::class, fn () => new GideonResearchExecutor(
            (string) config('agentic-crm.gideon.base_url'),
            (string) config('agentic-crm.gideon.hmac_secret'),
            (int) config('agentic-crm.gideon.timeout_seconds', 45),
            (bool) config('agentic-crm.gideon.allow_insecure_http', false),
        ));
        $this->app->singleton(AgentOrchestrator::class, fn ($app) => new AgentOrchestrator(
            $app->make(AgentStore::class),
            $app->make(DeterministicExecutor::class),
            $app->make(GideonResearchExecutor::class),
        ));
    }

    public function boot(): void
    {
        if ($this->app->bound(ModuleRegistry::class)) {
            $this->app->make(ModuleRegistry::class)->register(new AgenticCrmModule());
        }
    }
}

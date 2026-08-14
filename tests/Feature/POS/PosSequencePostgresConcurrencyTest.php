<?php

namespace Tests\Feature\POS;

use App\Models\Organization;
use App\Models\Plan;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Tests\TestCase;

class PosSequencePostgresConcurrencyTest extends TestCase
{
    use DatabaseMigrations;

    public function test_first_pos_and_return_numbers_are_unique_under_real_overlap(): void
    {
        if (config('database.default') !== 'pgsql') {
            $this->markTestSkipped('Real sequence concurrency proof runs on PostgreSQL only.');
        }

        $user = User::factory()->create(['role' => 'company_admin']);
        $plan = Plan::create(['name' => 'Sequence Concurrency', 'status' => true, 'modules' => ['pos'], 'created_by' => $user->id]);
        $org = Organization::factory()->create(['owner_id' => $user->id, 'plan_id' => $plan->id]);
        $workspace = Workspace::factory()->create(['organization_id' => $org->id, 'created_by' => $user->id]);

        foreach (['pos' => 'POS-', 'return' => 'RET-'] as $kind => $prefix) {
            $results = $this->runSequenceWorkers($kind, $workspace->id);
            $this->assertSame(2, $results->where('ok', true)->count(), $results->toJson());
            $numbers = $results->pluck('number')->sort()->values();
            $this->assertCount(2, $numbers->unique());
            $this->assertStringStartsWith($prefix.now()->format('Ymd').'-', $numbers[0]);
            $this->assertStringEndsWith('00001', $numbers[0]);
            $this->assertStringEndsWith('00002', $numbers[1]);
        }
    }

    private function runSequenceWorkers(string $kind, int $workspaceId)
    {
        $dir = storage_path('framework/testing-concurrency/'.uniqid('sequence-'.$kind.'-', true));
        mkdir($dir, 0777, true);
        $start = $dir.'/start';
        $workers = [];

        foreach ([0, 1] as $index) {
            $ready = $dir.'/ready-'.$index;
            $result = $dir.'/result-'.$index.'.json';
            $log = $dir.'/worker-'.$index.'.log';
            $command = [PHP_BINARY, base_path('artisan'), 'pos:sequence-concurrency-probe', $kind, (string) $workspaceId, $ready, $start, $result];
            $process = proc_open($command, [0 => ['pipe', 'r'], 1 => ['file', $log, 'a'], 2 => ['file', $log, 'a']], $pipes, base_path(), $this->childEnvironment());
            $this->assertIsResource($process, 'Failed to start sequence worker.');
            $workers[] = compact('process', 'ready', 'result', 'log');
        }

        $deadline = microtime(true) + 10;
        while (! collect($workers)->every(fn ($worker) => file_exists($worker['ready'])) && microtime(true) < $deadline) {
            usleep(20_000);
        }
        $this->assertTrue(collect($workers)->every(fn ($worker) => file_exists($worker['ready'])), 'Sequence workers did not reach barrier.');
        touch($start);

        foreach ($workers as $worker) {
            $deadline = microtime(true) + 20;
            do {
                $status = proc_get_status($worker['process']);
                if (! $status['running']) {
                    proc_close($worker['process']);

                    continue 2;
                }
                usleep(20_000);
            } while (microtime(true) < $deadline);
            proc_terminate($worker['process']);
            $this->fail('Sequence worker timed out. '.(file_exists($worker['log']) ? file_get_contents($worker['log']) : ''));
        }

        return collect($workers)->map(fn ($worker) => json_decode((string) file_get_contents($worker['result']), true));
    }

    private function childEnvironment(): array
    {
        $environment = getenv() ?: [];
        $config = config('database.connections.pgsql');
        foreach ([
            'APP_ENV' => 'testing', 'DB_CONNECTION' => 'pgsql',
            'DB_HOST' => $config['host'] ?? null, 'DB_PORT' => $config['port'] ?? null,
            'DB_DATABASE' => $config['database'] ?? null, 'DB_USERNAME' => $config['username'] ?? null,
            'DB_PASSWORD' => $config['password'] ?? '', 'CACHE_STORE' => 'array',
            'SESSION_DRIVER' => 'array', 'QUEUE_CONNECTION' => 'sync',
        ] as $key => $value) {
            if ($value !== null) {
                $environment[$key] = (string) $value;
            }
        }

        return $environment;
    }
}

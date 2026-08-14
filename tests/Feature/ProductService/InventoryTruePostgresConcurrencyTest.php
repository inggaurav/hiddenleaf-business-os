<?php

namespace Tests\Feature\ProductService;

use App\Models\Organization;
use App\Models\Plan;
use App\Models\ProductServiceItem;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseStock;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Tests\TestCase;

class InventoryTruePostgresConcurrencyTest extends TestCase
{
    use DatabaseMigrations;

    public function test_two_independent_processes_cannot_both_consume_the_last_unit(): void
    {
        $tenant = $this->inventoryFixture(true);
        $results = $this->runWorkers($tenant, -1);

        $this->assertSame(1, $results->where('ok', true)->count(), $results->toJson());
        $this->assertSame(1, $results->where('ok', false)->count(), $results->toJson());
        $this->assertSame('0.0000', $this->stock($tenant)->quantity);
        $this->assertSame(1, StockMovement::where('type', 'concurrency_probe_out')->where('warehouse_id', $tenant['warehouse']->id)->where('product_id', $tenant['product']->id)->count());
    }

    public function test_two_first_inbound_movements_create_one_materialized_stock_row(): void
    {
        $tenant = $this->inventoryFixture(false);
        $results = $this->runWorkers($tenant, 1);

        $this->assertSame(2, $results->where('ok', true)->count(), $results->toJson());
        $this->assertSame(1, WarehouseStock::where('warehouse_id', $tenant['warehouse']->id)->where('product_id', $tenant['product']->id)->count());
        $this->assertSame('2.0000', $this->stock($tenant)->quantity);
        $this->assertSame(2, StockMovement::where('type', 'concurrency_probe_in')->where('warehouse_id', $tenant['warehouse']->id)->where('product_id', $tenant['product']->id)->count());
    }

    private function inventoryFixture(bool $withLastUnit): array
    {
        if (config('database.default') !== 'pgsql') {
            $this->markTestSkipped('Real inventory concurrency proof runs on PostgreSQL only.');
        }

        $user = User::factory()->create(['role' => 'company_admin']);
        $plan = Plan::create(['name' => 'Concurrency', 'status' => true, 'modules' => ['productservice'], 'created_by' => $user->id]);
        $org = Organization::factory()->create(['owner_id' => $user->id, 'plan_id' => $plan->id]);
        $workspace = Workspace::factory()->create(['organization_id' => $org->id, 'created_by' => $user->id]);
        $warehouse = Warehouse::create(['organization_id' => $org->id, 'workspace_id' => $workspace->id, 'name' => 'Concurrency Warehouse', 'created_by' => $user->id]);
        $product = ProductServiceItem::create([
            'organization_id' => $org->id,
            'workspace_id' => $workspace->id,
            'name' => 'Concurrent Unit',
            'sku' => 'CONC-'.uniqid(),
            'type' => 'product',
            'sale_price' => '10.00',
            'purchase_price' => '5.00',
            'is_active' => true,
            'created_by' => $user->id,
        ]);
        if ($withLastUnit) {
            WarehouseStock::create(['warehouse_id' => $warehouse->id, 'product_id' => $product->id, 'quantity' => '1.0000']);
        }

        return compact('org', 'workspace', 'warehouse', 'product');
    }

    private function runWorkers(array $tenant, int $direction)
    {
        $dir = storage_path('framework/testing-concurrency/'.uniqid('inventory-', true));
        mkdir($dir, 0777, true);
        $start = $dir.'/start';
        $workers = [];

        foreach ([101, 202] as $index => $reference) {
            $ready = $dir.'/ready-'.$index;
            $result = $dir.'/result-'.$index.'.json';
            $log = $dir.'/worker-'.$index.'.log';
            $command = [
                PHP_BINARY, base_path('artisan'), 'inventory:concurrency-probe',
                (string) $tenant['org']->id, (string) $tenant['workspace']->id,
                (string) $tenant['warehouse']->id, (string) $tenant['product']->id,
                '1.0000', (string) $reference, $ready, $start, $result, (string) $direction,
            ];
            $process = proc_open($command, [0 => ['pipe', 'r'], 1 => ['file', $log, 'a'], 2 => ['file', $log, 'a']], $pipes, base_path(), $this->childEnvironment());
            $this->assertIsResource($process, 'Failed to start inventory concurrency worker.');
            $workers[] = compact('process', 'ready', 'result', 'log');
        }

        $this->waitUntil(fn () => collect($workers)->every(fn ($worker) => file_exists($worker['ready'])), 10, 'Both inventory workers did not reach the start barrier.');
        touch($start);
        foreach ($workers as $worker) {
            $this->waitForProcess($worker['process'], 20, $worker['log']);
        }

        return collect($workers)->map(fn ($worker) => json_decode((string) file_get_contents($worker['result']), true));
    }

    private function stock(array $tenant): WarehouseStock
    {
        return WarehouseStock::where('warehouse_id', $tenant['warehouse']->id)->where('product_id', $tenant['product']->id)->firstOrFail();
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
            if ($value !== null) $environment[$key] = (string) $value;
        }
        return $environment;
    }

    private function waitUntil(callable $condition, int $seconds, string $message): void
    {
        $deadline = microtime(true) + $seconds;
        while (! $condition() && microtime(true) < $deadline) usleep(20_000);
        $this->assertTrue($condition(), $message);
    }

    private function waitForProcess($process, int $seconds, string $log): void
    {
        $deadline = microtime(true) + $seconds;
        do {
            $status = proc_get_status($process);
            if (! $status['running']) {
                proc_close($process);
                return;
            }
            usleep(20_000);
        } while (microtime(true) < $deadline);
        proc_terminate($process);
        $this->fail('Concurrency worker timed out. '.(file_exists($log) ? file_get_contents($log) : ''));
    }
}

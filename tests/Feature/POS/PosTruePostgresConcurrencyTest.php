<?php

namespace Tests\Feature\POS;

use App\Models\Organization;
use App\Models\Plan;
use App\Models\POS\BillingCounter;
use App\Models\POS\PosSale;
use App\Models\ProductServiceItem;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseStock;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Tests\TestCase;

class PosTruePostgresConcurrencyTest extends TestCase
{
    use DatabaseMigrations;

    public function test_two_independent_checkouts_cannot_oversell_last_unit(): void
    {
        $tenant = $this->tenantWithOneUnit();
        $results = $this->runWorkers($tenant, ['concurrent-sale-a', 'concurrent-sale-b']);

        $this->assertSame(1, $results->where('ok', true)->count(), $results->toJson());
        $this->assertSame(1, $results->where('ok', false)->count(), $results->toJson());
        $this->assertSame(1, PosSale::where('workspace_id', $tenant['workspace']->id)->count());
        $this->assertSame('0.0000', WarehouseStock::where('warehouse_id', $tenant['warehouse']->id)->where('product_id', $tenant['product']->id)->firstOrFail()->quantity);
        $this->assertSame(1, StockMovement::where('workspace_id', $tenant['workspace']->id)->where('type', 'pos_sale')->count());
    }

    public function test_same_idempotency_key_concurrently_returns_one_sale_and_one_stock_deduction(): void
    {
        $tenant = $this->tenantWithOneUnit();
        $results = $this->runWorkers($tenant, ['same-concurrent-key', 'same-concurrent-key']);

        $this->assertSame(2, $results->where('ok', true)->count(), $results->toJson());
        $saleIds = $results->pluck('sale_id')->unique()->values();
        $this->assertCount(1, $saleIds, $results->toJson());
        $this->assertSame(1, PosSale::where('workspace_id', $tenant['workspace']->id)->count());
        $this->assertSame('0.0000', WarehouseStock::where('warehouse_id', $tenant['warehouse']->id)->where('product_id', $tenant['product']->id)->firstOrFail()->quantity);
        $this->assertSame(1, StockMovement::where('workspace_id', $tenant['workspace']->id)->where('type', 'pos_sale')->count());
    }

    private function tenantWithOneUnit(): array
    {
        if (config('database.default') !== 'pgsql') {
            $this->markTestSkipped('Real POS concurrency proof runs on PostgreSQL only.');
        }

        $user = User::factory()->create(['role' => 'company_admin']);
        $plan = Plan::create(['name' => 'POS Concurrency', 'status' => true, 'modules' => ['pos', 'productservice', 'account'], 'created_by' => $user->id]);
        $org = Organization::factory()->create(['owner_id' => $user->id, 'plan_id' => $plan->id]);
        $workspace = Workspace::factory()->create(['organization_id' => $org->id, 'created_by' => $user->id]);
        $org->members()->attach($user, ['role' => 'owner']);
        $workspace->members()->attach($user);
        $warehouse = Warehouse::create(['organization_id' => $org->id, 'workspace_id' => $workspace->id, 'name' => 'Concurrent POS', 'created_by' => $user->id]);
        $counter = BillingCounter::create(['organization_id' => $org->id, 'workspace_id' => $workspace->id, 'warehouse_id' => $warehouse->id, 'name' => 'Counter', 'counter_number' => 'CC-1', 'created_by' => $user->id]);
        $product = ProductServiceItem::create([
            'organization_id' => $org->id,
            'workspace_id' => $workspace->id,
            'name' => 'Last POS Unit',
            'sku' => 'POS-LAST-1',
            'type' => 'product',
            'sale_price' => '25.00',
            'purchase_price' => '10.00',
            'is_active' => true,
            'created_by' => $user->id,
        ]);
        WarehouseStock::create(['warehouse_id' => $warehouse->id, 'product_id' => $product->id, 'quantity' => '1.0000']);

        return compact('user', 'org', 'workspace', 'warehouse', 'counter', 'product');
    }

    private function runWorkers(array $tenant, array $keys)
    {
        $dir = storage_path('framework/testing-concurrency/'.uniqid('pos-', true));
        mkdir($dir, 0777, true);
        $start = $dir.'/start';
        $workers = [];

        foreach ($keys as $index => $key) {
            $ready = $dir.'/ready-'.$index;
            $result = $dir.'/result-'.$index.'.json';
            $log = $dir.'/worker-'.$index.'.log';
            $command = [
                PHP_BINARY,
                base_path('artisan'),
                'pos:concurrency-probe',
                (string) $tenant['org']->id,
                (string) $tenant['workspace']->id,
                (string) $tenant['user']->id,
                (string) $tenant['counter']->id,
                (string) $tenant['warehouse']->id,
                (string) $tenant['product']->id,
                '1.0000',
                $key,
                $ready,
                $start,
                $result,
            ];
            $workers[] = [
                'process' => proc_open($command, [0 => ['pipe', 'r'], 1 => ['file', $log, 'a'], 2 => ['file', $log, 'a']], $pipes, base_path(), $this->childEnvironment()),
                'ready' => $ready,
                'result' => $result,
                'log' => $log,
            ];
        }

        $this->waitUntil(fn () => collect($workers)->every(fn ($worker) => file_exists($worker['ready'])), 10, 'Both POS workers did not reach the start barrier.');
        touch($start);
        foreach ($workers as $worker) {
            $this->waitForProcess($worker['process'], 25, $worker['log']);
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

    private function waitUntil(callable $condition, int $seconds, string $message): void
    {
        $deadline = microtime(true) + $seconds;
        while (! $condition() && microtime(true) < $deadline) {
            usleep(20_000);
        }
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
        $this->fail('POS concurrency worker timed out. '.(file_exists($log) ? file_get_contents($log) : ''));
    }
}

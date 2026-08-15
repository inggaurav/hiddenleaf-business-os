<?php

namespace Tests\Feature\SalesProcurement;

use App\Models\Organization;
use App\Models\Plan;
use App\Models\ProductServiceCategory;
use App\Models\ProductServiceItem;
use App\Models\SalesInvoice;
use App\Models\SalesInvoiceItem;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseStock;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Tests\TestCase;

class SalesProcurementPostgresConcurrencyTest extends TestCase
{
    use DatabaseMigrations;

    public function test_two_concurrent_sales_invoice_numbers_are_unique_and_monotonic(): void
    {
        if (config('database.default') !== 'pgsql') {
            $this->markTestSkipped('Real document sequence concurrency proof runs on PostgreSQL only.');
        }

        $user = User::factory()->create(['role' => 'company_admin']);
        $plan = Plan::create(['name' => 'Sales Concurrency', 'status' => true, 'modules' => ['sales'], 'created_by' => $user->id]);
        $org = Organization::factory()->create(['owner_id' => $user->id, 'plan_id' => $plan->id]);
        $workspace = Workspace::factory()->create(['organization_id' => $org->id, 'created_by' => $user->id]);

        $results = $this->runWorkers('sales_invoice_number', $workspace->id, 0, $user->id);

        $this->assertSame(2, $results->where('ok', true)->count(), $results->toJson());
        $numbers = $results->pluck('number')->sort()->values();
        $this->assertCount(2, $numbers->unique());
        $this->assertStringStartsWith('SI-'.now()->format('Ymd').'-', $numbers[0]);
        $this->assertStringEndsWith('00001', $numbers[0]);
        $this->assertStringEndsWith('00002', $numbers[1]);
    }

    public function test_two_concurrent_purchase_invoice_numbers_are_unique_and_monotonic(): void
    {
        if (config('database.default') !== 'pgsql') {
            $this->markTestSkipped('Real document sequence concurrency proof runs on PostgreSQL only.');
        }

        $user = User::factory()->create(['role' => 'company_admin']);
        $plan = Plan::create(['name' => 'Purchase Concurrency', 'status' => true, 'modules' => ['procurement'], 'created_by' => $user->id]);
        $org = Organization::factory()->create(['owner_id' => $user->id, 'plan_id' => $plan->id]);
        $workspace = Workspace::factory()->create(['organization_id' => $org->id, 'created_by' => $user->id]);

        $results = $this->runWorkers('purchase_invoice_number', $workspace->id, 0, $user->id);

        $this->assertSame(2, $results->where('ok', true)->count(), $results->toJson());
        $numbers = $results->pluck('number')->sort()->values();
        $this->assertCount(2, $numbers->unique());
        $this->assertStringStartsWith('PI-'.now()->format('Ymd').'-', $numbers[0]);
        $this->assertStringEndsWith('00001', $numbers[0]);
        $this->assertStringEndsWith('00002', $numbers[1]);
    }

    public function test_two_concurrent_post_attempts_on_same_sales_invoice_executes_only_once(): void
    {
        if (config('database.default') !== 'pgsql') {
            $this->markTestSkipped('Real document posting concurrency proof runs on PostgreSQL only.');
        }

        $user = User::factory()->create(['role' => 'company_admin']);
        $plan = Plan::create(['name' => 'Sales Concurrency', 'status' => true, 'modules' => ['sales', 'inventory', 'account'], 'created_by' => $user->id]);
        $org = Organization::factory()->create(['owner_id' => $user->id, 'plan_id' => $plan->id]);
        $workspace = Workspace::factory()->create(['organization_id' => $org->id, 'created_by' => $user->id]);

        $warehouse = Warehouse::create(['organization_id' => $org->id, 'workspace_id' => $workspace->id, 'name' => 'Main Warehouse']);
        $category = ProductServiceCategory::create(['organization_id' => $org->id, 'workspace_id' => $workspace->id, 'name' => 'Hardware', 'type' => 'product']);
        $product = ProductServiceItem::create([
            'organization_id' => $org->id,
            'workspace_id' => $workspace->id,
            'category_id' => $category->id,
            'name' => 'Server Unit',
            'sku' => 'SRV-001',
            'type' => 'product',
            'sale_price' => 1000.00,
            'purchase_price' => 700.00,
            'quantity' => 10.0000,
            'is_active' => true,
        ]);

        WarehouseStock::create([
            'organization_id' => $org->id,
            'workspace_id' => $workspace->id,
            'warehouse_id' => $warehouse->id,
            'product_id' => $product->id,
            'quantity' => 10.0000,
        ]);

        $invoice = SalesInvoice::create([
            'organization_id' => $org->id,
            'workspace_id' => $workspace->id,
            'warehouse_id' => $warehouse->id,
            'invoice_id' => 'SI-TEST-999',
            'issue_date' => now()->toDateString(),
            'total_amount' => 1000.00,
            'status' => 0, // Draft
            'created_by' => $user->id,
        ]);

        SalesInvoiceItem::create([
            'invoice_id' => $invoice->id,
            'product_id' => $product->id,
            'item_name' => 'Server Unit',
            'quantity' => 1,
            'price' => 1000.00,
        ]);

        $results = $this->runWorkers('post_sales_invoice', $workspace->id, $invoice->id, $user->id);

        // Exactly one process must succeed; the other process must fail with conflict / non-draft error
        $successes = $results->where('ok', true)->count();
        $failures = $results->where('ok', false)->count();

        $this->assertSame(1, $successes, 'Expected exactly one process to successfully post the invoice: '.$results->toJson());
        $this->assertSame(1, $failures, 'Expected exactly one process to be rejected: '.$results->toJson());
        $this->assertSame(1, (int) $invoice->refresh()->status);
    }

    private function runWorkers(string $probeType, int $workspaceId, int $targetId, int $userId)
    {
        $dir = storage_path('framework/testing-concurrency/'.uniqid('sales-proc-'.$probeType.'-', true));
        mkdir($dir, 0777, true);
        $start = $dir.'/start';
        $workers = [];

        foreach ([0, 1] as $index) {
            $ready = $dir.'/ready-'.$index;
            $result = $dir.'/result-'.$index.'.json';
            $log = $dir.'/worker-'.$index.'.log';
            $command = [
                PHP_BINARY,
                base_path('artisan'),
                'sales-procurement:concurrency-probe',
                $probeType,
                (string) $workspaceId,
                (string) $targetId,
                (string) $userId,
                $ready,
                $start,
                $result,
            ];
            $process = proc_open($command, [0 => ['pipe', 'r'], 1 => ['file', $log, 'a'], 2 => ['file', $log, 'a']], $pipes, base_path(), $this->childEnvironment());
            $this->assertIsResource($process, 'Failed to start worker.');
            $workers[] = compact('process', 'ready', 'result', 'log');
        }

        $deadline = microtime(true) + 10;
        while (! collect($workers)->every(fn ($worker) => file_exists($worker['ready'])) && microtime(true) < $deadline) {
            usleep(20_000);
        }
        $this->assertTrue(collect($workers)->every(fn ($worker) => file_exists($worker['ready'])), 'Workers did not reach barrier.');
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
            $this->fail('Worker timed out. '.(file_exists($worker['log']) ? file_get_contents($worker['log']) : ''));
        }

        return collect($workers)->map(function ($worker) {
            return file_exists($worker['result'])
                ? json_decode(file_get_contents($worker['result']), true)
                : ['ok' => false, 'error' => 'missing_result'];
        });
    }

    private function childEnvironment(): array
    {
        $env = getenv();
        $env['APP_ENV'] = 'testing';
        $env['DB_CONNECTION'] = 'pgsql';
        $env['DB_HOST'] = env('DB_HOST', '127.0.0.1');
        $env['DB_PORT'] = (string) env('DB_PORT', '5432');
        $env['DB_DATABASE'] = env('DB_DATABASE', 'hiddenleaf_test');
        $env['DB_USERNAME'] = env('DB_USERNAME', 'hiddenleaf');
        $env['DB_PASSWORD'] = env('DB_PASSWORD', 'hiddenleaf');

        return $env;
    }
}

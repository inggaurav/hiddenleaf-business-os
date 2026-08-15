<?php

namespace Tests\Feature\Accounting;

use App\Models\AccountType;
use App\Models\LedgerAccount;
use App\Models\Organization;
use App\Models\Plan;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Tests\TestCase;

class AccountingPostgresConcurrencyTest extends TestCase
{
    use DatabaseMigrations;

    public function test_two_concurrent_journal_entries_receive_unique_monotonic_numbers(): void
    {
        if (config('database.default') !== 'pgsql') {
            $this->markTestSkipped('Real accounting concurrency proof runs on PostgreSQL only.');
        }

        $user = User::factory()->create(['role' => 'company_admin']);
        $plan = Plan::create(['name' => 'Accounting Concurrency', 'status' => true, 'modules' => ['account'], 'created_by' => $user->id]);
        $org = Organization::factory()->create(['owner_id' => $user->id, 'plan_id' => $plan->id]);
        $workspace = Workspace::factory()->create(['organization_id' => $org->id, 'created_by' => $user->id]);

        $assetType = AccountType::create(['organization_id' => $org->id, 'workspace_id' => $workspace->id, 'name' => 'Bank', 'classification' => 'asset', 'normal_balance' => 'debit']);
        $incomeType = AccountType::create(['organization_id' => $org->id, 'workspace_id' => $workspace->id, 'name' => 'Sales', 'classification' => 'income', 'normal_balance' => 'credit']);

        $cash = LedgerAccount::create(['organization_id' => $org->id, 'workspace_id' => $workspace->id, 'account_type_id' => $assetType->id, 'code' => '1000', 'name' => 'Cash', 'currency' => 'USD', 'is_bank' => true]);
        $sales = LedgerAccount::create(['organization_id' => $org->id, 'workspace_id' => $workspace->id, 'account_type_id' => $incomeType->id, 'code' => '4000', 'name' => 'Sales', 'currency' => 'USD']);

        $results = $this->runWorkers('journal', $org->id, $workspace->id, $user->id, $cash->id, $sales->id, '100.00');

        $this->assertSame(2, $results->where('ok', true)->count(), $results->toJson());
        $numbers = $results->pluck('entry_number')->sort()->values();
        $this->assertCount(2, $numbers->unique());
        $this->assertStringStartsWith('JE-'.now()->format('Ymd').'-', $numbers[0]);
        $this->assertStringEndsWith('00001', $numbers[0]);
        $this->assertStringEndsWith('00002', $numbers[1]);
    }

    public function test_two_concurrent_bank_transfers_receive_unique_monotonic_numbers(): void
    {
        if (config('database.default') !== 'pgsql') {
            $this->markTestSkipped('Real accounting concurrency proof runs on PostgreSQL only.');
        }

        $user = User::factory()->create(['role' => 'company_admin']);
        $plan = Plan::create(['name' => 'Transfer Concurrency', 'status' => true, 'modules' => ['account'], 'created_by' => $user->id]);
        $org = Organization::factory()->create(['owner_id' => $user->id, 'plan_id' => $plan->id]);
        $workspace = Workspace::factory()->create(['organization_id' => $org->id, 'created_by' => $user->id]);

        $assetType = AccountType::create(['organization_id' => $org->id, 'workspace_id' => $workspace->id, 'name' => 'Bank', 'classification' => 'asset', 'normal_balance' => 'debit']);
        $cash = LedgerAccount::create(['organization_id' => $org->id, 'workspace_id' => $workspace->id, 'account_type_id' => $assetType->id, 'code' => '1000', 'name' => 'Cash', 'currency' => 'USD', 'is_bank' => true]);
        $payroll = LedgerAccount::create(['organization_id' => $org->id, 'workspace_id' => $workspace->id, 'account_type_id' => $assetType->id, 'code' => '1010', 'name' => 'Payroll', 'currency' => 'USD', 'is_bank' => true]);

        $results = $this->runWorkers('transfer_sequence', $org->id, $workspace->id, $user->id, $cash->id, $payroll->id, '50.00');

        $this->assertSame(2, $results->where('ok', true)->count(), $results->toJson());
        $numbers = $results->pluck('transfer_number')->sort()->values();
        $this->assertCount(2, $numbers->unique());
        $this->assertStringStartsWith('TRF-'.now()->format('Ymd').'-', $numbers[0]);
        $this->assertStringEndsWith('00001', $numbers[0]);
        $this->assertStringEndsWith('00002', $numbers[1]);
    }

    private function runWorkers(string $type, int $orgId, int $workspaceId, int $userId, int $accFrom, int $accTo, string $amount)
    {
        $dir = storage_path('framework/testing-concurrency/'.uniqid('accounting-'.$type.'-', true));
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
                'accounting:concurrency-probe',
                $type,
                (string) $orgId,
                (string) $workspaceId,
                (string) $userId,
                (string) $accFrom,
                (string) $accTo,
                $amount,
                $ready,
                $start,
                $result,
            ];
            $process = proc_open($command, [0 => ['pipe', 'r'], 1 => ['file', $log, 'a'], 2 => ['file', $log, 'a']], $pipes, base_path(), $this->childEnvironment());
            $this->assertIsResource($process, 'Failed to start accounting worker.');
            $workers[] = compact('process', 'ready', 'result', 'log');
        }

        $deadline = microtime(true) + 10;
        while (! collect($workers)->every(fn ($worker) => file_exists($worker['ready'])) && microtime(true) < $deadline) {
            usleep(20_000);
        }
        $this->assertTrue(collect($workers)->every(fn ($worker) => file_exists($worker['ready'])), 'Accounting workers did not reach barrier.');
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
            $this->fail('Accounting worker timed out. '.(file_exists($worker['log']) ? file_get_contents($worker['log']) : ''));
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

<?php

namespace App\Console\Commands;

use App\Domain\POS\PosCheckoutService;
use App\Models\User;
use Illuminate\Console\Command;
use Throwable;

/** Internal independent-process worker for PostgreSQL POS concurrency tests. */
class PosConcurrencyProbeCommand extends Command
{
    protected $signature = 'pos:concurrency-probe
        {organization}
        {workspace}
        {user}
        {counter}
        {warehouse}
        {product}
        {quantity}
        {idempotencyKey}
        {readyFile}
        {startFile}
        {resultFile}';

    protected $description = 'Internal worker for real POS checkout concurrency regression tests';

    public function handle(PosCheckoutService $checkout): int
    {
        $ready = (string) $this->argument('readyFile');
        $start = (string) $this->argument('startFile');
        $result = (string) $this->argument('resultFile');
        file_put_contents($ready, 'ready');

        $deadline = microtime(true) + 15;
        while (! file_exists($start) && microtime(true) < $deadline) {
            usleep(10_000);
        }
        if (! file_exists($start)) {
            file_put_contents($result, json_encode(['ok' => false, 'error' => 'barrier_timeout']));

            return self::FAILURE;
        }

        try {
            $user = User::findOrFail((int) $this->argument('user'));
            $sale = $checkout->checkout(
                (int) $this->argument('workspace'),
                (int) $this->argument('organization'),
                $user,
                [
                    'billing_counter_id' => (int) $this->argument('counter'),
                    'warehouse_id' => (int) $this->argument('warehouse'),
                    'payment_method' => 'cash',
                    'idempotency_key' => (string) $this->argument('idempotencyKey'),
                    'items' => [[
                        'product_id' => (int) $this->argument('product'),
                        'quantity' => (string) $this->argument('quantity'),
                    ]],
                ]
            );
            file_put_contents($result, json_encode(['ok' => true, 'sale_id' => $sale->id]));

            return self::SUCCESS;
        } catch (Throwable $e) {
            file_put_contents($result, json_encode(['ok' => false, 'error' => $e->getMessage(), 'class' => $e::class]));

            return self::FAILURE;
        }
    }
}

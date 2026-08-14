<?php

namespace App\Console\Commands;

use App\Domain\Inventory\InventoryQuantity;
use App\Domain\Inventory\StockMovementService;
use Illuminate\Console\Command;
use Throwable;

/**
 * Internal regression-test worker used to prove real PostgreSQL lock contention
 * from two independent PHP processes. Not exposed through HTTP.
 */
class InventoryConcurrencyProbeCommand extends Command
{
    protected $signature = 'inventory:concurrency-probe
        {organization}
        {workspace}
        {warehouse}
        {product}
        {quantity}
        {reference}
        {readyFile}
        {startFile}
        {resultFile}';

    protected $description = 'Internal worker for real inventory concurrency regression tests';

    public function handle(StockMovementService $movements): int
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
            $movement = $movements->recordMovement(
                organizationId: (int) $this->argument('organization'),
                workspaceId: (int) $this->argument('workspace'),
                warehouseId: (int) $this->argument('warehouse'),
                productId: (int) $this->argument('product'),
                movementType: 'concurrency_probe',
                quantity: InventoryQuantity::of((string) $this->argument('quantity')),
                direction: -1,
                referenceType: 'concurrency_probe',
                referenceId: (int) $this->argument('reference'),
                reason: 'Concurrent stock probe',
                referenceLineId: 1,
            );
            file_put_contents($result, json_encode(['ok' => true, 'movement_id' => $movement->id]));
            return self::SUCCESS;
        } catch (Throwable $e) {
            file_put_contents($result, json_encode(['ok' => false, 'error' => $e->getMessage(), 'class' => $e::class]));
            return self::FAILURE;
        }
    }
}

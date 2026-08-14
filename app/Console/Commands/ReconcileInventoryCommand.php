<?php

namespace App\Console\Commands;

use App\Domain\Inventory\InventoryBalanceService;
use App\Models\Workspace;
use Illuminate\Console\Command;

class ReconcileInventoryCommand extends Command
{
    protected $signature = 'inventory:reconcile {--workspace= : Specific workspace ID} {--repair : Automatically repair discrepancies}';

    protected $description = 'Reconcile stored warehouse stocks against the historical stock movement ledger';

    public function handle(InventoryBalanceService $balancer): int
    {
        $workspaceId = $this->option('workspace');
        $repair = (bool) $this->option('repair');

        $query = Workspace::query()->with('organization');
        if ($workspaceId) {
            $query->whereKey($workspaceId);
        }

        $workspaces = $query->get();
        if ($workspaces->isEmpty()) {
            $this->warn('No active workspaces found.');

            return 0;
        }

        $allDiscrepancies = 0;

        foreach ($workspaces as $workspace) {
            $this->info("Checking workspace [{$workspace->name}] (ID: {$workspace->id})...");
            $results = $balancer->reconcileWarehouseStocks($workspace->organization_id, $workspace->id, $repair);

            $rows = [];
            foreach ($results as $item) {
                if ($item['status'] !== 'MATCH' || $this->output->isVerbose()) {
                    $rows[] = [
                        $item['warehouse_name'],
                        $item['product_name'],
                        $item['stored_quantity'],
                        $item['calculated_quantity'],
                        $item['difference'],
                        $item['status'],
                    ];
                }
                if ($item['status'] === 'DISCREPANCY') {
                    $allDiscrepancies++;
                }
            }

            if (! empty($rows)) {
                $this->table(['Warehouse', 'Product', 'Stored Qty', 'Calculated Qty', 'Diff', 'Status'], $rows);
            } else {
                $this->info("✓ All product warehouse stock matches movement ledger for workspace [{$workspace->name}].");
            }
        }

        if ($allDiscrepancies > 0 && ! $repair) {
            $this->error("Found {$allDiscrepancies} stock discrepancies. Run with --repair to synchronize.");

            return 1;
        }

        $this->info('Inventory reconciliation complete.');

        return 0;
    }
}

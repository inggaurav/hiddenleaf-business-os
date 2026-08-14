<?php

namespace App\Console\Commands;

use App\Domain\Accounting\FinancialBalanceService;
use Illuminate\Console\Command;

class ReconcileFinancialBalancesCommand extends Command
{
    protected $signature = 'financial:reconcile-balances {--workspace= : Optional workspace ID to scope reconciliation}';

    protected $description = 'Reconcile stored customer and vendor balances against posted invoices, payments, and credit/debit notes';

    public function handle(FinancialBalanceService $balanceService): int
    {
        $workspaceId = $this->option('workspace') ? (int) $this->option('workspace') : null;

        $this->info('Starting financial balance reconciliation...');

        $stats = $balanceService->reconcileWorkspaceBalances($workspaceId);

        $this->table(
            ['Entity', 'Checked', 'Reconciled / Corrected'],
            [
                ['Customers', $stats['customers_checked'], $stats['customers_fixed']],
                ['Vendors', $stats['vendors_checked'], $stats['vendors_fixed']],
            ]
        );

        $this->info('Financial balance reconciliation completed successfully.');

        return self::SUCCESS;
    }
}

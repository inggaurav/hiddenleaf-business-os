<?php

namespace App\Console\Commands;

use App\Domain\Inventory\InvoicePostingService;
use App\Domain\Shared\DocumentNumberService;
use App\Models\PurchaseInvoice;
use App\Models\SalesInvoice;
use App\Models\User;
use Illuminate\Console\Command;
use Throwable;

class SalesProcurementConcurrencyProbeCommand extends Command
{
    protected $signature = 'sales-procurement:concurrency-probe
        {probeType}
        {workspace}
        {targetId}
        {userId}
        {readyFile}
        {startFile}
        {resultFile}';

    protected $description = 'Internal worker for real PostgreSQL Sales & Procurement concurrency regression tests';

    public function handle(
        DocumentNumberService $docNumbers,
        InvoicePostingService $postingService
    ): int {
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
            $probeType = (string) $this->argument('probeType');
            $wsId = (int) $this->argument('workspace');
            $targetId = (int) $this->argument('targetId');
            $userId = (int) $this->argument('userId');
            $actor = $userId ? User::find($userId) : null;

            if ($probeType === 'sales_invoice_number') {
                $number = $docNumbers->next($wsId, 'sales_invoice', 'SI');
                file_put_contents($result, json_encode(['ok' => true, 'number' => $number]));

                return self::SUCCESS;
            }

            if ($probeType === 'purchase_invoice_number') {
                $number = $docNumbers->next($wsId, 'purchase_invoice', 'PI');
                file_put_contents($result, json_encode(['ok' => true, 'number' => $number]));

                return self::SUCCESS;
            }

            if ($probeType === 'post_sales_invoice') {
                $invoice = SalesInvoice::findOrFail($targetId);
                $postingService->postSale($invoice, $actor);
                file_put_contents($result, json_encode(['ok' => true, 'invoice_id' => $invoice->id]));

                return self::SUCCESS;
            }

            if ($probeType === 'post_purchase_invoice') {
                $invoice = PurchaseInvoice::findOrFail($targetId);
                $postingService->postPurchase($invoice, $actor);
                file_put_contents($result, json_encode(['ok' => true, 'invoice_id' => $invoice->id]));

                return self::SUCCESS;
            }

            file_put_contents($result, json_encode(['ok' => false, 'error' => 'unknown_probe_type']));

            return self::FAILURE;
        } catch (Throwable $e) {
            file_put_contents($result, json_encode([
                'ok' => false,
                'error' => $e->getMessage(),
                'class' => get_class($e),
            ]));

            return self::FAILURE;
        }
    }
}

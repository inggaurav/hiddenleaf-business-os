<?php

namespace App\Domain\Inventory;

use App\Domain\Accounting\CommercialAccountingService;
use App\Models\ProductServiceItem;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseReturn;
use App\Models\SalesInvoice;
use App\Models\SalesInvoiceReturn;
use App\Models\User;
use App\Models\Warehouse;
use HiddenLeaf\Kernel\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ReturnPostingService
{
    public function __construct(
        private readonly StockAdjustmentService $stock,
        private readonly AuditLogger $audit,
        private readonly CommercialAccountingService $accounting,
    ) {}

    public function completePurchase(PurchaseReturn $return, User $actor): void
    {
        $invoice = PurchaseInvoice::where('organization_id', $return->organization_id)
            ->where('workspace_id', $return->workspace_id)
            ->findOrFail($return->purchase_invoice_id);
        $this->complete($return, $invoice, $actor, -1, 'purchase_return.completed');
    }

    public function completeSale(SalesInvoiceReturn $return, User $actor): void
    {
        $invoice = SalesInvoice::where('organization_id', $return->organization_id)
            ->where('workspace_id', $return->workspace_id)
            ->findOrFail($return->sales_invoice_id);
        $this->complete($return, $invoice, $actor, 1, 'sales_return.completed');
    }

    private function complete(PurchaseReturn|SalesInvoiceReturn $return, PurchaseInvoice|SalesInvoice $invoice, User $actor, int $direction, string $event): void
    {
        DB::transaction(function () use ($return, $invoice, $actor, $direction, $event) {
            $locked = $return->newQuery()->whereKey($return->id)->lockForUpdate()->firstOrFail();
            $allowedInvoiceStatuses = [1, 2, 3, '1', '2', '3', 'posted', 'partial', 'paid', 'sent'];
            if ((int) $locked->status !== 1 || ! in_array($invoice->status, $allowedInvoiceStatuses, false)) {
                throw new RuntimeException('Only approved returns for posted invoices can be completed.');
            }

            $warehouse = null;
            $invoiceLines = $invoice->items()->get()->groupBy('product_id');

            foreach ($locked->items()->get() as $line) {
                if (! $line->product_id) {
                    throw new RuntimeException('Return line must reference a canonical product or service.');
                }

                $original = InventoryQuantity::zero();
                foreach ($invoiceLines->get($line->product_id, collect()) as $invoiceLine) {
                    $original = $original->add((string) $invoiceLine->quantity);
                }

                $returned = InventoryQuantity::of((string) $line->quantity);
                if ($returned->greaterThan($original)) {
                    throw new RuntimeException('A return quantity exceeds the original invoice quantity.');
                }

                $product = ProductServiceItem::where('organization_id', $locked->organization_id)
                    ->where('workspace_id', $locked->workspace_id)
                    ->findOrFail($line->product_id);

                if ($product->type === 'product') {
                    $warehouse ??= Warehouse::where('organization_id', $locked->organization_id)
                        ->where('workspace_id', $locked->workspace_id)
                        ->findOrFail($invoice->warehouse_id);

                    $signed = $direction > 0 ? $returned : $returned->negate();
                    $this->stock->adjust(
                        $product,
                        $warehouse,
                        $signed,
                        $event.' '.$locked->return_id,
                        $actor,
                        $direction > 0 ? 'sales_return_received' : 'purchase_return_issued',
                        $locked,
                        (int) $line->id,
                    );
                }
            }

            $locked->update(['status' => 2]);
            if ($locked instanceof SalesInvoiceReturn && $invoice instanceof SalesInvoice) {
                $this->accounting->postSalesReturn($locked, $invoice, $actor);
            } elseif ($locked instanceof PurchaseReturn && $invoice instanceof PurchaseInvoice) {
                $this->accounting->postPurchaseReturn($locked, $invoice, $actor);
            }

            $this->audit->log($actor->id, $locked->organization_id, $locked->workspace_id, $event, $locked->getMorphClass(), (string) $locked->id, [
                'return_id' => $locked->return_id,
                'total_amount' => $locked->total_amount,
                'accounting_adjustment_created' => true,
            ], critical: true);
        });
    }
}

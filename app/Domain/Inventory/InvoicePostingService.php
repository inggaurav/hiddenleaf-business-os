<?php

namespace App\Domain\Inventory;

use App\Models\ProductServiceItem;
use App\Models\PurchaseInvoice;
use App\Models\SalesInvoice;
use App\Models\User;
use App\Models\Warehouse;
use HiddenLeaf\Kernel\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class InvoicePostingService
{
    public function __construct(private readonly StockAdjustmentService $stock, private readonly AuditLogger $audit) {}

    public function postPurchase(PurchaseInvoice $invoice, User $actor): void
    {
        $this->post($invoice, $actor, 1, 'purchase.posted');
    }

    public function postSale(SalesInvoice $invoice, User $actor): void
    {
        $this->post($invoice, $actor, -1, 'sale.posted');
    }

    private function post(PurchaseInvoice|SalesInvoice $invoice, User $actor, int $direction, string $event): void
    {
        DB::transaction(function () use ($invoice, $actor, $direction, $event) {
            $locked = $invoice->newQuery()->whereKey($invoice->id)->lockForUpdate()->firstOrFail();
            if ((int) $locked->status !== 0) {
                throw new RuntimeException('Only draft invoices can be posted.');
            }
            $warehouse = null;

            foreach ($locked->items()->get() as $line) {
                if (! $line->product_id) {
                    throw new RuntimeException('Every invoice line must reference a canonical product or service.');
                }
                $product = ProductServiceItem::query()
                    ->where('organization_id', $locked->organization_id)
                    ->where('workspace_id', $locked->workspace_id)
                    ->findOrFail($line->product_id);
                if ($product->type === 'product') {
                    $warehouse ??= Warehouse::query()
                        ->where('organization_id', $locked->organization_id)
                        ->where('workspace_id', $locked->workspace_id)
                        ->findOrFail($locked->warehouse_id);
                    $this->stock->adjust(
                        $product,
                        $warehouse,
                        $direction * (float) $line->quantity,
                        $event.' '.$locked->invoice_id,
                        $actor,
                        $direction > 0 ? 'purchase_received' : 'sale_issued',
                        $locked,
                    );
                }
            }

            $locked->update(['status' => 1]);
            $this->audit->log($actor->id, $locked->organization_id, $locked->workspace_id, $event, $locked->getMorphClass(), (string) $locked->id, [
                'invoice_id' => $locked->invoice_id,
                'total_amount' => $locked->total_amount,
            ], critical: true);
        });
    }
}

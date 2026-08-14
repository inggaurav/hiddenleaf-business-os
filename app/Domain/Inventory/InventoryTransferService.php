<?php

namespace App\Domain\Inventory;

use App\Models\ProductServiceItem;
use App\Models\Transfer;
use App\Models\User;
use App\Models\Warehouse;
use HiddenLeaf\Kernel\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class InventoryTransferService
{
    public function __construct(
        private readonly StockAdjustmentService $stock,
        private readonly AuditLogger $audit,
    ) {}

    public function transfer(
        Warehouse $from,
        Warehouse $to,
        ProductServiceItem $product,
        InventoryQuantity|string|int $quantity,
        string $date,
        User $actor,
    ): Transfer {
        $qty = InventoryQuantity::of($quantity);
        if ($from->is($to) || ! $qty->isPositive()) {
            throw new RuntimeException('A transfer requires different warehouses and a positive quantity.');
        }

        foreach ([$to, $product] as $resource) {
            if ((int) $resource->organization_id !== (int) $from->organization_id || (int) $resource->workspace_id !== (int) $from->workspace_id) {
                throw new RuntimeException('All transfer resources must belong to the same tenant.');
            }
        }

        return DB::transaction(function () use ($from, $to, $product, $qty, $date, $actor) {
            $transfer = Transfer::create([
                'transfer_number' => 'TRF-'.Str::upper((string) Str::ulid()),
                'from_warehouse' => $from->id,
                'to_warehouse' => $to->id,
                'product_id' => $product->id,
                'quantity' => $qty->toStorageString(),
                'date' => $date,
                'status' => 'completed',
                'processed_at' => now(),
                'processed_by' => $actor->id,
                'organization_id' => $from->organization_id,
                'workspace_id' => $from->workspace_id,
                'created_by' => $actor->id,
            ]);

            $this->stock->adjust(
                $product,
                $from,
                $qty->negate(),
                'Transfer #'.$transfer->transfer_number.' to '.$to->name,
                $actor,
                'transfer_out',
                $transfer,
                (int) $transfer->id,
            );
            $this->stock->adjust(
                $product,
                $to,
                $qty,
                'Transfer #'.$transfer->transfer_number.' from '.$from->name,
                $actor,
                'transfer_in',
                $transfer,
                (int) $transfer->id,
            );

            $this->audit->log($actor->id, $from->organization_id, $from->workspace_id, 'inventory.transferred', 'transfer', (string) $transfer->id, [
                'transfer_number' => $transfer->transfer_number,
                'product_id' => $product->id,
                'from_warehouse_id' => $from->id,
                'to_warehouse_id' => $to->id,
                'quantity' => $qty->toStorageString(),
            ], critical: true);

            return $transfer;
        });
    }
}

<?php

namespace App\Domain\Inventory;

use App\Models\ProductServiceItem;
use App\Models\Transfer;
use App\Models\User;
use App\Models\Warehouse;
use HiddenLeaf\Kernel\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class InventoryTransferService
{
    public function __construct(private readonly StockAdjustmentService $stock, private readonly AuditLogger $audit) {}

    public function transfer(Warehouse $from, Warehouse $to, ProductServiceItem $product, float $quantity, string $date, User $actor): Transfer
    {
        if ($from->is($to) || $quantity <= 0) {
            throw new RuntimeException('A transfer requires different warehouses and a positive quantity.');
        }
        foreach ([$to, $product] as $resource) {
            if ((int) $resource->organization_id !== (int) $from->organization_id || (int) $resource->workspace_id !== (int) $from->workspace_id) {
                throw new RuntimeException('All transfer resources must belong to the same tenant.');
            }
        }

        return DB::transaction(function () use ($from, $to, $product, $quantity, $date, $actor) {
            $transfer = Transfer::create([
                'transfer_number' => 'TRF-'.now()->timestamp.'-'.rand(100, 999),
                'from_warehouse' => $from->id,
                'to_warehouse' => $to->id,
                'product_id' => $product->id,
                'quantity' => $quantity,
                'date' => $date,
                'status' => 'completed',
                'processed_at' => now(),
                'processed_by' => $actor->id,
                'organization_id' => $from->organization_id,
                'workspace_id' => $from->workspace_id,
                'created_by' => $actor->id,
            ]);
            $this->stock->adjust($product, $from, -$quantity, 'Transfer #'.$transfer->transfer_number.' to '.$to->name, $actor, 'transfer_out', $transfer);
            $this->stock->adjust($product, $to, $quantity, 'Transfer #'.$transfer->transfer_number.' from '.$from->name, $actor, 'transfer_in', $transfer);
            $this->audit->log($actor->id, $from->organization_id, $from->workspace_id, 'inventory.transferred', 'transfer', (string) $transfer->id, [
                'transfer_number' => $transfer->transfer_number,
                'product_id' => $product->id,
                'from_warehouse_id' => $from->id,
                'to_warehouse_id' => $to->id,
                'quantity' => $quantity,
            ], critical: true);

            return $transfer;
        });
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockMovement extends Model
{
    protected $fillable = ['organization_id', 'workspace_id', 'warehouse_id', 'product_id', 'type', 'quantity', 'balance_after', 'reference_type', 'reference_id', 'reason', 'created_by'];

    protected function casts(): array
    {
        return ['quantity' => 'decimal:2', 'balance_after' => 'decimal:2'];
    }
}

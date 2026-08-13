<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class PosOrder extends Model
{
    protected $fillable = ['organization_id', 'workspace_id', 'session_id', 'receipt_number', 'customer_name', 'customer_email', 'subtotal', 'tax_total', 'discount_total', 'grand_total', 'paid_amount', 'change_amount', 'payment_method', 'status', 'created_by'];

    protected function casts(): array
    {
        return ['subtotal' => 'decimal:2', 'tax_total' => 'decimal:2', 'discount_total' => 'decimal:2', 'grand_total' => 'decimal:2', 'paid_amount' => 'decimal:2', 'change_amount' => 'decimal:2'];
    }

    public function items()
    {
        return $this->hasMany(PosOrderItem::class, 'order_id');
    }

    public function scopeForWorkspace(Builder $q, int $org, int $ws): Builder
    {
        return $q->where('organization_id', $org)->where('workspace_id', $ws);
    }
}

<?php

namespace App\Models\POS;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\User;

class PosSale extends Model
{
    protected $table = 'pos_sales';

    protected $fillable = [
        'organization_id', 'workspace_id', 'sale_number', 'billing_counter_id',
        'warehouse_id', 'customer_id', 'cashier_id', 'subtotal', 'tax_amount',
        'discount_amount', 'total', 'payment_method', 'payment_reference',
        'status', 'notes', 'idempotency_key', 'posted_at', 'created_by',
    ];

    protected $casts = [
        'posted_at' => 'datetime',
        'subtotal' => 'string',
        'tax_amount' => 'string',
        'discount_amount' => 'string',
        'total' => 'string',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(PosSaleItem::class);
    }

    public function returns(): HasMany
    {
        return $this->hasMany(PosReturn::class);
    }

    public function cashier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cashier_id');
    }

    public function counter(): BelongsTo
    {
        return $this->belongsTo(BillingCounter::class, 'billing_counter_id');
    }
}

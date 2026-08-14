<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SalesInvoiceReturnItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'sales_invoice_return_id',
        'product_id',
        'item_name',
        'quantity',
        'price',
    ];

    protected $casts = [
        'price' => 'decimal:2',
    ];

    public function return(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(SalesInvoiceReturn::class, 'sales_invoice_return_id');
    }
}

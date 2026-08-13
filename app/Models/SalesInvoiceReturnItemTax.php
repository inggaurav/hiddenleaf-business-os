<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SalesInvoiceReturnItemTax extends Model
{
    use HasFactory;

    protected $fillable = [
        'sales_invoice_return_id',
        'item_id',
        'tax_name',
        'tax_rate',
        'amount',
    ];

    protected $casts = [
        'tax_rate' => 'decimal:2',
        'amount' => 'decimal:2',
    ];
}

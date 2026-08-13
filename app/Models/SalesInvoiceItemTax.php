<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SalesInvoiceItemTax extends Model
{
    use HasFactory;

    protected $fillable = [
        'invoice_id',
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

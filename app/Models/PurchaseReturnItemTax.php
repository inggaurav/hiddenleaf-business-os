<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PurchaseReturnItemTax extends Model
{
    use HasFactory;

    protected $fillable = [
        'purchase_return_id',
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

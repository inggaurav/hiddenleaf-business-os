<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SalesInvoiceReturn extends Model
{
    use HasFactory;

    protected $fillable = [
        'return_id',
        'customer_id',
        'sales_invoice_id',
        'date',
        'total_amount',
        'status',
        'organization_id',
        'workspace_id',
        'created_by',
    ];

    protected $casts = [
        'date' => 'date',
        'total_amount' => 'decimal:2',
    ];

    public function items()
    {
        return $this->hasMany(SalesInvoiceReturnItem::class, 'sales_invoice_return_id');
    }
}

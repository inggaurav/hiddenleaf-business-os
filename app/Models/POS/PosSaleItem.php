<?php

namespace App\Models\POS;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PosSaleItem extends Model
{
    protected $table = 'pos_sale_items';

    protected $fillable = [
        'pos_sale_id', 'product_id', 'product_name', 'sku',
        'quantity', 'unit_price', 'tax_rate', 'tax_amount',
        'discount_amount', 'line_total', 'type',
    ];

    protected $casts = [
        'quantity' => 'string',
        'unit_price' => 'string',
        'tax_rate' => 'string',
        'tax_amount' => 'string',
        'discount_amount' => 'string',
        'line_total' => 'string',
    ];

    public function sale(): BelongsTo
    {
        return $this->belongsTo(PosSale::class, 'pos_sale_id');
    }
}

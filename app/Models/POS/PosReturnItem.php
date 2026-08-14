<?php

namespace App\Models\POS;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PosReturnItem extends Model
{
    protected $table = 'pos_return_items';

    protected $fillable = [
        'pos_return_id', 'pos_sale_item_id', 'product_id', 'quantity', 'refund_amount',
    ];

    protected $casts = [
        'quantity' => 'string',
        'refund_amount' => 'string',
    ];

    public function return(): BelongsTo
    {
        return $this->belongsTo(PosReturn::class, 'pos_return_id');
    }

    public function saleItem(): BelongsTo
    {
        return $this->belongsTo(PosSaleItem::class, 'pos_sale_item_id');
    }
}

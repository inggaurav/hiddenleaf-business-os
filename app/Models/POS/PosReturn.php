<?php

namespace App\Models\POS;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PosReturn extends Model
{
    protected $table = 'pos_returns';

    protected $fillable = [
        'organization_id', 'workspace_id', 'pos_sale_id', 'return_number',
        'status', 'reason', 'refund_amount', 'refund_method',
        'processed_by', 'processed_at', 'created_by',
    ];

    protected $casts = [
        'processed_at' => 'datetime',
        'refund_amount' => 'string',
    ];

    public function sale(): BelongsTo
    {
        return $this->belongsTo(PosSale::class, 'pos_sale_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PosReturnItem::class);
    }
}

<?php

namespace App\Models\POS;

use Illuminate\Database\Eloquent\Model;

class PosDiscount extends Model
{
    protected $table = 'pos_discounts';

    protected $fillable = [
        'organization_id', 'workspace_id', 'name', 'type', 'value',
        'min_order_amount', 'max_discount_amount',
        'valid_from', 'valid_until', 'is_active', 'created_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'valid_from' => 'datetime',
        'valid_until' => 'datetime',
        'value' => 'string',
        'min_order_amount' => 'string',
        'max_discount_amount' => 'string',
    ];
}

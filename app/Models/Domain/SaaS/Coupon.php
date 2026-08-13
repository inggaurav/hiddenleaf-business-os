<?php

namespace App\Models\Domain\SaaS;

use Illuminate\Database\Eloquent\Model;

class Coupon extends Model
{
    protected $fillable = [
        'code', 'discount_type', 'discount', 'limit', 'used', 'expiry_date'
    ];

    protected $casts = [
        'expiry_date' => 'date',
        'discount' => 'decimal:2',
    ];
}

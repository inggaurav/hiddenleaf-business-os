<?php

namespace App\Models\Domain\SaaS;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    protected $fillable = [
        'order_id', 'organization_id', 'plan_id', 'price', 'discount_amount', 'currency', 'payment_status', 'payment_type', 'receipt',
    ];

    public function plan()
    {
        return $this->belongsTo(Plan::class);
    }
}

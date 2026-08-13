<?php

namespace App\Models\Domain\SaaS;

use Illuminate\Database\Eloquent\Model;

class UserCoupon extends Model
{
    protected $fillable = [
        'user_id', 'coupon_id', 'order_id'
    ];

    public function coupon()
    {
        return $this->belongsTo(Coupon::class);
    }
}

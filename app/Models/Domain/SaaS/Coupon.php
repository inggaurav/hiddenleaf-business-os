<?php

namespace App\Models\Domain\SaaS;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Coupon extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'code',
        'discount',
        'type',
        'limit',
        'limit_per_user',
        'used',
        'minimum_spend',
        'maximum_spend',
        'expiry_date',
        'included_module',
        'excluded_module',
        'status',
        'created_by',
    ];

    protected $casts = [
        'discount' => 'decimal:2',
        'minimum_spend' => 'decimal:2',
        'maximum_spend' => 'decimal:2',
        'expiry_date' => 'date',
        'status' => 'boolean',
    ];

    public function userCoupons()
    {
        return $this->hasMany(UserCoupon::class, 'coupon_id');
    }

    public function isValidFor(float $amount, int $userId): array
    {
        if (! $this->status) {
            return ['valid' => false, 'message' => 'Coupon is inactive.'];
        }

        if ($this->expiry_date && $this->expiry_date->isPast()) {
            return ['valid' => false, 'message' => 'Coupon has expired.'];
        }

        if ($this->limit !== null && $this->used >= $this->limit) {
            return ['valid' => false, 'message' => 'Coupon usage limit reached.'];
        }

        if ($this->limit_per_user !== null) {
            $userUsage = $this->userCoupons()->where('user_id', $userId)->count();
            if ($userUsage >= $this->limit_per_user) {
                return ['valid' => false, 'message' => 'You have reached the usage limit for this coupon.'];
            }
        }

        if ($this->minimum_spend !== null && $amount < (float) $this->minimum_spend) {
            return ['valid' => false, 'message' => "Minimum spend of {$this->minimum_spend} required."];
        }

        if ($this->maximum_spend !== null && $amount > (float) $this->maximum_spend) {
            return ['valid' => false, 'message' => "Maximum spend limit is {$this->maximum_spend}."];
        }

        $discountAmount = ($this->type === 'percentage')
            ? ($amount * ((float) $this->discount / 100))
            : (float) $this->discount;

        $discountAmount = min($discountAmount, $amount);
        $finalAmount = max(0, $amount - $discountAmount);

        return [
            'valid' => true,
            'coupon' => $this,
            'discount_amount' => round($discountAmount, 2),
            'final_amount' => round($finalAmount, 2),
        ];
    }
}

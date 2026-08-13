<?php

namespace App\Domain\SaaS\Services;

use App\Models\Coupon;
use App\Models\Order;
use App\Models\Organization;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Models\UserActiveModule;
use App\Models\UserCoupon;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SaaSSubscriptionService
{
    /**
     * Assign a plan to a user and their organization.
     */
    public function assignPlan(int $planId, string $duration = 'Month', array|string $modules = [], array $counters = [], ?int $userId = null): array
    {
        $userId = $userId ?: auth()->id();
        $user = User::find($userId);

        if (! $user) {
            return ['is_success' => false, 'error' => 'User not found.'];
        }

        $plan = Plan::find($planId);
        if (! $plan) {
            return ['is_success' => false, 'error' => 'Plan not found.'];
        }

        return DB::transaction(function () use ($user, $plan, $duration, $modules, $counters) {
            $now = Carbon::now();
            $expireDate = null;

            if ($duration === 'Trial') {
                $days = $plan->trial_days ?: 14;
                $expireDate = $now->copy()->addDays($days);
                $user->is_trial_done = 1;
            } elseif ($duration === 'Month') {
                $expireDate = $now->copy()->addMonth();
            } elseif ($duration === 'Year') {
                $expireDate = $now->copy()->addYear();
            } elseif ($duration === 'Lifetime') {
                $expireDate = null;
            } else {
                $expireDate = $now->copy()->addMonth();
            }

            $user->active_plan = $plan->id;
            $user->plan_expire_date = $expireDate?->toDateString();
            $user->user_counter = $counters['user_counter'] ?? $plan->number_of_users;
            $user->storage_limit = isset($counters['storage_limit']) ? (int) $counters['storage_limit'] * 1024 * 1024 : $plan->storage_limit;
            $user->save();

            // Link to active organization if user has one
            $orgId = session('active_organization_id');
            if (! $orgId && $user->organizations()->exists()) {
                $orgId = $user->organizations()->first()->id;
            }

            if ($orgId) {
                $organization = Organization::find($orgId);
                if ($organization) {
                    Subscription::updateOrCreate(
                        ['organization_id' => $organization->id],
                        [
                            'plan_id' => $plan->id,
                            'status' => 'active',
                            'starts_at' => $now,
                            'expires_at' => $expireDate,
                        ]
                    );
                }
            }

            // Sync user active modules
            if (is_string($modules)) {
                $modules = array_filter(explode(',', $modules));
            }
            if (empty($modules) && ! empty($plan->modules)) {
                $modules = is_array($plan->modules) ? $plan->modules : json_decode($plan->modules, true);
            }

            if (! empty($modules)) {
                foreach ($modules as $modName) {
                    $modName = trim($modName);
                    if ($modName) {
                        UserActiveModule::firstOrCreate([
                            'user_id' => $user->id,
                            'module' => $modName,
                        ]);
                    }
                }
            }

            return [
                'is_success' => true,
                'plan' => $plan,
                'expire_date' => $expireDate,
            ];
        });
    }

    /**
     * Validate and apply a coupon discount.
     */
    public function applyCouponDiscount(string $couponCode, float $totalAmount, ?int $userId = null): array
    {
        $userId = $userId ?: auth()->id();
        $coupon = Coupon::where('code', $couponCode)->where('status', true)->first();

        if (! $coupon) {
            return ['valid' => false, 'message' => 'Invalid or inactive coupon code.'];
        }

        return $coupon->isValidFor($totalAmount, $userId);
    }

    /**
     * Record coupon usage.
     */
    public function recordCouponUsage(int $couponId, int $userId, ?string $orderId = null): void
    {
        $coupon = Coupon::find($couponId);
        if ($coupon) {
            $coupon->increment('used');
            UserCoupon::create([
                'user_id' => $userId,
                'coupon_id' => $coupon->id,
                'order_id' => $orderId,
            ]);
        }
    }

    /**
     * Create an Order record.
     */
    public function createOrder(array $data): Order
    {
        $orderId = $data['order_id'] ?? strtoupper(Str::random(12));

        return Order::create([
            'order_id' => $orderId,
            'name' => $data['name'] ?? null,
            'email' => $data['email'] ?? null,
            'card_number' => $data['card_number'] ?? null,
            'card_exp_month' => $data['card_exp_month'] ?? null,
            'card_exp_year' => $data['card_exp_year'] ?? null,
            'plan_name' => $data['plan_name'] ?? null,
            'plan_id' => $data['plan_id'] ?? null,
            'price' => $data['price'] ?? 0,
            'discount_amount' => $data['discount_amount'] ?? 0,
            'currency' => $data['currency'] ?? 'USD',
            'txn_id' => $data['txn_id'] ?? '',
            'payment_type' => $data['payment_type'] ?? 'bank_transfer',
            'payment_status' => $data['payment_status'] ?? 'succeeded',
            'receipt' => $data['receipt'] ?? null,
            'organization_id' => $data['organization_id'] ?? session('active_organization_id'),
            'user_id' => $data['user_id'] ?? auth()->id(),
            'created_by' => $data['created_by'] ?? auth()->id(),
        ]);
    }
}

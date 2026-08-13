<?php

namespace App\Http\Controllers;

use App\Models\BankTransferPayment;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class BankTransferPaymentController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();

        $requests = BankTransferPayment::with(['user'])
            ->when(! $user->isSuperAdmin(), function ($q) use ($user) {
                $q->where('user_id', $user->id);
            })
            ->when($request->order_number, fn ($q) => $q->where('order_id', 'like', "%{$request->order_number}%"))
            ->when($request->status, fn ($q) => $q->where('status', $request->status))
            ->when($request->price_min, fn ($q) => $q->where('price', '>=', $request->price_min))
            ->when($request->price_max, fn ($q) => $q->where('price', '<=', $request->price_max))
            ->latest()
            ->paginate($request->input('per_page', 10))
            ->withQueryString();

        foreach ($requests as $item) {
            $requestData = json_decode($item->request, true);
            if (isset($requestData['plan_id'])) {
                $item->plan = Plan::find($requestData['plan_id']);
            }
        }

        return Inertia::render('BankTransfer/Index', [
            'requests' => $requests,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'plan_id' => 'required|exists:plans,id',
            'time_period' => 'nullable|in:Month,Year',
            'user_counter_input' => 'nullable|integer|min:0',
            'storage_counter_input' => 'nullable|integer|min:0',
            'coupon_code' => 'nullable|string',
            'payment_receipt' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:10240',
        ]);

        $plan = Plan::findOrFail($validated['plan_id']);
        $duration = $validated['time_period'] ?? 'Month';
        $userCounter = $validated['user_counter_input'] ?? 0;
        $storageCounter = $validated['storage_counter_input'] ?? 0;

        $planPrice = ($duration === 'Year') ? (float) $plan->package_price_yearly : (float) $plan->package_price_monthly;
        $userPrice = $userCounter * (($duration === 'Year') ? (float) $plan->price_per_user_yearly : (float) $plan->price_per_user_monthly);
        $storagePrice = $storageCounter * (($duration === 'Year') ? (float) $plan->price_per_storage_yearly : (float) $plan->price_per_storage_monthly);

        $price = $planPrice + $userPrice + $storagePrice;

        if (! empty($validated['coupon_code'])) {
            $couponResult = applyCouponDiscount($validated['coupon_code'], $price, Auth::id());
            if ($couponResult['valid']) {
                $price = $couponResult['final_amount'];
            }
        }

        $receiptUrl = null;
        if ($request->hasFile('payment_receipt')) {
            $path = $request->file('payment_receipt')->store('bank_transfers', 'public');
            $receiptUrl = Storage::url($path);
        }

        $orderID = strtoupper(substr(uniqid('BT-'), -12));

        $payload = $request->except(['_token', '_method', 'payment_receipt']);

        BankTransferPayment::create([
            'order_id' => $orderID,
            'user_id' => Auth::id(),
            'request' => json_encode($payload),
            'status' => 'pending',
            'type' => 'plan',
            'price' => $price,
            'price_currency' => admin_setting('defaultCurrency', 'USD'),
            'attachment' => $receiptUrl,
            'created_by' => Auth::id(),
        ]);

        return redirect()->route('plans.index')->with('success', 'Bank transfer request submitted successfully. Pending administrator review.');
    }

    public function update(Request $request, int $id)
    {
        $user = Auth::user();
        if (! $user->isSuperAdmin()) {
            abort(403, 'Unauthorized');
        }

        $validated = $request->validate([
            'status' => 'required|in:approved,rejected',
        ]);

        $payment = BankTransferPayment::findOrFail($id);

        if ($payment->status !== 'pending') {
            return back()->with('error', 'Request is already resolved.');
        }

        $payment->status = $validated['status'];
        $payment->save();

        if ($validated['status'] === 'approved') {
            $requestPayload = json_decode($payment->request, true);
            $plan = Plan::find($requestPayload['plan_id'] ?? null);

            if ($plan) {
                $duration = $requestPayload['time_period'] ?? 'Month';
                $counter = [
                    'user_counter' => $requestPayload['user_counter_input'] ?? $plan->number_of_users,
                    'storage_limit' => $requestPayload['storage_counter_input'] ?? ($plan->storage_limit / (1024 * 1024)),
                ];

                $assignResult = assignPlan($plan->id, $duration, $plan->modules ?? [], $counter, $payment->user_id);

                if ($assignResult['is_success']) {
                    $orderUser = User::find($payment->user_id);

                    Order::create([
                        'order_id' => $payment->order_id,
                        'name' => $orderUser?->name,
                        'email' => $orderUser?->email,
                        'plan_name' => $plan->name,
                        'plan_id' => $plan->id,
                        'price' => $payment->price,
                        'currency' => $payment->price_currency,
                        'payment_type' => 'Bank Transfer',
                        'payment_status' => 'succeeded',
                        'receipt' => $payment->attachment,
                        'user_id' => $payment->user_id,
                        'created_by' => $payment->user_id,
                    ]);

                    if (! empty($requestPayload['coupon_code'])) {
                        $coupon = Coupon::where('code', $requestPayload['coupon_code'])->first();
                        if ($coupon) {
                            recordCouponUsage($coupon->id, $payment->user_id, $payment->order_id);
                        }
                    }

                    return back()->with('success', 'Bank transfer approved and subscription activated.');
                }
            }

            return back()->with('error', 'Failed to activate plan.');
        }

        return back()->with('success', 'Bank transfer request rejected.');
    }

    public function reject(BankTransferPayment $payment)
    {
        $user = Auth::user();
        if (! $user->isSuperAdmin()) {
            abort(403, 'Unauthorized');
        }

        $payment->update(['status' => 'rejected']);

        return back()->with('success', 'Bank transfer request rejected.');
    }

    public function destroy(BankTransferPayment $payment)
    {
        $user = Auth::user();

        if (! $user->isSuperAdmin() && $payment->user_id !== $user->id) {
            abort(403, 'Unauthorized');
        }

        if ($payment->status !== 'pending' && ! $user->isSuperAdmin()) {
            return back()->with('error', 'Only pending requests can be deleted.');
        }

        $payment->delete();

        return back()->with('success', 'Bank transfer request deleted.');
    }
}

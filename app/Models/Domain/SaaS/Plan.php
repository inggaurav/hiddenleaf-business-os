<?php

namespace App\Models\Domain\SaaS;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Plan extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'package_price_monthly',
        'package_price_yearly',
        'price_per_user_monthly',
        'price_per_user_yearly',
        'price_per_storage_monthly',
        'price_per_storage_yearly',
        'number_of_users',
        'storage_limit',
        'workspace_limit',
        'modules',
        'trial',
        'trial_days',
        'free_plan',
        'status',
        'custom_plan',
        'created_by',
    ];

    protected $casts = [
        'modules' => 'array',
        'trial' => 'boolean',
        'free_plan' => 'boolean',
        'status' => 'boolean',
        'custom_plan' => 'boolean',
        'package_price_monthly' => 'decimal:2',
        'package_price_yearly' => 'decimal:2',
    ];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function orders()
    {
        return $this->hasMany(Order::class, 'plan_id');
    }

    public function subscriptions()
    {
        return $this->hasMany(Subscription::class, 'plan_id');
    }

    public function hasModule(string $module): bool
    {
        return in_array($module, $this->modules ?? []);
    }
}

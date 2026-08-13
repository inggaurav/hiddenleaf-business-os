<?php

namespace App\Models\Domain\SaaS;

use Illuminate\Database\Eloquent\Model;

class Plan extends Model
{
    protected $fillable = [
        'name', 'price_monthly', 'price_yearly', 'max_users', 'max_storage', 'modules', 'trial_days'
    ];

    protected $casts = [
        'modules' => 'array',
        'price_monthly' => 'decimal:2',
        'price_yearly' => 'decimal:2',
    ];
}

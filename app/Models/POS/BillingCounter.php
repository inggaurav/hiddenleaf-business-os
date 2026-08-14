<?php

namespace App\Models\POS;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class BillingCounter extends Model
{
    use SoftDeletes;

    protected $table = 'billing_counters';

    protected $fillable = [
        'organization_id', 'workspace_id', 'name', 'counter_number',
        'warehouse_id', 'is_active', 'created_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function sales()
    {
        return $this->hasMany(PosSale::class, 'billing_counter_id');
    }

    public function hasTransactions(): bool
    {
        return $this->sales()->exists();
    }
}

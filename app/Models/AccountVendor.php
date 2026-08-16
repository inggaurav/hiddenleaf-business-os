<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AccountVendor extends Model
{
    use HasFactory;

    protected $table = 'account_vendors';

    protected $fillable = [
        'organization_id',
        'workspace_id',
        'user_id',
        'name',
        'email',
        'contact',
        'tax_number',
        'billing_name',
        'billing_country',
        'billing_state',
        'billing_city',
        'billing_phone',
        'billing_zip',
        'billing_address',
        'shipping_name',
        'shipping_country',
        'shipping_state',
        'shipping_city',
        'shipping_phone',
        'shipping_zip',
        'shipping_address',
        'balance',
        'is_active',
        'created_by',
    ];

    protected $casts = [
        'balance' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function scopeForWorkspace(Builder $query, int $organizationId, int $workspaceId): Builder
    {
        return $query->where('organization_id', $organizationId)->where('workspace_id', $workspaceId);
    }

    public function purchaseInvoices()
    {
        return $this->hasMany(PurchaseInvoice::class, 'vendor_id');
    }

    public function portalUser()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function payments()
    {
        return $this->hasMany(VendorPayment::class, 'vendor_id');
    }

    public function expenses()
    {
        return $this->hasMany(AccountExpense::class, 'vendor_id');
    }

    public function debitNotes()
    {
        return $this->hasMany(AccountDebitNote::class, 'vendor_id');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VendorPayment extends Model
{
    use HasFactory;

    protected $table = 'vendor_payments';

    protected $fillable = [
        'organization_id', 'workspace_id', 'vendor_id', 'purchase_invoice_id', 'account_id',
        'amount', 'payment_date', 'payment_method', 'reference', 'description', 'receipt',
        'idempotency_key', 'request_fingerprint', 'status', 'voided_at', 'voided_by',
        'journal_entry_id', 'created_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'payment_date' => 'date',
        'voided_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope('active_financial_effect', fn (Builder $query) => $query->where($query->qualifyColumn('status'), '!=', 'void'));
    }

    public function scopeForWorkspace(Builder $query, int $organizationId, int $workspaceId): Builder
    {
        return $query->where('organization_id', $organizationId)->where('workspace_id', $workspaceId);
    }

    public function scopeIncludingVoided(Builder $query): Builder
    {
        return $query->withoutGlobalScope('active_financial_effect');
    }

    public function vendor()
    {
        return $this->belongsTo(AccountVendor::class, 'vendor_id');
    }

    public function purchaseInvoice()
    {
        return $this->belongsTo(PurchaseInvoice::class, 'purchase_invoice_id');
    }

    public function account()
    {
        return $this->belongsTo(LedgerAccount::class, 'account_id');
    }

    public function journalEntry()
    {
        return $this->belongsTo(JournalEntry::class, 'journal_entry_id');
    }
}

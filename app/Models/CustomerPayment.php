<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CustomerPayment extends Model
{
    use HasFactory;

    protected $table = 'customer_payments';

    protected $fillable = [
        'organization_id', 'workspace_id', 'customer_id', 'invoice_id', 'account_id',
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

    public function customer()
    {
        return $this->belongsTo(AccountCustomer::class, 'customer_id');
    }

    public function invoice()
    {
        return $this->belongsTo(SalesInvoice::class, 'invoice_id');
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

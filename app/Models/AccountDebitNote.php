<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AccountDebitNote extends Model
{
    use HasFactory;

    protected $table = 'account_debit_notes';

    protected $fillable = [
        'organization_id', 'workspace_id', 'purchase_invoice_id', 'vendor_id', 'amount',
        'date', 'description', 'source_type', 'source_id', 'status', 'journal_entry_id',
        'approved_at', 'approved_by', 'created_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'date' => 'date',
        'approved_at' => 'datetime',
    ];

    public function scopeForWorkspace(Builder $query, int $organizationId, int $workspaceId): Builder
    {
        return $query->where('organization_id', $organizationId)->where('workspace_id', $workspaceId);
    }

    public function vendor()
    {
        return $this->belongsTo(AccountVendor::class, 'vendor_id');
    }

    public function purchaseInvoice()
    {
        return $this->belongsTo(PurchaseInvoice::class, 'purchase_invoice_id');
    }

    public function journalEntry()
    {
        return $this->belongsTo(JournalEntry::class, 'journal_entry_id');
    }
}

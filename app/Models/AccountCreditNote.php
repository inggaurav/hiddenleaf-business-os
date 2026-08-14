<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AccountCreditNote extends Model
{
    use HasFactory;

    protected $table = 'account_credit_notes';

    protected $fillable = [
        'organization_id', 'workspace_id', 'invoice_id', 'customer_id', 'amount',
        'date', 'description', 'status', 'journal_entry_id', 'approved_at', 'approved_by', 'created_by',
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

    public function customer()
    {
        return $this->belongsTo(AccountCustomer::class, 'customer_id');
    }

    public function invoice()
    {
        return $this->belongsTo(SalesInvoice::class, 'invoice_id');
    }

    public function journalEntry()
    {
        return $this->belongsTo(JournalEntry::class, 'journal_entry_id');
    }
}

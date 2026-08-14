<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class AccountBankTransfer extends Model
{
    protected $fillable = [
        'organization_id', 'workspace_id', 'from_account_id', 'to_account_id',
        'journal_entry_id', 'amount', 'transfer_date', 'reference', 'status',
        'processed_at', 'processed_by', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'transfer_date' => 'date',
            'processed_at' => 'datetime',
        ];
    }

    public function scopeForWorkspace(Builder $query, int $organizationId, int $workspaceId): Builder
    {
        return $query->where('organization_id', $organizationId)->where('workspace_id', $workspaceId);
    }

    public function fromAccount()
    {
        return $this->belongsTo(LedgerAccount::class, 'from_account_id');
    }

    public function toAccount()
    {
        return $this->belongsTo(LedgerAccount::class, 'to_account_id');
    }

    public function journalEntry()
    {
        return $this->belongsTo(JournalEntry::class, 'journal_entry_id');
    }
}

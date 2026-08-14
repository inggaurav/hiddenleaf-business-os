<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AccountExpense extends Model
{
    use HasFactory;

    protected $table = 'account_expenses';

    protected $fillable = [
        'organization_id',
        'workspace_id',
        'vendor_id',
        'account_id',
        'category_id',
        'amount',
        'date',
        'payment_method',
        'reference',
        'description',
        'receipt',
        'journal_entry_id',
        'created_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'date' => 'date',
    ];

    public function scopeForWorkspace(Builder $query, int $organizationId, int $workspaceId): Builder
    {
        return $query->where('organization_id', $organizationId)->where('workspace_id', $workspaceId);
    }

    public function vendor()
    {
        return $this->belongsTo(AccountVendor::class, 'vendor_id');
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

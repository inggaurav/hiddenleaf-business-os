<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class AccountExpense extends Model
{
    use HasFactory;

    protected $table = 'account_expenses';

    protected $fillable = [
        'organization_id', 'workspace_id', 'vendor_id', 'account_id', 'category_id',
        'amount', 'date', 'payment_method', 'reference', 'description', 'status',
        'approved_at', 'approved_by', 'posted_at', 'posted_by', 'receipt',
        'journal_entry_id', 'created_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'date' => 'date',
        'approved_at' => 'datetime',
        'posted_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $expense) {
            if (! $expense->category_id) {
                return;
            }

            $valid = AccountTransactionCategory::query()
                ->whereKey($expense->category_id)
                ->where('organization_id', $expense->organization_id)
                ->where('workspace_id', $expense->workspace_id)
                ->where('type', 'expense')
                ->exists();

            if (! $valid) {
                throw ValidationException::withMessages([
                    'category_id' => 'Expense category must belong to the active workspace and be an expense category.',
                ]);
            }
        });
    }

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

    public function category()
    {
        return $this->belongsTo(AccountTransactionCategory::class, 'category_id');
    }

    public function journalEntry()
    {
        return $this->belongsTo(JournalEntry::class, 'journal_entry_id');
    }
}

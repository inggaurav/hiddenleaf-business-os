<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class AccountRevenue extends Model
{
    use HasFactory;

    protected $table = 'account_revenues';

    protected $fillable = [
        'organization_id', 'workspace_id', 'customer_id', 'account_id', 'category_id',
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
        static::saving(function (self $revenue) {
            if (! $revenue->category_id) {
                return;
            }

            $valid = AccountTransactionCategory::query()
                ->whereKey($revenue->category_id)
                ->where('organization_id', $revenue->organization_id)
                ->where('workspace_id', $revenue->workspace_id)
                ->where('type', 'revenue')
                ->exists();

            if (! $valid) {
                throw ValidationException::withMessages([
                    'category_id' => 'Revenue category must belong to the active workspace and be a revenue category.',
                ]);
            }
        });
    }

    public function scopeForWorkspace(Builder $query, int $organizationId, int $workspaceId): Builder
    {
        return $query->where('organization_id', $organizationId)->where('workspace_id', $workspaceId);
    }

    public function customer()
    {
        return $this->belongsTo(AccountCustomer::class, 'customer_id');
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

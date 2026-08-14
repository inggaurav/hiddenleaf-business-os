<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class LedgerAccount extends Model
{
    protected $fillable = [
        'organization_id', 'workspace_id', 'account_type_id', 'parent_id', 'code', 'name',
        'currency', 'is_bank', 'bank_name', 'account_holder', 'account_number', 'branch_name',
        'iban', 'swift_code', 'opening_balance', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_bank' => 'boolean',
            'is_active' => 'boolean',
            'opening_balance' => 'decimal:2',
        ];
    }

    public function type()
    {
        return $this->belongsTo(AccountType::class, 'account_type_id');
    }

    public function journalLines()
    {
        return $this->hasMany(JournalLine::class, 'ledger_account_id');
    }

    public function scopeForWorkspace(Builder $query, int $organizationId, int $workspaceId): Builder
    {
        return $query->where('organization_id', $organizationId)->where('workspace_id', $workspaceId);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class LedgerAccount extends Model
{
    protected $fillable = ['organization_id', 'workspace_id', 'account_type_id', 'parent_id', 'code', 'name', 'currency', 'is_bank', 'is_active'];

    protected function casts(): array
    {
        return ['is_bank' => 'boolean', 'is_active' => 'boolean'];
    }

    public function type()
    {
        return $this->belongsTo(AccountType::class, 'account_type_id');
    }

    public function journalLines()
    {
        return $this->hasMany(JournalLine::class);
    }

    public function scopeForWorkspace(Builder $query, int $organizationId, int $workspaceId): Builder
    {
        return $query->where('organization_id', $organizationId)->where('workspace_id', $workspaceId);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class AccountType extends Model
{
    protected $fillable = ['organization_id', 'workspace_id', 'name', 'classification', 'normal_balance'];

    public function accounts()
    {
        return $this->hasMany(LedgerAccount::class, 'account_type_id');
    }

    public function scopeForWorkspace(Builder $query, int $organizationId, int $workspaceId): Builder
    {
        return $query->where('organization_id', $organizationId)->where('workspace_id', $workspaceId);
    }
}

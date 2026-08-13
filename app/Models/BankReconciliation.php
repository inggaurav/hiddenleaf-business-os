<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BankReconciliation extends Model
{
    protected $fillable = ['organization_id', 'workspace_id', 'ledger_account_id', 'statement_date', 'statement_balance', 'ledger_balance', 'status', 'reconciled_by', 'reconciled_at'];

    protected function casts(): array
    {
        return ['statement_date' => 'date', 'statement_balance' => 'decimal:2', 'ledger_balance' => 'decimal:2', 'reconciled_at' => 'datetime'];
    }
}

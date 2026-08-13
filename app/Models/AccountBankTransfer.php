<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AccountBankTransfer extends Model
{
    protected $fillable = ['organization_id', 'workspace_id', 'from_account_id', 'to_account_id', 'journal_entry_id', 'amount', 'transfer_date', 'reference', 'created_by'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'transfer_date' => 'date'];
    }
}

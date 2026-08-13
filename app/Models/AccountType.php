<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AccountType extends Model
{
    protected $fillable = ['organization_id', 'workspace_id', 'name', 'classification', 'normal_balance'];
}

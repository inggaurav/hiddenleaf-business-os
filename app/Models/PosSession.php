<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PosSession extends Model
{
    protected $fillable = ['organization_id', 'workspace_id', 'register_id', 'opened_by', 'closed_by', 'opening_cash', 'closing_cash', 'expected_cash', 'variance', 'status', 'opened_at', 'closed_at'];

    protected function casts(): array
    {
        return ['opening_cash' => 'decimal:2', 'closing_cash' => 'decimal:2', 'expected_cash' => 'decimal:2', 'variance' => 'decimal:2', 'opened_at' => 'datetime', 'closed_at' => 'datetime'];
    }
}

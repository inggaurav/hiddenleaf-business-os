<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SalesProposal extends Model
{
    use HasFactory;

    protected $fillable = [
        'proposal_id',
        'customer_id',
        'issue_date',
        'type',
        'total_amount',
        'status',
        'organization_id',
        'workspace_id',
        'created_by',
    ];

    protected $casts = [
        'issue_date' => 'date',
        'total_amount' => 'decimal:2',
    ];

    public function items()
    {
        return $this->hasMany(SalesProposalItem::class, 'proposal_id');
    }
}

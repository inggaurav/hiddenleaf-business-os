<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SalesProposalItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'proposal_id',
        'product_id',
        'item_name',
        'quantity',
        'price',
        'tax',
        'discount',
        'description',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'tax' => 'decimal:2',
        'discount' => 'decimal:2',
    ];

    public function proposal()
    {
        return $this->belongsTo(SalesProposal::class, 'proposal_id');
    }

    public function taxes()
    {
        return $this->hasMany(SalesProposalItemTax::class, 'item_id');
    }
}

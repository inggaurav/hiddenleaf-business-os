<?php

namespace App\Models;

use App\Models\Domain\SaaS\Plan;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BankTransferPayment extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'user_id',
        'request',
        'status',
        'type',
        'price',
        'price_currency',
        'attachment',
        'created_by',
    ];

    protected $casts = [
        'price' => 'decimal:2',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function plan()
    {
        $requestData = json_decode($this->request, true);
        if (isset($requestData['plan_id'])) {
            return Plan::find($requestData['plan_id']);
        }

        return null;
    }
}

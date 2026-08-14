<?php

namespace App\Models\POS;

use Illuminate\Database\Eloquent\Model;

class PosIdempotencyKey extends Model
{
    protected $table = 'pos_idempotency_keys';

    protected $fillable = [
        'organization_id',
        'workspace_id',
        'idempotency_key',
        'request_fingerprint',
        'pos_sale_id',
    ];

    public function sale()
    {
        return $this->belongsTo(PosSale::class, 'pos_sale_id');
    }
}

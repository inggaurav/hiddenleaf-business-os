<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WebhookDelivery extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['id', 'webhook_id', 'event', 'idempotency_key', 'payload', 'attempts', 'status', 'response_status', 'response_body', 'error', 'delivered_at', 'next_attempt_at'];

    protected function casts(): array
    {
        return ['payload' => 'array', 'delivered_at' => 'datetime', 'next_attempt_at' => 'datetime'];
    }

    public function webhook()
    {
        return $this->belongsTo(Webhook::class);
    }
}

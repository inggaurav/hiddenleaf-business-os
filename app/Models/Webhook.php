<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Webhook extends Model
{
    use HasFactory;

    protected $fillable = ['organization_id', 'workspace_id', 'url', 'event', 'events', 'method', 'secret', 'is_active', 'timeout_seconds', 'created_by'];

    protected function casts(): array
    {
        return ['secret' => 'encrypted', 'events' => 'array', 'is_active' => 'boolean'];
    }

    public function deliveries()
    {
        return $this->hasMany(WebhookDelivery::class);
    }
}

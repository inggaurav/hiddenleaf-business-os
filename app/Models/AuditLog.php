<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    public $timestamps = false;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'actor_id',
        'organization_id',
        'workspace_id',
        'action',
        'entity_type',
        'entity_id',
        'request_id',
        'ip',
        'user_agent',
        'metadata',
        'created_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'created_at' => 'datetime',
    ];

    public function actor()
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function workspace()
    {
        return $this->belongsTo(Workspace::class);
    }
}

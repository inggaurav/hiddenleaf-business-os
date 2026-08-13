<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Organization extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'slug',
        'owner_id',
        'plan_id',
        'plan_expires_at',
        'is_active',
        'settings',
    ];

    protected $casts = [
        'plan_expires_at' => 'datetime',
        'is_active' => 'boolean',
        'settings' => 'array',
    ];

    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function workspaces()
    {
        return $this->hasMany(Workspace::class);
    }

    public function members()
    {
        return $this->belongsToMany(User::class, 'organization_memberships')
                    ->withPivot('role')
                    ->withTimestamps();
    }
}

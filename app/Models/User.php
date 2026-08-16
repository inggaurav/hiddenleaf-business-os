<?php

namespace App\Models;

use App\Services\PermissionService;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'is_active',
        'avatar',
        'phone',
        'lang',
        'theme',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === 'super_admin';
    }

    public function canInWorkspace(string $permission, Workspace $workspace): bool
    {
        return app(PermissionService::class)->allows($this, $workspace, $permission);
    }

    public function organizations()
    {
        return $this->belongsToMany(Organization::class, 'organization_memberships')
            ->withPivot('role')
            ->withTimestamps();
    }

    public function workspaces()
    {
        return $this->belongsToMany(Workspace::class, 'workspace_memberships')
            ->withPivot('role_id')
            ->withTimestamps();
    }
}

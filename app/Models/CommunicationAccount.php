<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Crypt;

class CommunicationAccount extends Model
{
    use HasFactory;

    protected $table = 'comm_accounts';

    protected $fillable = [
        'organization_id',
        'workspace_id',
        'user_id',
        'provider',
        'external_account_id',
        'display_name',
        'email',
        'phone_number',
        'enabled',
        'status',
        'encrypted_access_token',
        'encrypted_refresh_token',
        'token_expires_at',
        'last_synced_at',
        'sync_cursor',
        'last_error',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'metadata' => 'array',
            'token_expires_at' => 'datetime',
            'last_synced_at' => 'datetime',
        ];
    }

    public function setAccessTokenAttribute(?string $value): void
    {
        $this->attributes['encrypted_access_token'] = $value ? Crypt::encryptString($value) : null;
    }

    public function getAccessTokenAttribute(): ?string
    {
        if (empty($this->attributes['encrypted_access_token'])) {
            return null;
        }

        try {
            return Crypt::decryptString($this->attributes['encrypted_access_token']);
        } catch (\Throwable) {
            return null;
        }
    }

    public function setRefreshTokenAttribute(?string $value): void
    {
        $this->attributes['encrypted_refresh_token'] = $value ? Crypt::encryptString($value) : null;
    }

    public function getRefreshTokenAttribute(): ?string
    {
        if (empty($this->attributes['encrypted_refresh_token'])) {
            return null;
        }

        try {
            return Crypt::decryptString($this->attributes['encrypted_refresh_token']);
        } catch (\Throwable) {
            return null;
        }
    }

    public function scopeForWorkspace(Builder $query, int $organizationId, int $workspaceId): Builder
    {
        return $query->where('organization_id', $organizationId)->where('workspace_id', $workspaceId);
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(CommunicationConversation::class, 'account_id');
    }
}

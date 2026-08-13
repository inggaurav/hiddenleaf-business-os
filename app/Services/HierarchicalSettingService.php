<?php

namespace App\Services;

use App\Models\Organization;
use App\Models\Setting;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Crypt;
use InvalidArgumentException;

class HierarchicalSettingService
{
    private const SCOPES = ['platform', 'organization', 'workspace', 'user'];

    private const SENSITIVE_PATTERN = '/(?:password|secret|private_key|api_key|access_key|token|webhook_key)$/i';

    public function authorize(User $user, string $scope, ?Organization $organization, ?Workspace $workspace): int
    {
        if (! in_array($scope, self::SCOPES, true)) {
            throw new InvalidArgumentException('Invalid settings scope.');
        }

        if ($scope === 'platform') {
            throw_unless($user->isSuperAdmin(), AuthorizationException::class);

            return 0;
        }

        if ($scope === 'user') {
            return $user->id;
        }

        throw_unless($organization, AuthorizationException::class);
        $isOwner = (int) $organization->owner_id === (int) $user->id;
        throw_unless($user->isSuperAdmin() || $isOwner, AuthorizationException::class);

        if ($scope === 'organization') {
            return $organization->id;
        }

        throw_unless($workspace && (int) $workspace->organization_id === (int) $organization->id, AuthorizationException::class);

        return $workspace->id;
    }

    public function put(
        User $actor,
        string $scope,
        int $scopeId,
        string $key,
        mixed $value,
        ?Organization $organization = null,
        ?Workspace $workspace = null,
        bool $public = false,
    ): Setting {
        if (! preg_match('/^[a-z][a-z0-9_.-]{1,119}$/', $key)) {
            throw new InvalidArgumentException("Invalid setting key: {$key}");
        }

        $serialized = is_array($value) ? json_encode($value, JSON_THROW_ON_ERROR) : (string) $value;
        if (strlen($serialized) > 65535) {
            throw new InvalidArgumentException("Setting {$key} exceeds the value limit.");
        }

        $encrypted = (bool) preg_match(self::SENSITIVE_PATTERN, $key);

        return Setting::updateOrCreate(
            ['scope' => $scope, 'scope_id' => $scopeId, 'key' => $key],
            [
                'value' => $encrypted ? Crypt::encryptString($serialized) : $serialized,
                'is_encrypted' => $encrypted,
                'is_public' => $public && ! $encrypted,
                'organization_id' => $organization?->id,
                'workspace_id' => $scope === 'workspace' ? $workspace?->id : null,
                'user_id' => $scope === 'user' ? $actor->id : null,
                'created_by' => $actor->id,
            ],
        );
    }

    public function values(string $scope, int $scopeId, bool $maskSecrets = true): array
    {
        return Setting::where(['scope' => $scope, 'scope_id' => $scopeId])->get()
            ->mapWithKeys(fn (Setting $setting) => [
                $setting->key => $setting->is_encrypted
                    ? ($maskSecrets ? '********' : Crypt::decryptString($setting->value))
                    : $setting->value,
            ])->all();
    }

    public function resolved(User $user, ?Organization $organization, ?Workspace $workspace, bool $maskSecrets = true): array
    {
        return array_replace(
            $this->values('platform', 0, $maskSecrets),
            $organization ? $this->values('organization', $organization->id, $maskSecrets) : [],
            $workspace ? $this->values('workspace', $workspace->id, $maskSecrets) : [],
            $this->values('user', $user->id, $maskSecrets),
        );
    }
}

<?php

namespace App\Services;

use App\Models\Role;
use App\Models\User;
use App\Models\Workspace;

class BusinessRoleResolver
{
    public const SUPERADMIN = 'superadmin';

    public const COMPANY = 'company';

    public const STAFF = 'staff';

    public const CLIENT = 'client';

    public const VENDOR = 'vendor';

    /**
     * Resolve the WorkDo-compatible business role without replacing HiddenLeaf RBAC.
     *
     * HiddenLeaf remains authoritative for permission checks. This resolver only
     * supplies the business persona used by dashboards/navigation/presenters.
     */
    public function resolve(User $user, ?Workspace $workspace = null): string
    {
        if ($user->isSuperAdmin()) {
            return self::SUPERADMIN;
        }

        if ($workspace && (int) $workspace->organization?->owner_id === (int) $user->id) {
            return self::COMPANY;
        }

        if (in_array($user->role, ['company', 'company_admin'], true)) {
            return self::COMPANY;
        }

        if ($workspace) {
            $membership = $user->workspaces()
                ->where('workspaces.id', $workspace->id)
                ->first();

            $roleId = $membership?->pivot?->role_id;
            if ($roleId) {
                $role = Role::find($roleId);
                $normalized = $this->normalizeRoleName($role?->name);

                if ($normalized) {
                    return $normalized;
                }
            }
        }

        // Existing HiddenLeaf team/member/user accounts map to WorkDo's Staff persona.
        return self::STAFF;
    }

    private function normalizeRoleName(?string $roleName): ?string
    {
        if (! $roleName) {
            return null;
        }

        $normalized = strtolower(trim(str_replace(['_', ' '], '-', $roleName)));

        return match ($normalized) {
            'client', 'customer', 'workspace-client' => self::CLIENT,
            'vendor', 'supplier', 'workspace-vendor' => self::VENDOR,
            'staff', 'employee', 'member', 'team-member', 'workspace-member', 'workspace-staff' => self::STAFF,
            'admin', 'owner', 'company', 'company-admin', 'workspace-admin' => self::COMPANY,
            default => null,
        };
    }
}

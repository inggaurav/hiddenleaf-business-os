<?php

namespace HiddenLeaf\Domain\SaaS\Services;

class PlanLimitEnforcer
{
    public function canAddUser(array $planLimits, int $currentUsers): bool
    {
        $max = $planLimits['max_users'] ?? -1;
        if ($max === -1) {
            return true;
        }

        return $currentUsers < $max;
    }

    public function canCreateWorkspace(array $planLimits, int $currentWorkspaces): bool
    {
        $max = $planLimits['max_workspaces'] ?? -1;
        if ($max === -1) {
            return true;
        }

        return $currentWorkspaces < $max;
    }

    public function canUploadFile(array $planLimits, int $currentStorageBytes, int $newFileSizeBytes): bool
    {
        $maxMb = $planLimits['max_storage_mb'] ?? -1;
        if ($maxMb === -1) {
            return true;
        }

        $maxBytes = $maxMb * 1024 * 1024;
        return ($currentStorageBytes + $newFileSizeBytes) <= $maxBytes;
    }

    public function isModuleAllowed(array $planEntitlements, string $moduleAlias): bool
    {
        $allowed = $planEntitlements['modules'] ?? [];
        if (in_array('*', $allowed, true)) {
            return true;
        }

        return in_array($moduleAlias, $allowed, true);
    }
}

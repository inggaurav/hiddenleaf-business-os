<?php

namespace Tests\Feature;

use HiddenLeaf\Domain\SaaS\Services\PlanLimitEnforcer;
use PHPUnit\Framework\TestCase;

class PlanLimitTest extends TestCase
{
    protected PlanLimitEnforcer $enforcer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->enforcer = new PlanLimitEnforcer;
    }

    public function test_user_limit_enforcement(): void
    {
        $planLimits = ['max_users' => 5];

        $this->assertTrue($this->enforcer->canAddUser($planLimits, 4));
        $this->assertFalse($this->enforcer->canAddUser($planLimits, 5));
    }

    public function test_unlimited_plan_limits(): void
    {
        $planLimits = ['max_users' => -1, 'max_workspaces' => -1];

        $this->assertTrue($this->enforcer->canAddUser($planLimits, 1000));
        $this->assertTrue($this->enforcer->canCreateWorkspace($planLimits, 1000));
    }

    public function test_storage_limit_enforcement(): void
    {
        $planLimits = ['max_storage_mb' => 10]; // 10 MB = 10485760 bytes

        $currentStorage = 9 * 1024 * 1024; // 9 MB
        $smallFile = 500 * 1024; // 500 KB
        $largeFile = 2 * 1024 * 1024; // 2 MB

        $this->assertTrue($this->enforcer->canUploadFile($planLimits, $currentStorage, $smallFile));
        $this->assertFalse($this->enforcer->canUploadFile($planLimits, $currentStorage, $largeFile));
    }
}

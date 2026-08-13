<?php

namespace Tests\Feature;

use HiddenLeaf\Architecture\ActorContext;
use HiddenLeaf\Kernel\Services\AuditLogger;
use PHPUnit\Framework\TestCase;

class SecurityTest extends TestCase
{
    public function test_actor_permission_verification(): void
    {
        $actor = new ActorContext();
        $actor->set(42, 'member', ['workspace.members.view', 'media.upload']);

        $this->assertTrue($actor->hasPermission('workspace.members.view'));
        $this->assertTrue($actor->hasPermission('media.upload'));
        $this->assertFalse($actor->hasPermission('settings.brand.manage'));
    }

    public function test_super_admin_bypasses_all_permissions(): void
    {
        $admin = new ActorContext();
        $admin->set(1, 'super_admin', []);

        $this->assertTrue($admin->hasPermission('any.arbitrary.permission'));
    }

    public function test_audit_logger_redacts_sensitive_metadata(): void
    {
        $auditLogger = new AuditLogger();
        $entry = $auditLogger->log(
            1, 10, 100,
            'user.login', 'User', '1',
            ['password' => 'SuperSecret123!', 'api_key' => 'sk_live_xyz', 'ip' => '192.168.1.1'],
            '192.168.1.1'
        );

        $this->assertEquals('[REDACTED]', $entry['metadata']['password']);
        $this->assertEquals('[REDACTED]', $entry['metadata']['api_key']);
        $this->assertEquals('192.168.1.1', $entry['metadata']['ip']);
    }
}

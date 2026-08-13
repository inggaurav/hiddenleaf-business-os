<?php

namespace Tests\Feature;

use HiddenLeaf\Architecture\OrganizationContext;
use HiddenLeaf\Architecture\WorkspaceContext;
use PHPUnit\Framework\TestCase;

class MultiTenancyTest extends TestCase
{
    public function test_organization_and_workspace_contexts_store_active_tenant(): void
    {
        $orgContext = new OrganizationContext;
        $wsContext = new WorkspaceContext;

        $this->assertFalse($orgContext->isSet());
        $this->assertFalse($wsContext->isSet());

        $orgContext->set(1, 'HiddenLeaf Corp');
        $wsContext->set(10, 1, 'Main Operations');

        $this->assertTrue($orgContext->isSet());
        $this->assertEquals(1, $orgContext->getId());
        $this->assertEquals('HiddenLeaf Corp', $orgContext->getName());

        $this->assertTrue($wsContext->isSet());
        $this->assertEquals(10, $wsContext->getId());
        $this->assertEquals(1, $wsContext->getOrganizationId());
    }
}

<?php

namespace Tests\Feature\MrFox;

use App\Domain\MrFox\Tools\CrmCreateLeadTool;
use App\Domain\MrFox\Tools\SalesInvoiceSearchTool;
use App\Domain\MrFox\Validation\ToolInputValidator;
use Tests\TestCase;

class MrFoxInputValidationTest extends TestCase
{
    public function test_missing_required_fields_fails_validation(): void
    {
        $validator = new ToolInputValidator;
        $tool = new CrmCreateLeadTool;

        $result = $validator->validate($tool, [
            'email' => 'test@example.com',
            // Missing required 'name'
        ]);

        $this->assertFalse($result['valid']);
        $this->assertArrayHasKey('name', $result['errors']);
    }

    public function test_forbidden_authority_keys_are_stripped_and_ignored(): void
    {
        $validator = new ToolInputValidator;
        $tool = new CrmCreateLeadTool;

        $result = $validator->validate($tool, [
            'name' => 'Valid Lead',
            'organization_id' => 9999,
            'workspace_id' => 8888,
            'is_super_admin' => true,
        ]);

        $this->assertTrue($result['valid']);
        $this->assertArrayNotHasKey('organization_id', $result['sanitized']);
        $this->assertArrayNotHasKey('workspace_id', $result['sanitized']);
        $this->assertArrayNotHasKey('is_super_admin', $result['sanitized']);
        $this->assertEquals('Valid Lead', $result['sanitized']['name']);
    }

    public function test_oversized_limit_is_bounded(): void
    {
        $validator = new ToolInputValidator;
        $tool = new SalesInvoiceSearchTool;

        $result = $validator->validate($tool, [
            'limit' => 5000, // exceeds max: 100
        ]);

        $this->assertFalse($result['valid']);
        $this->assertArrayHasKey('limit', $result['errors']);
    }
}

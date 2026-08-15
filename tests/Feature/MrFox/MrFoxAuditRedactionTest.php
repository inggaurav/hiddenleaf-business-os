<?php

namespace Tests\Feature\MrFox;

use App\Domain\MrFox\Context\BusinessContextService;
use App\Domain\MrFox\DTO\ToolResult;
use App\Domain\MrFox\Observability\MrFoxAuditService;
use App\Domain\MrFox\RiskLevel;
use App\Models\MrFoxAuditLog;
use App\Models\Organization;
use App\Models\Plan;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MrFoxAuditRedactionTest extends TestCase
{
    use RefreshDatabase;

    public function test_sensitive_passwords_and_tokens_are_redacted_from_audit_logs(): void
    {
        $this->seed();
        $user = User::factory()->create(['role' => 'super_admin']);
        $plan = Plan::create(['name' => 'Audit Plan', 'modules' => ['crm'], 'status' => true, 'created_by' => $user->id]);
        $org = Organization::factory()->create(['owner_id' => $user->id, 'plan_id' => $plan->id]);
        $workspace = Workspace::factory()->create(['organization_id' => $org->id, 'created_by' => $user->id]);

        $auditService = app(MrFoxAuditService::class);
        $contextService = app(BusinessContextService::class);
        $context = $contextService->createToolContext($user, $workspace);

        $sensitivePayload = [
            'username' => 'admin_user',
            'password' => 'SuperSecretP@ssword123!',
            'api_key' => 'sk-live_99887766554433221100',
            'nested' => [
                'stripe_secret' => 'sk_live_abcdef1234567890',
                'bearer_token' => 'Bearer eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9',
            ],
        ];

        $result = ToolResult::success([], 'Connected with Bearer eyJhbGciOiJIUzI1Ni... token');

        $log = $auditService->logToolExecution(
            $context,
            'test.tool',
            $sensitivePayload,
            $result,
            RiskLevel::LOW,
            120,
            'openai',
            'gpt-4o'
        );

        $this->assertDatabaseHas('mrfox_audit_logs', ['id' => $log->id]);

        $savedLog = MrFoxAuditLog::find($log->id);
        $input = $savedLog->input_payload;

        $this->assertEquals('[REDACTED]', $input['password']);
        $this->assertEquals('[REDACTED]', $input['api_key']);
        $this->assertEquals('[REDACTED]', $input['nested']['stripe_secret']);
        $this->assertEquals('[REDACTED]', $input['nested']['bearer_token']);

        // Verify summary scrubbing
        $this->assertStringNotContainsString('eyJhbGciOiJIUzI1Ni', $savedLog->output_summary);
        $this->assertStringContainsString('[REDACTED]', $savedLog->output_summary);
    }
}

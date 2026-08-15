<?php

namespace Tests\Feature\MrFox;

use App\Domain\MrFox\Agent\MrFoxAgent;
use App\Domain\MrFox\DTO\AiResponse;
use App\Domain\MrFox\Providers\FakeAiProvider;
use App\Domain\MrFox\Providers\ProviderRouter;
use App\Models\Organization;
use App\Models\Plan;
use App\Models\Role;
use App\Models\User;
use App\Models\UserActiveModule;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MrFoxRbacNegativeTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_without_accounting_permission_is_denied_accounting_tool_execution(): void
    {
        $this->seed();
        $owner = User::factory()->create(['role' => 'company_admin']);
        $unprivilegedUser = User::factory()->create(['role' => 'member']);

        $plan = Plan::create(['name' => 'All Modules Plan', 'modules' => ['crm', 'account', 'hrm', 'taskly', 'productservice'], 'status' => true, 'created_by' => $owner->id]);
        $org = Organization::factory()->create(['owner_id' => $owner->id, 'plan_id' => $plan->id]);
        $workspace = Workspace::factory()->create(['organization_id' => $org->id, 'created_by' => $owner->id]);

        // Create a role without accounting permissions
        $memberRole = Role::create([
            'organization_id' => $org->id,
            'name' => 'crm_only_member',
            'display_name' => 'CRM Only Member',
            'guard_name' => 'web',
        ]);

        $org->members()->attach($unprivilegedUser, ['role' => 'member']);
        $workspace->members()->attach($unprivilegedUser, ['role_id' => $memberRole->id]);

        UserActiveModule::create(['workspace_id' => $workspace->id, 'module_name' => 'account']);

        // Set up mock provider returning an accounting tool call
        $fakeProvider = new FakeAiProvider();
        $fakeProvider->queueResponse(new AiResponse(
            content: 'Checking accounting figures',
            toolCalls: [
                ['id' => 'call_1', 'name' => 'accounting.pnl', 'arguments' => []],
            ],
            provider: 'fake',
            model: 'fake-model'
        ));

        app(ProviderRouter::class)->setFakeProvider($fakeProvider);

        $agent = app(MrFoxAgent::class);
        $result = $agent->handle($unprivilegedUser, $workspace, [
            ['role' => 'user', 'content' => 'Show me the profit and loss report'],
        ]);

        $this->assertNotEmpty($result['tools_executed']);
        $toolExecution = $result['tools_executed'][0];

        $this->assertFalse($toolExecution['success']);
        $this->assertStringContainsString('Access denied', $toolExecution['summary']);
    }
}

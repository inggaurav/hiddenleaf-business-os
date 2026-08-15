<?php

namespace Tests\Feature\Communications;

use App\Domain\Communications\DTO\OutgoingMessage;
use App\Domain\Communications\Providers\InternalMessengerProvider;
use App\Models\ChMessage;
use App\Models\CommunicationAccount;
use App\Models\Organization;
use App\Models\Plan;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InternalMessengerAdapterTest extends TestCase
{
    use RefreshDatabase;

    public function test_internal_messenger_provider_maps_workdo_ch_messages(): void
    {
        $this->seed();
        $user1 = User::factory()->create(['role' => 'company']);
        $user2 = User::factory()->create(['role' => 'employee']);
        $plan = Plan::create(['name' => 'Plan A', 'modules' => ['crm'], 'status' => true, 'created_by' => $user1->id]);
        $org = Organization::factory()->create(['owner_id' => $user1->id, 'plan_id' => $plan->id]);
        $ws = Workspace::factory()->create(['organization_id' => $org->id, 'created_by' => $user1->id]);

        // Create legacy WorkDo ch_messages
        ChMessage::create([
            'from_id' => $user1->id,
            'to_id' => $user2->id,
            'body' => 'Welcome to the team project channel!',
            'workspace_id' => $ws->id,
            'seen' => false,
        ]);

        $account = CommunicationAccount::create([
            'organization_id' => $org->id,
            'workspace_id' => $ws->id,
            'user_id' => $user1->id,
            'provider' => 'internal',
            'external_account_id' => "internal_{$user1->id}",
            'display_name' => $user1->name,
        ]);

        $provider = new InternalMessengerProvider();

        // Test sync mapping
        $sync = $provider->sync($account);
        $this->assertTrue($sync->success);
        $this->assertCount(1, $sync->messages);
        $this->assertEquals('Welcome to the team project channel!', $sync->messages[0]['body_text']);

        // Test sending via adapter creates ch_message
        $out = new OutgoingMessage(
            recipient: (string) $user2->id,
            bodyText: 'Looking forward to collaborating.'
        );
        $res = $provider->send($account, $out);
        $this->assertTrue($res->success);
        $this->assertEquals(2, ChMessage::where('workspace_id', $ws->id)->count());
    }
}

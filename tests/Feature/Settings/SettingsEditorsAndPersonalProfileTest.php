<?php

namespace Tests\Feature\Settings;

use App\Domain\Webhooks\WebhookService;
use App\Models\EmailTemplate;
use App\Models\Organization;
use App\Models\NotificationTemplate;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Models\Webhook;
use App\Models\WebhookDelivery;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Mockery\MockInterface;
use Tests\TestCase;

class SettingsEditorsAndPersonalProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_personal_profile_persists_identity_language_theme_and_verified_password_change(): void
    {
        $user = User::factory()->create(['password' => 'Old-password-123', 'lang' => 'en', 'theme' => 'system']);

        $this->actingAs($user)->patch('/profile', [
            'name' => 'Updated Person', 'email' => 'updated@example.test', 'phone' => '+91 90000 00000',
            'lang' => 'fr', 'theme' => 'dark', 'current_password' => 'Old-password-123',
            'password' => 'New-password-456', 'password_confirmation' => 'New-password-456',
        ])->assertSessionHasNoErrors();

        $user->refresh();
        $this->assertSame('Updated Person', $user->name);
        $this->assertSame('+91 90000 00000', $user->phone);
        $this->assertSame('fr', $user->lang);
        $this->assertSame('dark', $user->theme);
        $this->assertTrue(Hash::check('New-password-456', $user->password));

        $this->actingAs($user)->patch('/profile', [
            'name' => $user->name, 'email' => $user->email, 'phone' => $user->phone, 'lang' => 'fr', 'theme' => 'dark',
            'current_password' => 'wrong', 'password' => 'Another-password-789', 'password_confirmation' => 'Another-password-789',
        ])->assertSessionHasErrors('current_password');
    }

    public function test_email_template_customization_is_tenant_local_and_permission_guarded(): void
    {
        [$manager, $organization, $workspace] = $this->tenant(['settings.notifications.manage']);
        [, , $otherWorkspace] = $this->tenant(['settings.notifications.manage']);
        $global = EmailTemplate::create(['name' => 'Invoice created', 'module' => 'general', 'subject' => 'Global subject', 'body' => 'Global body', 'variables' => ['{invoice_number}'], 'is_enabled' => true]);

        $session = $this->tenantSession($organization, $workspace);
        $response = $this->actingAs($manager)->withSession($session)->get("/email-templates/{$global->id}")->assertOk();
        $localId = $response->inertiaProps('template.id');
        $this->assertNotSame($global->id, $localId);

        $this->actingAs($manager)->withSession($session)->patch("/email-templates/{$localId}", [
            'lang' => 'en', 'subject' => 'Tenant subject', 'content' => 'Hello {invoice_number}', 'is_enabled' => false,
        ])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('email_templates', ['id' => $localId, 'workspace_id' => $workspace->id, 'is_enabled' => false]);
        $this->assertDatabaseHas('email_template_langs', ['parent_id' => $localId, 'lang' => 'en', 'subject' => 'Tenant subject']);
        $this->assertDatabaseHas('email_templates', ['id' => $global->id, 'subject' => 'Global subject']);

        $outsider = User::factory()->create();
        $otherWorkspace->members()->attach($outsider);
        $this->actingAs($outsider)->withSession(['active_organization_id' => $otherWorkspace->organization_id, 'active_workspace_id' => $otherWorkspace->id])
            ->get('/settings/email-templates')->assertForbidden();
    }

    public function test_webhook_manager_supports_events_status_toggle_rotation_and_tenant_isolation(): void
    {
        [$manager, $organization, $workspace] = $this->tenant(['webhooks.manage']);
        $this->mock(WebhookService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('validateUrl')->andReturnNull();
        });
        $session = $this->tenantSession($organization, $workspace);
        $this->actingAs($manager)->withSession($session)->post('/webhooks', [
            'url' => 'https://hooks.example.test/erp', 'events' => ['order.paid', 'invoice.posted'], 'method' => 'POST', 'timeout_seconds' => 12,
        ])->assertSessionHasNoErrors();
        $hook = Webhook::sole();
        WebhookDelivery::create(['id' => (string) Str::uuid(), 'webhook_id' => $hook->id, 'event' => 'order.paid', 'idempotency_key' => 'status-1', 'payload' => [], 'status' => 'delivered', 'response_status' => 200]);

        $this->actingAs($manager)->withSession($session)->get('/webhooks')->assertInertia(fn (Assert $page) => $page
            ->where('webhooks.0.events', ['order.paid', 'invoice.posted'])
            ->where('webhooks.0.deliveries.0.status', 'delivered')
            ->missing('webhooks.0.secret'));

        $oldSecret = $hook->secret;
        $this->actingAs($manager)->withSession($session)->patch("/webhooks/{$hook->id}/toggle")->assertSessionHasNoErrors();
        $this->assertFalse($hook->refresh()->is_active);
        $this->actingAs($manager)->withSession($session)->post("/webhooks/{$hook->id}/rotate-secret")->assertSessionHasNoErrors();
        $this->assertNotSame($oldSecret, $hook->refresh()->secret);
        $this->assertNotSame($hook->secret, DB::table('webhooks')->where('id', $hook->id)->value('secret'));

        [, , $foreignWorkspace] = $this->tenant(['webhooks.manage']);
        $this->actingAs($manager)->withSession(['active_organization_id' => $organization->id, 'active_workspace_id' => $workspace->id])
            ->delete('/webhooks/'.Webhook::create(['organization_id' => $foreignWorkspace->organization_id, 'workspace_id' => $foreignWorkspace->id, 'url' => 'https://foreign.example.test', 'event' => 'order.paid', 'events' => ['order.paid'], 'secret' => 'foreign'])->id)
            ->assertNotFound();
    }

    public function test_notification_templates_are_filtered_by_active_modules_and_support_tenant_override_reset(): void
    {
        [$manager, $organization, $workspace] = $this->tenant(['settings.notifications.manage']);
        $this->entitleWorkspaceModules($organization, $workspace, $manager, ['hrm']);
        NotificationTemplate::create(['name' => 'General alert', 'module' => 'general', 'variables' => ['{message}']]);
        $hrm = NotificationTemplate::create(['name' => 'Leave approved', 'module' => 'hrm', 'variables' => ['{employee_name}']]);
        NotificationTemplate::create(['name' => 'Deal won', 'module' => 'lead', 'variables' => ['{deal_name}']]);
        $session = $this->tenantSession($organization, $workspace);

        $this->actingAs($manager)->withSession($session)->get('/notification-templates')->assertInertia(fn (Assert $page) => $page
            ->has('templates', 2)
            ->where('templates.0.name', 'General alert')
            ->where('templates.1.name', 'Leave approved'));

        $response = $this->actingAs($manager)->withSession($session)->get("/notification-templates/{$hrm->id}")->assertOk();
        $localId = $response->inertiaProps('template.id');
        $this->actingAs($manager)->withSession($session)->patch("/notification-templates/{$localId}", [
            'lang' => 'en', 'content' => 'Leave approved for {employee_name}', 'is_enabled' => false,
        ])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('notification_templates', ['id' => $localId, 'workspace_id' => $workspace->id, 'is_enabled' => false]);
        $this->assertDatabaseHas('notification_template_langs', ['parent_id' => $localId, 'lang' => 'en']);
        $this->actingAs($manager)->withSession($session)->post("/notification-templates/{$localId}/reset", ['lang' => 'en'])->assertSessionHasNoErrors();
        $this->assertDatabaseMissing('notification_template_langs', ['parent_id' => $localId, 'lang' => 'en']);
    }

    /** @return array{User, Organization, Workspace} */
    private function tenant(array $permissions): array
    {
        $this->seed();
        $owner = User::factory()->create(['role' => 'company_admin']);
        $manager = User::factory()->create(['role' => 'user']);
        $organization = Organization::factory()->create(['owner_id' => $owner->id]);
        $workspace = Workspace::factory()->create(['organization_id' => $organization->id, 'created_by' => $owner->id]);
        $organization->members()->attach($manager, ['role' => 'member']);
        $role = Role::create(['organization_id' => $organization->id, 'name' => 'settings-'.Str::random(8), 'display_name' => 'Settings Manager', 'is_system' => false]);
        $role->permissions()->sync(Permission::whereIn('name', $permissions)->pluck('id'));
        $workspace->members()->attach($manager, ['role_id' => $role->id]);

        return [$manager, $organization, $workspace];
    }

    private function tenantSession(Organization $organization, Workspace $workspace): array
    {
        return ['active_organization_id' => $organization->id, 'active_workspace_id' => $workspace->id];
    }
}

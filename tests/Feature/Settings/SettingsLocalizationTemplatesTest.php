<?php

namespace Tests\Feature\Settings;

use App\Models\EmailTemplate;
use App\Models\Language;
use App\Models\NotificationTemplate;
use App\Models\Organization;
use App\Models\Setting;
use App\Models\User;
use App\Models\Workspace;
use App\Notifications\BusinessNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SettingsLocalizationTemplatesTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;

    protected User $companyAdmin;

    protected Organization $org;

    protected Workspace $ws;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->superAdmin = User::factory()->create([
            'role' => 'super_admin',
            'email' => 'super@hiddenleaf.test',
        ]);

        $this->companyAdmin = User::factory()->create([
            'role' => 'company_admin',
            'email' => 'company@hiddenleaf.test',
        ]);

        $this->org = Organization::factory()->create(['owner_id' => $this->companyAdmin->id]);
        $this->ws = Workspace::factory()->create([
            'organization_id' => $this->org->id,
            'created_by' => $this->companyAdmin->id,
        ]);

        $this->companyAdmin->organizations()->attach($this->org->id, ['role' => 'owner']);
        $this->companyAdmin->workspaces()->attach($this->ws->id);
    }

    public function test_system_settings_and_tenant_workspace_isolation(): void
    {
        // 1. Super admin saves system setting
        $this->actingAs($this->superAdmin)->post('/settings', [
            'site_name' => 'HiddenLeaf Global Cloud',
            'default_currency' => 'EUR',
        ]);

        $this->assertEquals('HiddenLeaf Global Cloud', admin_setting('site_name'));
        $this->assertEquals('EUR', admin_setting('default_currency'));

        // 2. Company admin saves workspace setting
        $this->actingAs($this->companyAdmin)
            ->withSession(['active_organization_id' => $this->org->id, 'active_workspace_id' => $this->ws->id])
            ->post('/settings', [
                'site_name' => 'Acme Internal ERP',
            ]);

        // Assert system setting remains unchanged
        $this->assertEquals('HiddenLeaf Global Cloud', admin_setting('site_name'));

        // Assert workspace setting is scoped
        $wsSetting = Setting::where('workspace_id', $this->ws->id)->where('key', 'site_name')->first();
        $this->assertNotNull($wsSetting);
        $this->assertEquals('Acme Internal ERP', $wsSetting->value);
    }

    public function test_language_crud_and_switching(): void
    {
        // Create language
        $response = $this->actingAs($this->superAdmin)->post('/languages', [
            'code' => 'de',
            'name' => 'German',
        ]);
        $response->assertRedirect('/languages');
        $this->assertDatabaseHas('languages', ['code' => 'de', 'name' => 'German']);

        // Switch language
        $switchResp = $this->actingAs($this->companyAdmin)->get('/languages/change/de');
        $switchResp->assertSessionHas('locale', 'de');

        // Save translations
        $transResp = $this->actingAs($this->superAdmin)->post('/languages/save-data/de', [
            'translations' => ['Dashboard' => 'Instrumententafel', 'Save' => 'Speichern'],
        ]);
        $transResp->assertSessionHas('success');
    }

    public function test_email_template_localization(): void
    {
        $template = EmailTemplate::create([
            'name' => 'New User Registration',
            'subject' => 'Welcome to HiddenLeaf',
            'body' => 'Welcome {name}!',
        ]);

        // Save German localization
        $resp = $this->actingAs($this->superAdmin)->put("/email-templates/{$template->id}", [
            'lang' => 'de',
            'subject' => 'Willkommen bei HiddenLeaf',
            'content' => 'Willkommen {name} bei unserem Service!',
        ]);

        $resp->assertSessionHas('success');
        $this->assertDatabaseHas('email_template_langs', [
            'parent_id' => $template->id,
            'lang' => 'de',
            'subject' => 'Willkommen bei HiddenLeaf',
        ]);
    }

    public function test_notification_template_localization(): void
    {
        $template = NotificationTemplate::create([
            'name' => 'Invoice Paid',
            'module' => 'sales',
        ]);

        $resp = $this->actingAs($this->superAdmin)->put("/notification-templates/{$template->id}", [
            'lang' => 'fr',
            'content' => 'Votre facture #{invoice_id} a été payée.',
        ]);

        $resp->assertSessionHas('success');
        $this->assertDatabaseHas('notification_template_langs', [
            'parent_id' => $template->id,
            'lang' => 'fr',
            'content' => 'Votre facture #{invoice_id} a été payée.',
        ]);
    }

    public function test_hierarchical_settings_encrypt_secrets_and_enforce_scope_ownership(): void
    {
        $this->actingAs($this->companyAdmin)
            ->withSession(['active_organization_id' => $this->org->id, 'active_workspace_id' => $this->ws->id])
            ->post('/settings', [
                '_scope' => 'organization',
                'timezone' => 'Asia/Kolkata',
                'smtp_password' => 'tenant-secret-password',
            ])->assertRedirect();

        $secret = Setting::where('scope', 'organization')
            ->where('scope_id', $this->org->id)
            ->where('key', 'smtp_password')
            ->firstOrFail();
        $this->assertTrue($secret->is_encrypted);
        $this->assertNotSame('tenant-secret-password', $secret->value);

        $member = User::factory()->create();
        $member->organizations()->attach($this->org->id, ['role' => 'member']);
        $member->workspaces()->attach($this->ws->id);
        $this->actingAs($member)
            ->withSession(['active_organization_id' => $this->org->id, 'active_workspace_id' => $this->ws->id])
            ->post('/settings', ['_scope' => 'workspace', 'timezone' => 'UTC'])
            ->assertForbidden();

        $this->actingAs($member)
            ->withSession(['active_organization_id' => $this->org->id, 'active_workspace_id' => $this->ws->id])
            ->post('/settings', ['_scope' => 'user', 'timezone' => 'UTC'])
            ->assertRedirect();
        $this->assertDatabaseHas('settings', [
            'scope' => 'user',
            'scope_id' => $member->id,
            'key' => 'timezone',
            'value' => 'UTC',
        ]);
    }

    public function test_localized_template_preview_and_database_notification_lifecycle(): void
    {
        $template = EmailTemplate::create([
            'name' => 'Security Alert',
            'subject' => 'Alert for {name}',
            'body' => 'Login from {ip}',
        ]);

        $this->actingAs($this->companyAdmin)
            ->withSession(['active_organization_id' => $this->org->id, 'active_workspace_id' => $this->ws->id])
            ->postJson("/email-templates/{$template->id}/preview", [
                'variables' => ['name' => 'Alice', 'ip' => '127.0.0.1'],
            ])->assertOk()
            ->assertJsonPath('subject', 'Alert for Alice')
            ->assertJsonPath('content', 'Login from 127.0.0.1');

        $this->companyAdmin->notifyNow(new BusinessNotification(
            'security.login',
            'New login',
            'A new login was detected.',
            $this->org->id,
            $this->ws->id,
        ));

        $notification = $this->companyAdmin->notifications()->firstOrFail();
        $this->actingAs($this->companyAdmin)
            ->withSession(['active_organization_id' => $this->org->id, 'active_workspace_id' => $this->ws->id])
            ->getJson('/notifications?unread=1')
            ->assertOk()
            ->assertJsonPath('unread_count', 1)
            ->assertJsonFragment(['event' => 'security.login']);

        $this->actingAs($this->companyAdmin)
            ->withSession(['active_organization_id' => $this->org->id, 'active_workspace_id' => $this->ws->id])
            ->patchJson("/notifications/{$notification->id}/read")
            ->assertNoContent();
        $this->assertNotNull($notification->fresh()->read_at);
    }
}

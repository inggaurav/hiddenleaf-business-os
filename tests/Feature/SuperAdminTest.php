<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SuperAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_non_super_admin_cannot_access_dashboard()
    {
        $user = User::factory()->create(['role' => 'user']);
        $org = Organization::factory()->create(['owner_id' => $user->id]);
        $user->organizations()->attach($org->id);

        $response = $this->actingAs($user)->get('/super-admin/dashboard');

        $response->assertStatus(403);
    }

    public function test_super_admin_can_access_dashboard()
    {
        $superAdmin = User::factory()->create(['role' => 'super_admin']);

        $response = $this->actingAs($superAdmin)->get('/super-admin/dashboard');

        $response->assertStatus(200);
    }

    public function test_super_admin_can_update_settings()
    {
        $superAdmin = User::factory()->create(['role' => 'super_admin']);

        $response = $this->actingAs($superAdmin)->post('/super-admin/settings', [
            'site_name' => 'My New SaaS',
            'default_currency' => 'EUR',
            'timezone' => 'Europe/Berlin',
        ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('settings', [
            'key' => 'site_name',
            'value' => 'My New SaaS',
        ]);

        $this->assertDatabaseHas('settings', [
            'key' => 'default_currency',
            'value' => 'EUR',
        ]);
    }

    public function test_super_admin_can_view_settings_page()
    {
        $superAdmin = User::factory()->create(['role' => 'super_admin']);

        $response = $this->actingAs($superAdmin)->get('/super-admin/settings');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('Settings/Index')->where('isSuperAdmin', true));
    }

    public function test_super_admin_can_view_and_update_translations()
    {
        $superAdmin = User::factory()->create(['role' => 'super_admin']);

        $response = $this->actingAs($superAdmin)->get('/super-admin/translations');
        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('SuperAdmin/Translations/Index')->has('translations')->has('locales'));

        $postResp = $this->actingAs($superAdmin)->post('/super-admin/translations', [
            'locale' => 'fr',
            'key'    => 'welcome_message',
            'value'  => 'Bienvenue sur la plateforme',
        ]);
        $postResp->assertRedirect();
        $this->assertDatabaseHas('languages', ['code' => 'fr']);

        $jsonPath = resource_path('lang/fr.json');
        $this->assertFileExists($jsonPath);
        $content = json_decode(file_get_contents($jsonPath), true);
        $this->assertEquals('Bienvenue sur la plateforme', $content['welcome_message']);

        // Clean up created file
        @unlink($jsonPath);
    }
}

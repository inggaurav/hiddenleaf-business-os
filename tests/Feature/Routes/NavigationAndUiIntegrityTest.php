<?php

namespace Tests\Feature\Routes;

use App\Models\Organization;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Models\Workspace;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Tests\TestCase;

class NavigationAndUiIntegrityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_truthful_codebase_contains_zero_fake_mr_fox_result_data(): void
    {
        $tsxFiles = File::allFiles(resource_path('js'));
        $forbiddenStrings = [
            '2 Bank Transfers Awaiting Review',
            'totaling $450.00',
            '$450.00',
            'No anomalies detected across tenant workspace boundaries',
            'All records adhere to strict cryptographic isolation',
            '100% operational integrity',
            '100% Isolated',
        ];

        foreach ($tsxFiles as $file) {
            $content = File::get($file->getRealPath());
            foreach ($forbiddenStrings as $forbidden) {
                $this->assertStringNotContainsString(
                    $forbidden,
                    $content,
                    "Forbidden fake data claim '{$forbidden}' found in file: " . $file->getRelativePathname()
                );
            }
        }
    }

    public function test_inertia_shares_authorized_permissions_and_workspaces(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        $org = Organization::factory()->create(['owner_id' => $user->id]);
        $org->members()->attach($user->id, ['role' => 'member']);

        $ws1 = Workspace::factory()->create(['organization_id' => $org->id, 'name' => 'HQ Workspace']);
        $ws2 = Workspace::factory()->create(['organization_id' => $org->id, 'name' => 'Lab Workspace']);

        $role = Role::create([
            'name' => 'sales-clerk',
            'display_name' => 'Sales Clerk',
            'organization_id' => $org->id,
        ]);
        $perm = Permission::firstOrCreate(['name' => 'sales.invoice.view'], ['module' => 'sales', 'resource' => 'invoices', 'action' => 'view']);
        $role->permissions()->attach($perm->id);

        $user->workspaces()->attach($ws1->id, ['role_id' => $role->id]);

        $response = $this->actingAs($user)
            ->withSession(['active_organization_id' => $org->id, 'active_workspace_id' => $ws1->id, 'active_workspace_title' => $ws1->name])
            ->get('/dashboard');

        $response->assertOk();
        $page = $response->original->getData()['page'];

        $this->assertArrayHasKey('auth', $page['props']);
        $this->assertArrayHasKey('tenant', $page['props']);
        $this->assertEquals($ws1->name, $page['props']['tenant']['workspace_title']);
        $this->assertContains($ws1->id, array_column($page['props']['tenant']['available_workspaces'], 'id'));
    }

    public function test_super_admin_receives_super_admin_status(): void
    {
        $superAdmin = User::factory()->create(['role' => 'super_admin']);

        $response = $this->actingAs($superAdmin)->get('/dashboard');
        $response->assertOk();

        $page = $response->original->getData()['page'];
        $this->assertTrue($page['props']['auth']['user']['is_super_admin']);
    }

    public function test_workspace_switch_route_succeeds_for_authorized_member(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        $org = Organization::factory()->create(['owner_id' => $user->id]);
        $org->members()->attach($user->id, ['role' => 'member']);

        $ws1 = Workspace::factory()->create(['organization_id' => $org->id, 'name' => 'Workspace Alpha']);
        $ws2 = Workspace::factory()->create(['organization_id' => $org->id, 'name' => 'Workspace Beta']);

        $role = Role::where('name', 'workspace-member')->first();
        $user->workspaces()->attach($ws1->id, ['role_id' => $role->id]);
        $user->workspaces()->attach($ws2->id, ['role_id' => $role->id]);

        $response = $this->actingAs($user)
            ->withSession(['active_organization_id' => $org->id, 'active_workspace_id' => $ws1->id])
            ->post('/workspaces/switch', ['workspace_id' => $ws2->id]);

        $response->assertRedirect();
        $this->assertEquals($ws2->id, session('active_workspace_id'));
        $this->assertEquals('Workspace Beta', session('active_workspace_title'));
    }

    public function test_workspace_switch_route_fails_for_unauthorized_workspace(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        $org = Organization::factory()->create(['owner_id' => $user->id]);
        $org->members()->attach($user->id, ['role' => 'member']);

        $foreignOrg = Organization::factory()->create();
        $foreignWs = Workspace::factory()->create(['organization_id' => $foreignOrg->id, 'name' => 'Foreign Workspace']);

        $ws1 = Workspace::factory()->create(['organization_id' => $org->id, 'name' => 'Workspace Alpha']);
        $role = Role::where('name', 'workspace-member')->first();
        $user->workspaces()->attach($ws1->id, ['role_id' => $role->id]);

        $response = $this->actingAs($user)
            ->withSession(['active_organization_id' => $org->id, 'active_workspace_id' => $ws1->id])
            ->post('/workspaces/switch', ['workspace_id' => $foreignWs->id]);

        $response->assertStatus(403);
    }

    public function test_notifications_shared_and_visible_in_inertia(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        $org = Organization::factory()->create(['owner_id' => $user->id]);
        $org->members()->attach($user->id, ['role' => 'member']);

        $ws = Workspace::factory()->create(['organization_id' => $org->id]);
        $role = Role::where('name', 'workspace-member')->first();
        $user->workspaces()->attach($ws->id, ['role_id' => $role->id]);

        DatabaseNotification::create([
            'id' => (string) Str::uuid(),
            'type' => 'App\\Notifications\\SystemAlert',
            'notifiable_type' => User::class,
            'notifiable_id' => $user->id,
            'organization_id' => $org->id,
            'workspace_id' => $ws->id,
            'data' => [
                'title' => 'Invoice Created',
                'message' => 'Invoice #INV-001 has been posted.',
            ],
            'read_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($user)
            ->withSession(['active_organization_id' => $org->id, 'active_workspace_id' => $ws->id])
            ->get('/dashboard');

        $response->assertOk();
        $page = $response->original->getData()['page'];

        $this->assertNotEmpty($page['props']['auth']['notifications']);
        $this->assertEquals('Invoice Created', $page['props']['auth']['notifications'][0]['title']);
    }

    public function test_notification_mark_read_persists(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        $org = Organization::factory()->create(['owner_id' => $user->id]);
        $org->members()->attach($user->id, ['role' => 'member']);

        $ws = Workspace::factory()->create(['organization_id' => $org->id]);
        $role = Role::where('name', 'workspace-member')->first();
        $user->workspaces()->attach($ws->id, ['role_id' => $role->id]);

        $notification = DatabaseNotification::create([
            'id' => (string) Str::uuid(),
            'type' => 'App\\Notifications\\SystemAlert',
            'notifiable_type' => User::class,
            'notifiable_id' => $user->id,
            'organization_id' => $org->id,
            'workspace_id' => $ws->id,
            'data' => ['title' => 'Test Notification', 'message' => 'Hello world'],
            'read_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($user)
            ->withSession(['active_organization_id' => $org->id, 'active_workspace_id' => $ws->id])
            ->post("/notifications/{$notification->id}/read");

        $response->assertOk();
        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_notification_mark_all_read_persists(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        $org = Organization::factory()->create(['owner_id' => $user->id]);
        $org->members()->attach($user->id, ['role' => 'member']);

        $ws = Workspace::factory()->create(['organization_id' => $org->id]);
        $role = Role::where('name', 'workspace-member')->first();
        $user->workspaces()->attach($ws->id, ['role_id' => $role->id]);

        $n1 = DatabaseNotification::create([
            'id' => (string) Str::uuid(),
            'type' => 'App\\Notifications\\SystemAlert',
            'notifiable_type' => User::class,
            'notifiable_id' => $user->id,
            'organization_id' => $org->id,
            'workspace_id' => $ws->id,
            'data' => ['title' => 'Test 1', 'message' => 'Msg 1'],
            'read_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $n2 = DatabaseNotification::create([
            'id' => (string) Str::uuid(),
            'type' => 'App\\Notifications\\SystemAlert',
            'notifiable_type' => User::class,
            'notifiable_id' => $user->id,
            'organization_id' => $org->id,
            'workspace_id' => $ws->id,
            'data' => ['title' => 'Test 2', 'message' => 'Msg 2'],
            'read_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($user)
            ->withSession(['active_organization_id' => $org->id, 'active_workspace_id' => $ws->id])
            ->post('/notifications/read-all');

        $response->assertOk();
        $this->assertNotNull($n1->fresh()->read_at);
        $this->assertNotNull($n2->fresh()->read_at);
    }

    public function test_cross_user_notification_idor_is_rejected(): void
    {
        $userA = User::factory()->create(['role' => 'user']);
        $userB = User::factory()->create(['role' => 'user']);
        $orgA = Organization::factory()->create(['owner_id' => $userA->id]);
        $orgA->members()->attach($userA->id, ['role' => 'member']);
        $wsA = Workspace::factory()->create(['organization_id' => $orgA->id]);
        $role = Role::where('name', 'workspace-member')->first();
        $userA->workspaces()->attach($wsA->id, ['role_id' => $role->id]);

        $orgB = Organization::factory()->create(['owner_id' => $userB->id]);
        $orgB->members()->attach($userB->id, ['role' => 'member']);
        $wsB = Workspace::factory()->create(['organization_id' => $orgB->id]);
        $userB->workspaces()->attach($wsB->id, ['role_id' => $role->id]);

        $notificationB = DatabaseNotification::create([
            'id' => (string) Str::uuid(),
            'type' => 'App\\Notifications\\SystemAlert',
            'notifiable_type' => User::class,
            'notifiable_id' => $userB->id,
            'organization_id' => $orgB->id,
            'workspace_id' => $wsB->id,
            'data' => ['title' => 'Secret', 'message' => 'For User B only'],
            'read_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // User A attempts to mark User B's notification as read
        $response = $this->actingAs($userA)
            ->withSession(['active_organization_id' => $orgA->id, 'active_workspace_id' => $wsA->id])
            ->post("/notifications/{$notificationB->id}/read");

        $this->assertContains($response->getStatusCode(), [403, 404]);
        $this->assertNull($notificationB->fresh()->read_at);
    }

    public function test_every_frontend_permission_exists_in_permission_catalog(): void
    {
        $navContent = File::get(resource_path('js/Navigation/NavigationRegistry.ts'));
        $actionContent = File::get(resource_path('js/Navigation/ActionRegistry.ts'));
        $foxContent = File::get(resource_path('js/Components/MrFox/MrFoxPanel.tsx'));

        $combined = $navContent . "\n" . $actionContent . "\n" . $foxContent;

        preg_match_all("/permission:\s*['\"]([^'\"]+)['\"]/", $combined, $matches);
        $frontendPermissions = array_unique($matches[1]);

        $this->assertNotEmpty($frontendPermissions, 'Frontend permissions list must not be empty.');

        $catalogPermissions = Permission::pluck('name')->toArray();

        foreach ($frontendPermissions as $perm) {
            $this->assertContains(
                $perm,
                $catalogPermissions,
                "Frontend permission '{$perm}' does not exist in the database permission catalog."
            );
        }
    }

    public function test_every_navigation_and_action_href_resolves_to_valid_route(): void
    {
        $navContent = File::get(resource_path('js/Navigation/NavigationRegistry.ts'));
        $actionContent = File::get(resource_path('js/Navigation/ActionRegistry.ts'));
        $foxContent = File::get(resource_path('js/Components/MrFox/MrFoxPanel.tsx'));

        $combined = $navContent . "\n" . $actionContent . "\n" . $foxContent;

        preg_match_all("/href:\s*['\"]([^'\"]+)['\"]/", $combined, $matches);
        $frontendHrefs = array_unique($matches[1]);

        $this->assertNotEmpty($frontendHrefs, 'Frontend href list must not be empty.');

        $registeredRoutes = collect(Route::getRoutes()->getRoutes())
            ->filter(fn ($r) => in_array('GET', $r->methods()))
            ->map(fn ($r) => '/' . ltrim($r->uri(), '/'))
            ->toArray();

        foreach ($frontendHrefs as $href) {
            $normalized = '/' . ltrim(parse_url($href, PHP_URL_PATH), '/');
            $hasMatchingRoute = collect($registeredRoutes)->contains(function ($routeUri) use ($normalized) {
                if ($routeUri === $normalized) {
                    return true;
                }
                $pattern = preg_replace('/\{[^}]+\}/', '[^/]+', $routeUri);
                return (bool) preg_match('#^' . $pattern . '$#', $normalized);
            });

            $this->assertTrue(
                $hasMatchingRoute,
                "Frontend href '{$href}' does not resolve to any registered GET route in Laravel."
            );
        }
    }

    public function test_theme_tokens_and_data_attributes_deterministic(): void
    {
        $css = File::get(resource_path('css/app.css'));
        $this->assertStringContainsString('[data-theme="dark"]', $css);
        $this->assertStringContainsString('[data-theme="light"]', $css);
        $this->assertStringContainsString('--bg-0:', $css);
        $this->assertStringContainsString('--surface-1:', $css);

        $appBlade = File::get(resource_path('views/app.blade.php'));
        $this->assertStringContainsString('data-theme="dark"', $appBlade);
    }
}

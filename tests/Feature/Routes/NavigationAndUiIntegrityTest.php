<?php

namespace Tests\Feature\Routes;

use App\Models\Organization;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class NavigationAndUiIntegrityTest extends TestCase
{
    use RefreshDatabase;

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
}

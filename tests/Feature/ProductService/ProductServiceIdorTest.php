<?php

namespace Tests\Feature\ProductService;

use App\Models\Organization;
use App\Models\Permission;
use App\Models\ProductServiceCategory;
use App\Models\ProductServiceItem;
use App\Models\ProductServiceTax;
use App\Models\ProductServiceUnit;
use App\Models\Role;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductServiceIdorTest extends TestCase
{
    use RefreshDatabase;

    private User $tenantAUser;
    private Workspace $tenantAWorkspace;
    private Organization $tenantAOrg;

    private User $tenantBUser;
    private Workspace $tenantBWorkspace;
    private Organization $tenantBOrg;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::firstOrCreate(
            ['name' => 'workspace-admin', 'organization_id' => null],
            ['display_name' => 'Workspace Admin', 'is_system' => true]
        );
        $role->permissions()->sync(Permission::pluck('id')->toArray());

        // Tenant A
        $this->tenantAUser = User::factory()->create(['role' => 'company_admin']);
        $planA = \App\Models\Plan::create(['name' => 'Enterprise A', 'status' => true, 'modules' => ['productservice', 'account', 'pos'], 'created_by' => $this->tenantAUser->id]);
        $this->tenantAOrg = Organization::factory()->create(['owner_id' => $this->tenantAUser->id, 'plan_id' => $planA->id]);
        $this->tenantAWorkspace = Workspace::factory()->create(['organization_id' => $this->tenantAOrg->id, 'created_by' => $this->tenantAUser->id]);
        $this->tenantAOrg->members()->attach($this->tenantAUser, ['role' => 'owner']);
        $this->tenantAWorkspace->members()->attach($this->tenantAUser);
        \App\Models\UserActiveModule::create(['workspace_id' => $this->tenantAWorkspace->id, 'module_name' => 'productservice']);

        // Tenant B
        $this->tenantBUser = User::factory()->create(['role' => 'company_admin']);
        $planB = \App\Models\Plan::create(['name' => 'Enterprise B', 'status' => true, 'modules' => ['productservice', 'account', 'pos'], 'created_by' => $this->tenantBUser->id]);
        $this->tenantBOrg = Organization::factory()->create(['owner_id' => $this->tenantBUser->id, 'plan_id' => $planB->id]);
        $this->tenantBWorkspace = Workspace::factory()->create(['organization_id' => $this->tenantBOrg->id, 'created_by' => $this->tenantBUser->id]);
        $this->tenantBOrg->members()->attach($this->tenantBUser, ['role' => 'owner']);
        $this->tenantBWorkspace->members()->attach($this->tenantBUser);
        \App\Models\UserActiveModule::create(['workspace_id' => $this->tenantBWorkspace->id, 'module_name' => 'productservice']);
    }

    public function test_tenant_b_cannot_view_or_modify_tenant_a_product(): void
    {
        $productA = ProductServiceItem::create([
            'name' => 'Tenant A Secret Product',
            'sku' => 'SEC-001',
            'type' => 'product',
            'sale_price' => 500.00,
            'organization_id' => $this->tenantAOrg->id,
            'workspace_id' => $this->tenantAWorkspace->id,
            'created_by' => $this->tenantAUser->id,
        ]);

        // Tenant B attempts to view Tenant A product -> 404
        $viewResponse = $this->actingAs($this->tenantBUser)
            ->withSession(['active_workspace_id' => $this->tenantBWorkspace->id])
            ->get(route('product-service.show', $productA));
        $viewResponse->assertStatus(404);

        // Tenant B attempts to edit Tenant A product -> 404
        $editResponse = $this->actingAs($this->tenantBUser)
            ->withSession(['active_workspace_id' => $this->tenantBWorkspace->id])
            ->get(route('product-service.edit', $productA));
        $editResponse->assertStatus(404);

        // Tenant B attempts to update Tenant A product -> 404
        $updateResponse = $this->actingAs($this->tenantBUser)
            ->withSession(['active_workspace_id' => $this->tenantBWorkspace->id])
            ->put(route('product-service.update', $productA), [
                'name' => 'Hijacked Name',
                'sku' => 'SEC-001',
                'type' => 'product',
                'sale_price' => 1.00,
            ]);
        $updateResponse->assertStatus(404);

        // Tenant B attempts to delete Tenant A product -> 404
        $deleteResponse = $this->actingAs($this->tenantBUser)
            ->withSession(['active_workspace_id' => $this->tenantBWorkspace->id])
            ->delete(route('product-service.destroy', $productA));
        $deleteResponse->assertStatus(404);

        $this->assertEquals('Tenant A Secret Product', $productA->fresh()->name);
    }

    public function test_tenant_b_cannot_adjust_stock_with_tenant_a_warehouse(): void
    {
        $whA = Warehouse::create([
            'name' => 'Tenant A Warehouse',
            'organization_id' => $this->tenantAOrg->id,
            'workspace_id' => $this->tenantAWorkspace->id,
            'created_by' => $this->tenantAUser->id,
        ]);

        $productB = ProductServiceItem::create([
            'name' => 'Tenant B Product',
            'sku' => 'TB-001',
            'type' => 'product',
            'sale_price' => 10.00,
            'organization_id' => $this->tenantBOrg->id,
            'workspace_id' => $this->tenantBWorkspace->id,
            'created_by' => $this->tenantBUser->id,
        ]);

        // Tenant B attempts to adjust stock of productB into whA -> 404 (whA not found in tenant B workspace)
        $response = $this->actingAs($this->tenantBUser)
            ->withSession(['active_workspace_id' => $this->tenantBWorkspace->id])
            ->post(route('product-service.adjust-stock', $productB), [
                'warehouse_id' => $whA->id,
                'quantity' => 100,
                'reason' => 'IDOR Attempt',
            ]);

        $response->assertStatus(404);
    }

    public function test_tenant_b_cannot_modify_tenant_a_category_unit_or_tax(): void
    {
        $catA = ProductServiceCategory::create([
            'name' => 'Category A',
            'type' => 'product',
            'organization_id' => $this->tenantAOrg->id,
            'workspace_id' => $this->tenantAWorkspace->id,
            'created_by' => $this->tenantAUser->id,
        ]);

        $unitA = ProductServiceUnit::create([
            'name' => 'Unit A',
            'symbol' => 'UA',
            'organization_id' => $this->tenantAOrg->id,
            'workspace_id' => $this->tenantAWorkspace->id,
        ]);

        $taxA = ProductServiceTax::create([
            'name' => 'Tax A',
            'rate' => 10.0,
            'organization_id' => $this->tenantAOrg->id,
            'workspace_id' => $this->tenantAWorkspace->id,
        ]);

        $this->actingAs($this->tenantBUser)
            ->withSession(['active_workspace_id' => $this->tenantBWorkspace->id])
            ->put(route('product-service.categories.update', $catA), ['name' => 'Hacked Cat'])
            ->assertStatus(404);

        $this->actingAs($this->tenantBUser)
            ->withSession(['active_workspace_id' => $this->tenantBWorkspace->id])
            ->put(route('product-service.units.update', $unitA), ['name' => 'Hacked Unit', 'symbol' => 'HU'])
            ->assertStatus(404);

        $this->actingAs($this->tenantBUser)
            ->withSession(['active_workspace_id' => $this->tenantBWorkspace->id])
            ->put(route('product-service.taxes.update', $taxA), ['name' => 'Hacked Tax', 'rate' => 0.0])
            ->assertStatus(404);
    }
}

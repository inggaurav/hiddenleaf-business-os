<?php

namespace Tests\Feature\POS;

use App\Models\Organization;
use App\Models\Plan;
use App\Models\POS\BillingCounter;
use App\Models\POS\PosSale;
use App\Models\POS\PosSaleItem;
use App\Models\ProductServiceItem;
use App\Models\User;
use App\Models\UserActiveModule;
use App\Models\Warehouse;
use App\Models\WarehouseStock;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PosReportsTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Organization $organization;
    private Workspace $workspace;
    private Warehouse $warehouse;
    private BillingCounter $counter;
    private ProductServiceItem $product;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->user = User::factory()->create(['role' => 'company_admin']);
        $plan = Plan::create([
            'name' => 'Enterprise',
            'status' => true,
            'modules' => ['pos', 'productservice'],
            'created_by' => $this->user->id,
        ]);
        $this->organization = Organization::factory()->create(['owner_id' => $this->user->id, 'plan_id' => $plan->id]);
        $this->workspace = Workspace::factory()->create(['organization_id' => $this->organization->id, 'created_by' => $this->user->id]);

        $this->organization->members()->attach($this->user, ['role' => 'owner']);
        $this->workspace->members()->attach($this->user);

        UserActiveModule::create(['workspace_id' => $this->workspace->id, 'module_name' => 'pos']);
        UserActiveModule::create(['workspace_id' => $this->workspace->id, 'module_name' => 'productservice']);

        $this->warehouse = Warehouse::create([
            'organization_id' => $this->organization->id,
            'workspace_id' => $this->workspace->id,
            'name' => 'Outlet',
            'created_by' => $this->user->id,
        ]);

        $this->counter = BillingCounter::create([
            'organization_id' => $this->organization->id,
            'workspace_id' => $this->workspace->id,
            'warehouse_id' => $this->warehouse->id,
            'name' => 'Counter 1',
            'counter_number' => 'C-01',
            'created_by' => $this->user->id,
        ]);

        $this->product = ProductServiceItem::create([
            'organization_id' => $this->organization->id,
            'workspace_id' => $this->workspace->id,
            'name' => 'USB Cable',
            'sku' => 'USB-01',
            'type' => 'product',
            'sale_price' => 15.00,
            'purchase_price' => 5.00,
            'is_active' => true,
            'created_by' => $this->user->id,
        ]);
        WarehouseStock::create(['warehouse_id' => $this->warehouse->id, 'product_id' => $this->product->id, 'quantity' => '50.0000']);

        // Execute sale
        $this->actingAs($this->user)->withSession([
            'active_organization_id' => $this->organization->id,
            'active_workspace_id' => $this->workspace->id,
        ])->postJson('/pos/store', [
            'billing_counter_id' => $this->counter->id,
            'warehouse_id' => $this->warehouse->id,
            'payment_method' => 'cash',
            'items' => [['product_id' => $this->product->id, 'quantity' => 4]],
        ]);
    }

    public function test_all_pos_reports_return_valid_data(): void
    {
        $session = [
            'active_organization_id' => $this->organization->id,
            'active_workspace_id' => $this->workspace->id,
        ];

        // 1. Sales report
        $salesRes = $this->actingAs($this->user)->withSession($session)->get('/pos/reports/sales');
        $salesRes->assertOk();
        $salesProps = $salesRes->getOriginalContent()->getData()['page']['props'];
        $this->assertEquals(60.0, $salesProps['summary']['total_sales']);

        // 2. Products report
        $prodRes = $this->actingAs($this->user)->withSession($session)->get('/pos/reports/products');
        $prodRes->assertOk();

        // 3. Customers report
        $custRes = $this->actingAs($this->user)->withSession($session)->get('/pos/reports/customers');
        $custRes->assertOk();
    }
}

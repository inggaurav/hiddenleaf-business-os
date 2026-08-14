<?php

namespace Tests\Feature\POS;

use App\Models\Organization;
use App\Models\Plan;
use App\Models\POS\BillingCounter;
use App\Models\ProductServiceItem;
use App\Models\User;
use App\Models\UserActiveModule;
use App\Models\Warehouse;
use App\Models\WarehouseStock;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PosReceiptTest extends TestCase
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
            'name' => 'Front Desk',
            'counter_number' => 'FD-01',
            'created_by' => $this->user->id,
        ]);

        $this->product = ProductServiceItem::create([
            'organization_id' => $this->organization->id,
            'workspace_id' => $this->workspace->id,
            'name' => 'Desk Lamp',
            'sku' => 'DL-01',
            'type' => 'product',
            'sale_price' => 45.00,
            'purchase_price' => 20.00,
            'is_active' => true,
            'created_by' => $this->user->id,
        ]);
        WarehouseStock::create(['warehouse_id' => $this->warehouse->id, 'product_id' => $this->product->id, 'quantity' => '10.0000']);
    }

    public function test_receipt_print_endpoint_loads_sale_data(): void
    {
        $session = [
            'active_organization_id' => $this->organization->id,
            'active_workspace_id' => $this->workspace->id,
        ];

        $checkoutResp = $this->actingAs($this->user)->withSession($session)->postJson('/pos/store', [
            'billing_counter_id' => $this->counter->id,
            'warehouse_id' => $this->warehouse->id,
            'payment_method' => 'card',
            'items' => [['product_id' => $this->product->id, 'quantity' => 2]],
        ]);
        $checkoutResp->assertOk();
        $saleId = $checkoutResp->json('sale.id');

        $response = $this->actingAs($this->user)->withSession($session)->get("/pos/orders/{$saleId}/print");
        $response->assertOk();

        $pageProps = $response->getOriginalContent()->getData()['page']['props'];
        $this->assertSame('receipt', $pageProps['type']);
        $this->assertEquals($saleId, $pageProps['sale']['id']);
        $this->assertEquals('Front Desk', $pageProps['sale']['counter']['name']);
        $this->assertEquals(90.0, (float) $pageProps['sale']['total']);
    }
}

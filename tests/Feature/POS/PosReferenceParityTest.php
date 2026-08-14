<?php

namespace Tests\Feature\POS;

use App\Models\Organization;
use App\Models\Plan;
use App\Models\POS\BillingCounter;
use App\Models\POS\PosDiscount;
use App\Models\POS\PosReturn;
use App\Models\POS\PosSale;
use App\Models\ProductServiceItem;
use App\Models\User;
use App\Models\UserActiveModule;
use App\Models\Warehouse;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PosReferenceParityTest extends TestCase
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
            'modules' => ['pos', 'productservice', 'account'],
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
            'name' => 'Main Outlet',
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
            'name' => 'Keyboard',
            'sku' => 'KB-01',
            'type' => 'product',
            'sale_price' => 50.00,
            'purchase_price' => 25.00,
            'is_active' => true,
            'created_by' => $this->user->id,
        ]);
    }

    public function test_all_pos_routes_accessible(): void
    {
        $session = [
            'active_organization_id' => $this->organization->id,
            'active_workspace_id' => $this->workspace->id,
        ];

        // 1. pos.index (dashboard)
        $this->actingAs($this->user)->withSession($session)->get('/pos/dashboard')->assertOk();

        // 2. pos.orders
        $this->actingAs($this->user)->withSession($session)->get('/pos/orders')->assertOk();

        // 3. pos.create
        $this->actingAs($this->user)->withSession($session)->get('/pos/create')->assertOk();

        // 4. pos.products
        $this->actingAs($this->user)->withSession($session)->getJson('/pos/products')->assertOk();

        // 5. pos.pos-number
        $this->actingAs($this->user)->withSession($session)->getJson('/pos/pos-number')->assertOk();

        // Create a dummy sale for show, barcode, print
        $sale = PosSale::create([
            'organization_id' => $this->organization->id,
            'workspace_id' => $this->workspace->id,
            'sale_number' => 'POS-20260814-00001',
            'billing_counter_id' => $this->counter->id,
            'warehouse_id' => $this->warehouse->id,
            'cashier_id' => $this->user->id,
            'subtotal' => '50.0000',
            'tax_amount' => '0.0000',
            'discount_amount' => '0.0000',
            'total' => '50.0000',
            'payment_method' => 'cash',
            'status' => 'completed',
            'idempotency_key' => 'test-key-1',
            'created_by' => $this->user->id,
        ]);

        // 6. pos.show
        $this->actingAs($this->user)->withSession($session)->get("/pos/orders/{$sale->id}")->assertOk();

        // 7. pos.barcode
        $this->actingAs($this->user)->withSession($session)->get('/pos/barcode')->assertOk();

        // 8. pos.barcode.print
        $this->actingAs($this->user)->withSession($session)->get("/pos/barcode/{$sale->id}")->assertOk();

        // 9. pos-orders.print
        $this->actingAs($this->user)->withSession($session)->get("/pos/orders/{$sale->id}/print")->assertOk();

        // 10. pos.billing-counters
        $this->actingAs($this->user)->withSession($session)->get('/pos/billing-counters')->assertOk();

        // 11. pos.discounts.index
        $this->actingAs($this->user)->withSession($session)->get('/pos/discounts')->assertOk();

        // 12. pos.discounts.create
        $this->actingAs($this->user)->withSession($session)->get('/pos/discounts/create')->assertOk();

        $discount = PosDiscount::create([
            'organization_id' => $this->organization->id,
            'workspace_id' => $this->workspace->id,
            'name' => '10% Off',
            'type' => 'percentage',
            'value' => '10.0000',
            'created_by' => $this->user->id,
        ]);

        // 13. pos.discounts.show
        $this->actingAs($this->user)->withSession($session)->get("/pos/discounts/{$discount->id}")->assertOk();

        // 14. pos.discounts.edit
        $this->actingAs($this->user)->withSession($session)->get("/pos/discounts/{$discount->id}/edit")->assertOk();

        // 15. pos.reports.sales
        $this->actingAs($this->user)->withSession($session)->get('/pos/reports/sales')->assertOk();

        // 16. pos.reports.products
        $this->actingAs($this->user)->withSession($session)->get('/pos/reports/products')->assertOk();

        // 17. pos.reports.customers
        $this->actingAs($this->user)->withSession($session)->get('/pos/reports/customers')->assertOk();

        // 18. pos.returns.index
        $this->actingAs($this->user)->withSession($session)->get('/pos/returns')->assertOk();

        // 19. pos.returns.create
        $this->actingAs($this->user)->withSession($session)->get('/pos/returns/create')->assertOk();

        $return = PosReturn::create([
            'organization_id' => $this->organization->id,
            'workspace_id' => $this->workspace->id,
            'pos_sale_id' => $sale->id,
            'return_number' => 'RET-20260814-00001',
            'status' => 'draft',
            'refund_amount' => '50.0000',
            'created_by' => $this->user->id,
        ]);

        // 20. pos.returns.show
        $this->actingAs($this->user)->withSession($session)->get("/pos/returns/{$return->id}")->assertOk();
    }
}

<?php

namespace Tests\Feature\Modules;

use App\Models\Organization;
use App\Models\Plan;
use App\Models\PosOrder;
use App\Models\PosRegister;
use App\Models\PosSession;
use App\Models\ProductServiceItem;
use App\Models\User;
use App\Models\UserActiveModule;
use App\Models\Warehouse;
use App\Models\WarehouseStock;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PosModuleTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Organization $organization;

    private Workspace $workspace;

    private Warehouse $warehouse;

    private ProductServiceItem $product;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->owner = User::factory()->create();
        $plan = Plan::create(['name' => 'POS', 'modules' => ['pos'], 'status' => true, 'created_by' => $this->owner->id]);
        $this->organization = Organization::factory()->create(['owner_id' => $this->owner->id, 'plan_id' => $plan->id]);
        $this->workspace = Workspace::factory()->create(['organization_id' => $this->organization->id, 'created_by' => $this->owner->id]);
        $this->organization->members()->attach($this->owner, ['role' => 'owner']);
        $this->workspace->members()->attach($this->owner);
        UserActiveModule::create(['workspace_id' => $this->workspace->id, 'module_name' => 'pos']);
        $this->warehouse = Warehouse::create(['name' => 'Shop', 'organization_id' => $this->organization->id, 'workspace_id' => $this->workspace->id]);
        $this->product = ProductServiceItem::create(['organization_id' => $this->organization->id, 'workspace_id' => $this->workspace->id, 'name' => 'Tea', 'sku' => 'TEA-1', 'barcode' => '8901', 'type' => 'product', 'sale_price' => 100, 'purchase_price' => 50, 'is_active' => true]);
        WarehouseStock::create(['warehouse_id' => $this->warehouse->id, 'product_id' => $this->product->id, 'quantity' => 10]);
    }

    public function test_register_checkout_receipt_cash_close_and_return(): void
    {
        $this->request()->post('/pos/registers', ['warehouse_id' => $this->warehouse->id, 'name' => 'Front Desk'])->assertSessionHasNoErrors();
        $register = PosRegister::sole();
        $this->request()->post("/pos/registers/{$register->id}/open", ['opening_cash' => 1000])->assertSessionHasNoErrors();
        $session = PosSession::sole();
        $this->request()->getJson('/pos/products?query=8901')->assertOk()->assertJsonFragment(['sku' => 'TEA-1']);
        $this->request()->post("/pos/sessions/{$session->id}/checkout", ['payment_method' => 'cash', 'paid_amount' => 250, 'items' => [['product_id' => $this->product->id, 'quantity' => 2, 'tax_percent' => 10, 'discount_percent' => 5]]])->assertSessionHasNoErrors();
        $order = PosOrder::with('items')->firstOrFail();
        $this->assertSame('209.00', $order->grand_total);
        $this->assertSame('41.00', $order->change_amount);
        $this->assertDatabaseHas('warehouse_stocks', ['warehouse_id' => $this->warehouse->id, 'product_id' => $this->product->id, 'quantity' => 8]);
        $this->request()->post("/pos/orders/{$order->id}/refund", ['reason' => 'Customer return', 'items' => [['order_item_id' => $order->items->first()->id, 'quantity' => 1]]])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('warehouse_stocks', ['warehouse_id' => $this->warehouse->id, 'product_id' => $this->product->id, 'quantity' => 9]);
        $this->request()->post("/pos/sessions/{$session->id}/close", ['closing_cash' => 1104.50])->assertSessionHasNoErrors();
        $this->assertSame('closed', $session->refresh()->status);
        $this->assertSame('0.00', $session->variance);
        $this->assertDatabaseHas('audit_logs', ['action' => 'pos.checkout', 'entity_id' => (string) $order->id]);
    }

    public function test_insufficient_stock_and_cross_tenant_register_are_rejected(): void
    {
        $register = PosRegister::create(['organization_id' => $this->organization->id, 'workspace_id' => $this->workspace->id, 'warehouse_id' => $this->warehouse->id, 'name' => 'Register']);
        $session = PosSession::create(['organization_id' => $this->organization->id, 'workspace_id' => $this->workspace->id, 'register_id' => $register->id, 'opened_by' => $this->owner->id, 'opening_cash' => 0, 'status' => 'open', 'opened_at' => now()]);
        $this->request()->post("/pos/sessions/{$session->id}/checkout", ['payment_method' => 'cash', 'paid_amount' => 2000, 'items' => [['product_id' => $this->product->id, 'quantity' => 20]]])->assertServerError();
        $this->assertDatabaseCount('pos_orders', 0);
        $other = Organization::factory()->create(['owner_id' => $this->owner->id]);
        $otherWs = Workspace::factory()->create(['organization_id' => $other->id]);
        $foreignWarehouse = Warehouse::create(['name' => 'Foreign', 'organization_id' => $other->id, 'workspace_id' => $otherWs->id]);
        $foreign = PosRegister::create(['organization_id' => $other->id, 'workspace_id' => $otherWs->id, 'warehouse_id' => $foreignWarehouse->id, 'name' => 'Foreign']);
        $this->request()->post("/pos/registers/{$foreign->id}/open", ['opening_cash' => 0])->assertNotFound();
    }

    private function request(): self
    {
        return $this->actingAs($this->owner)->withSession(['active_organization_id' => $this->organization->id, 'active_workspace_id' => $this->workspace->id]);
    }
}

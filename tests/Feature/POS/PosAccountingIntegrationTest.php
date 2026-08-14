<?php

namespace Tests\Feature\POS;

use App\Models\JournalEntry;
use App\Models\Organization;
use App\Models\Plan;
use App\Models\POS\BillingCounter;
use App\Models\POS\PosSale;
use App\Models\ProductServiceItem;
use App\Models\User;
use App\Models\UserActiveModule;
use App\Models\Warehouse;
use App\Models\WarehouseStock;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PosAccountingIntegrationTest extends TestCase
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
        $plan = Plan::create(['name' => 'Enterprise', 'status' => true, 'modules' => ['pos', 'productservice', 'account'], 'created_by' => $this->user->id]);
        $this->organization = Organization::factory()->create(['owner_id' => $this->user->id, 'plan_id' => $plan->id]);
        $this->workspace = Workspace::factory()->create(['organization_id' => $this->organization->id, 'created_by' => $this->user->id]);
        $this->organization->members()->attach($this->user, ['role' => 'owner']);
        $this->workspace->members()->attach($this->user);
        foreach (['pos', 'productservice', 'account'] as $module) {
            UserActiveModule::create(['workspace_id' => $this->workspace->id, 'module_name' => $module]);
        }

        $this->warehouse = Warehouse::create(['organization_id' => $this->organization->id, 'workspace_id' => $this->workspace->id, 'name' => 'Outlet', 'created_by' => $this->user->id]);
        $this->counter = BillingCounter::create(['organization_id' => $this->organization->id, 'workspace_id' => $this->workspace->id, 'warehouse_id' => $this->warehouse->id, 'name' => 'Counter 1', 'counter_number' => 'C-01', 'created_by' => $this->user->id]);
        $this->product = ProductServiceItem::create([
            'organization_id' => $this->organization->id,
            'workspace_id' => $this->workspace->id,
            'name' => 'Monitor',
            'sku' => 'MON-01',
            'type' => 'product',
            'sale_price' => 200.00,
            'purchase_price' => 120.00,
            'is_active' => true,
            'created_by' => $this->user->id,
        ]);
        WarehouseStock::create(['warehouse_id' => $this->warehouse->id, 'product_id' => $this->product->id, 'quantity' => '10.0000']);
    }

    public function test_pos_sale_posts_balanced_financial_entry_and_payment_method(): void
    {
        $response = $this->actingAs($this->user)->withSession([
            'active_organization_id' => $this->organization->id,
            'active_workspace_id' => $this->workspace->id,
        ])->postJson('/pos/store', [
            'billing_counter_id' => $this->counter->id,
            'warehouse_id' => $this->warehouse->id,
            'payment_method' => 'card',
            'payment_reference' => 'TXN-CARD-999',
            'idempotency_key' => 'pos-accounting-card-1',
            'items' => [['product_id' => $this->product->id, 'quantity' => 1]],
        ]);

        $response->assertOk();

        $sale = PosSale::sole();
        $this->assertSame('card', $sale->payment_method);
        $this->assertSame('TXN-CARD-999', $sale->payment_reference);
        $this->assertEquals(200.0, (float) $sale->total);
        $this->assertSame('completed', $sale->status);
        $this->assertNotNull($sale->journal_entry_id);

        $entry = JournalEntry::with('lines')->findOrFail($sale->journal_entry_id);
        $this->assertSame('posted', $entry->status);
        $this->assertSame('POS-'.$sale->sale_number, $entry->reference);
        $this->assertEquals(
            (float) $entry->lines->sum('debit'),
            (float) $entry->lines->sum('credit')
        );
        $this->assertEquals(200.0, (float) $entry->lines->sum('debit'));
    }
}

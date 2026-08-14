<?php

namespace Tests\Feature\POS;

use App\Models\AccountCustomer;
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
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

class PosHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_foreign_customer_id_cannot_be_used_at_checkout(): void
    {
        $a = $this->tenant('A');
        $b = $this->tenant('B');
        $foreignCustomer = AccountCustomer::create(['organization_id' => $b['org']->id, 'workspace_id' => $b['workspace']->id, 'name' => 'Foreign Customer']);

        $this->postCheckout($a, [
            'customer_id' => $foreignCustomer->id,
            'idempotency_key' => 'foreign-customer-attempt',
        ])->assertNotFound();

        $this->assertSame(0, PosSale::where('workspace_id', $a['workspace']->id)->count());
        $this->assertSame('5.0000', $a['stock']->fresh()->quantity);
    }

    public function test_counter_bound_to_one_warehouse_cannot_checkout_from_another(): void
    {
        $tenant = $this->tenant('A');
        $otherWarehouse = Warehouse::create(['organization_id' => $tenant['org']->id, 'workspace_id' => $tenant['workspace']->id, 'name' => 'Wrong Warehouse', 'created_by' => $tenant['user']->id]);
        WarehouseStock::create(['warehouse_id' => $otherWarehouse->id, 'product_id' => $tenant['product']->id, 'quantity' => '5.0000']);

        $response = $this->postCheckout($tenant, [
            'warehouse_id' => $otherWarehouse->id,
            'idempotency_key' => 'wrong-counter-warehouse',
        ]);

        $this->assertGreaterThanOrEqual(400, $response->getStatusCode());
        $this->assertSame(0, PosSale::where('workspace_id', $tenant['workspace']->id)->count());
        $this->assertSame('5.0000', WarehouseStock::where('warehouse_id', $otherWarehouse->id)->where('product_id', $tenant['product']->id)->firstOrFail()->quantity);
    }

    public function test_completed_sale_is_immutable_after_accounting_link(): void
    {
        $tenant = $this->tenant('A');
        $saleId = $this->postCheckout($tenant, ['idempotency_key' => 'immutable-sale'])->assertOk()->json('sale.id');
        $sale = PosSale::findOrFail($saleId);
        $this->assertNotNull($sale->journal_entry_id);

        $this->expectException(RuntimeException::class);
        $sale->update(['notes' => 'Attempted mutation']);
    }

    public function test_pos_schema_has_scoped_uniques_and_core_migration_is_non_destructive(): void
    {
        $saleIndexes = collect(Schema::getIndexes('pos_sales'));
        $this->assertTrue($saleIndexes->contains(fn (array $index) => ($index['unique'] ?? false) && ($index['columns'] ?? []) === ['workspace_id', 'idempotency_key']));
        $this->assertTrue($saleIndexes->contains(fn (array $index) => ($index['unique'] ?? false) && ($index['columns'] ?? []) === ['workspace_id', 'sale_number']));

        $numberIndexes = collect(Schema::getIndexes('pos_numbers'));
        $this->assertTrue($numberIndexes->contains(fn (array $index) => ($index['unique'] ?? false) && ($index['columns'] ?? []) === ['workspace_id', 'date']));

        $core = file_get_contents(database_path('migrations/2026_08_14_000009_create_pos_core_tables.php'));
        $this->assertStringNotContainsString("Schema::dropIfExists('pos_orders')", $core);
        $this->assertStringNotContainsString("Schema::dropIfExists('pos_sessions')", $core);
        $this->assertStringNotContainsString("Schema::dropIfExists('pos_registers')", $core);
        $this->assertStringContainsString('legacy_pos_returns', $core);

        $hardening = file_get_contents(database_path('migrations/2026_08_14_140000_harden_inventory_and_pos_integrity.php'));
        $this->assertStringContainsString('migrateLegacyPosData', $hardening);
        $this->assertStringContainsString('pos_sales_workspace_idempotency_unique', $hardening);
    }

    private function tenant(string $suffix): array
    {
        $user = User::factory()->create(['role' => 'company_admin']);
        $plan = Plan::create(['name' => 'POS Hardening '.$suffix, 'status' => true, 'modules' => ['pos', 'productservice', 'account'], 'created_by' => $user->id]);
        $org = Organization::factory()->create(['owner_id' => $user->id, 'plan_id' => $plan->id]);
        $workspace = Workspace::factory()->create(['organization_id' => $org->id, 'created_by' => $user->id]);
        $org->members()->attach($user, ['role' => 'owner']);
        $workspace->members()->attach($user);
        foreach (['pos', 'productservice', 'account'] as $module) {
            UserActiveModule::create(['workspace_id' => $workspace->id, 'module_name' => $module]);
        }
        $warehouse = Warehouse::create(['organization_id' => $org->id, 'workspace_id' => $workspace->id, 'name' => 'Warehouse '.$suffix, 'created_by' => $user->id]);
        $counter = BillingCounter::create(['organization_id' => $org->id, 'workspace_id' => $workspace->id, 'warehouse_id' => $warehouse->id, 'name' => 'Counter '.$suffix, 'counter_number' => 'HC-'.$suffix, 'created_by' => $user->id]);
        $product = ProductServiceItem::create([
            'organization_id' => $org->id,
            'workspace_id' => $workspace->id,
            'name' => 'Hardening Product '.$suffix,
            'sku' => 'HARD-'.$suffix,
            'type' => 'product',
            'sale_price' => '20.00',
            'purchase_price' => '10.00',
            'is_active' => true,
            'created_by' => $user->id,
        ]);
        $stock = WarehouseStock::create(['warehouse_id' => $warehouse->id, 'product_id' => $product->id, 'quantity' => '5.0000']);

        return compact('user', 'org', 'workspace', 'warehouse', 'counter', 'product', 'stock');
    }

    private function postCheckout(array $tenant, array $overrides = [])
    {
        $payload = array_replace([
            'billing_counter_id' => $tenant['counter']->id,
            'warehouse_id' => $tenant['warehouse']->id,
            'payment_method' => 'cash',
            'idempotency_key' => 'hardening-default-key',
            'items' => [['product_id' => $tenant['product']->id, 'quantity' => '1.0000']],
        ], $overrides);

        return $this->actingAs($tenant['user'])->withSession([
            'active_organization_id' => $tenant['org']->id,
            'active_workspace_id' => $tenant['workspace']->id,
        ])->postJson('/pos/store', $payload);
    }
}

<?php

namespace Tests\Feature\ProductService;

use App\Models\Organization;
use App\Models\Plan;
use App\Models\ProductServiceCategory;
use App\Models\ProductServiceItem;
use App\Models\ProductServiceTax;
use App\Models\ProductServiceUnit;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\UserActiveModule;
use App\Models\Warehouse;
use App\Models\WarehouseStock;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductServiceReferenceParityTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Workspace $workspace;

    private Organization $organization;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['role' => 'company_admin']);
        $plan = Plan::create(['name' => 'Enterprise', 'status' => true, 'modules' => ['productservice', 'account', 'pos'], 'created_by' => $this->user->id]);
        $this->organization = Organization::factory()->create(['owner_id' => $this->user->id, 'plan_id' => $plan->id]);
        $this->workspace = Workspace::factory()->create(['organization_id' => $this->organization->id, 'created_by' => $this->user->id]);

        $this->organization->members()->attach($this->user, ['role' => 'owner']);
        $this->workspace->members()->attach($this->user);

        UserActiveModule::create(['workspace_id' => $this->workspace->id, 'module_name' => 'productservice']);
    }

    public function test_product_service_catalog_crud_and_parity(): void
    {
        $category = ProductServiceCategory::create([
            'name' => 'Hardware',
            'type' => 'product',
            'color' => '#10b981',
            'organization_id' => $this->organization->id,
            'workspace_id' => $this->workspace->id,
            'created_by' => $this->user->id,
        ]);

        $unit = ProductServiceUnit::create([
            'name' => 'Piece',
            'symbol' => 'PCS',
            'organization_id' => $this->organization->id,
            'workspace_id' => $this->workspace->id,
        ]);

        $tax = ProductServiceTax::create([
            'name' => 'VAT 10%',
            'rate' => 10.0,
            'is_compound' => false,
            'organization_id' => $this->organization->id,
            'workspace_id' => $this->workspace->id,
        ]);

        // 1. Index
        $response = $this->actingAs($this->user)
            ->withSession(['active_workspace_id' => $this->workspace->id])
            ->get(route('product-service.index'));
        $response->assertOk();

        // 2. Create
        $createResponse = $this->actingAs($this->user)
            ->withSession(['active_workspace_id' => $this->workspace->id])
            ->get(route('product-service.create'));
        $createResponse->assertOk();

        // 3. Store Product
        $storeResponse = $this->actingAs($this->user)
            ->withSession(['active_workspace_id' => $this->workspace->id])
            ->post(route('product-service.store'), [
                'name' => 'Wireless Mouse',
                'sku' => 'MOU-001',
                'barcode' => '8901234567890',
                'description' => 'Ergonomic 2.4GHz mouse',
                'type' => 'product',
                'sale_price' => 29.99,
                'purchase_price' => 14.50,
                'reorder_level' => 10,
                'unit' => 'Piece',
                'category_id' => $category->id,
                'tax_ids' => [$tax->id],
                'is_active' => true,
            ]);
        $storeResponse->assertRedirect(route('product-service.index'));

        $product = ProductServiceItem::where('sku', 'MOU-001')->firstOrFail();
        $this->assertEquals('Wireless Mouse', $product->name);
        $this->assertEquals('product', $product->type);
        $this->assertEquals(1, $product->taxes()->count());

        // 4. Store Service (Services have no warehouse inventory)
        $storeService = $this->actingAs($this->user)
            ->withSession(['active_workspace_id' => $this->workspace->id])
            ->post(route('product-service.store'), [
                'name' => 'Installation Service',
                'sku' => 'SRV-INST',
                'type' => 'service',
                'sale_price' => 99.00,
                'purchase_price' => 0.00,
                'category_id' => $category->id,
                'is_active' => true,
            ]);
        $storeService->assertRedirect(route('product-service.index'));
        $service = ProductServiceItem::where('sku', 'SRV-INST')->firstOrFail();
        $this->assertEquals('service', $service->type);

        // 5. Show
        $showResponse = $this->actingAs($this->user)
            ->withSession(['active_workspace_id' => $this->workspace->id])
            ->get(route('product-service.show', $product));
        $showResponse->assertOk();

        // 6. Edit & Update
        $editResponse = $this->actingAs($this->user)
            ->withSession(['active_workspace_id' => $this->workspace->id])
            ->get(route('product-service.edit', $product));
        $editResponse->assertOk();

        $updateResponse = $this->actingAs($this->user)
            ->withSession(['active_workspace_id' => $this->workspace->id])
            ->put(route('product-service.update', $product), [
                'name' => 'Wireless Mouse Pro',
                'sku' => 'MOU-001',
                'type' => 'product',
                'sale_price' => 34.99,
                'purchase_price' => 16.00,
                'category_id' => $category->id,
                'tax_ids' => [$tax->id],
                'is_active' => true,
            ]);
        $updateResponse->assertRedirect(route('product-service.index'));
        $this->assertEquals('Wireless Mouse Pro', $product->fresh()->name);
        $this->assertEquals(34.99, (float) $product->fresh()->sale_price);
    }

    public function test_category_unit_tax_workflows(): void
    {
        // Category CRUD
        $catStore = $this->actingAs($this->user)
            ->withSession(['active_workspace_id' => $this->workspace->id])
            ->post(route('product-service.categories.store'), [
                'name' => 'Electronics',
                'type' => 'product',
                'color' => '#3b82f6',
            ]);
        $catStore->assertRedirect();
        $category = ProductServiceCategory::where('name', 'Electronics')->firstOrFail();

        $catUpdate = $this->actingAs($this->user)
            ->withSession(['active_workspace_id' => $this->workspace->id])
            ->put(route('product-service.categories.update', $category), [
                'name' => 'Consumer Electronics',
                'type' => 'product',
                'color' => '#6366f1',
            ]);
        $catUpdate->assertRedirect();
        $this->assertEquals('Consumer Electronics', $category->fresh()->name);

        // Unit CRUD
        $unitStore = $this->actingAs($this->user)
            ->withSession(['active_workspace_id' => $this->workspace->id])
            ->post(route('product-service.units.store'), [
                'name' => 'Kilogram',
                'symbol' => 'KG',
            ]);
        $unitStore->assertRedirect();
        $unit = ProductServiceUnit::where('name', 'Kilogram')->firstOrFail();

        $unitUpdate = $this->actingAs($this->user)
            ->withSession(['active_workspace_id' => $this->workspace->id])
            ->put(route('product-service.units.update', $unit), [
                'name' => 'Kilogram Net',
                'symbol' => 'KG',
            ]);
        $unitUpdate->assertRedirect();
        $this->assertEquals('Kilogram Net', $unit->fresh()->name);

        // Tax CRUD
        $taxStore = $this->actingAs($this->user)
            ->withSession(['active_workspace_id' => $this->workspace->id])
            ->post(route('product-service.taxes.store'), [
                'name' => 'GST 18%',
                'rate' => 18.0,
                'is_compound' => false,
            ]);
        $taxStore->assertRedirect();
        $tax = ProductServiceTax::where('name', 'GST 18%')->firstOrFail();

        $taxUpdate = $this->actingAs($this->user)
            ->withSession(['active_workspace_id' => $this->workspace->id])
            ->put(route('product-service.taxes.update', $tax), [
                'name' => 'GST Standard 18%',
                'rate' => 18.0,
                'is_compound' => false,
            ]);
        $taxUpdate->assertRedirect();
        $this->assertEquals('GST Standard 18%', $tax->fresh()->name);
    }

    public function test_category_deletion_guard(): void
    {
        $category = ProductServiceCategory::create([
            'name' => 'Computers',
            'type' => 'product',
            'organization_id' => $this->organization->id,
            'workspace_id' => $this->workspace->id,
            'created_by' => $this->user->id,
        ]);

        $item = ProductServiceItem::create([
            'name' => 'Laptop X',
            'sku' => 'LAP-001',
            'type' => 'product',
            'sale_price' => 999.00,
            'category_id' => $category->id,
            'organization_id' => $this->organization->id,
            'workspace_id' => $this->workspace->id,
            'created_by' => $this->user->id,
        ]);

        $response = $this->actingAs($this->user)
            ->withSession(['active_workspace_id' => $this->workspace->id])
            ->delete(route('product-service.categories.destroy', $category));

        $response->assertStatus(422);
        $this->assertDatabaseHas('product_service_categories', ['id' => $category->id]);
    }

    public function test_unit_deletion_guard(): void
    {
        $unit = ProductServiceUnit::create([
            'name' => 'Box',
            'symbol' => 'BOX',
            'organization_id' => $this->organization->id,
            'workspace_id' => $this->workspace->id,
        ]);

        ProductServiceItem::create([
            'name' => 'Boxed Item',
            'sku' => 'BOX-001',
            'type' => 'product',
            'unit' => 'Box',
            'sale_price' => 10.00,
            'organization_id' => $this->organization->id,
            'workspace_id' => $this->workspace->id,
            'created_by' => $this->user->id,
        ]);

        $response = $this->actingAs($this->user)
            ->withSession(['active_workspace_id' => $this->workspace->id])
            ->delete(route('product-service.units.destroy', $unit));

        $response->assertStatus(422);
        $this->assertDatabaseHas('product_service_units', ['id' => $unit->id]);
    }

    public function test_tax_deletion_guard(): void
    {
        $tax = ProductServiceTax::create([
            'name' => 'Special Duty',
            'rate' => 5.0,
            'organization_id' => $this->organization->id,
            'workspace_id' => $this->workspace->id,
        ]);

        $item = ProductServiceItem::create([
            'name' => 'Taxed Product',
            'sku' => 'TAX-001',
            'type' => 'product',
            'sale_price' => 50.00,
            'organization_id' => $this->organization->id,
            'workspace_id' => $this->workspace->id,
            'created_by' => $this->user->id,
        ]);
        $item->taxes()->attach($tax->id);

        $response = $this->actingAs($this->user)
            ->withSession(['active_workspace_id' => $this->workspace->id])
            ->delete(route('product-service.taxes.destroy', $tax));

        $response->assertStatus(422);
        $this->assertDatabaseHas('product_service_taxes', ['id' => $tax->id]);
    }

    public function test_item_with_stock_cannot_be_deleted(): void
    {
        $warehouse = Warehouse::create([
            'name' => 'Main Hub',
            'organization_id' => $this->organization->id,
            'workspace_id' => $this->workspace->id,
            'created_by' => $this->user->id,
        ]);

        $item = ProductServiceItem::create([
            'name' => 'Stocked Widget',
            'sku' => 'STK-001',
            'type' => 'product',
            'sale_price' => 15.00,
            'organization_id' => $this->organization->id,
            'workspace_id' => $this->workspace->id,
            'created_by' => $this->user->id,
        ]);

        WarehouseStock::create([
            'warehouse_id' => $warehouse->id,
            'product_id' => $item->id,
            'quantity' => 25,
        ]);

        $response = $this->actingAs($this->user)
            ->withSession(['active_workspace_id' => $this->workspace->id])
            ->delete(route('product-service.destroy', $item));

        $response->assertStatus(422);
        $this->assertDatabaseHas('product_service_items', ['id' => $item->id]);
    }

    public function test_stock_ledger_and_adjustment_workflow(): void
    {
        $warehouse = Warehouse::create([
            'name' => 'Central Depot',
            'organization_id' => $this->organization->id,
            'workspace_id' => $this->workspace->id,
            'created_by' => $this->user->id,
        ]);

        $product = ProductServiceItem::create([
            'name' => 'Cable HDMI',
            'sku' => 'CBL-HDMI',
            'type' => 'product',
            'sale_price' => 12.00,
            'purchase_price' => 5.00,
            'organization_id' => $this->organization->id,
            'workspace_id' => $this->workspace->id,
            'created_by' => $this->user->id,
        ]);

        // View stock index
        $indexResponse = $this->actingAs($this->user)
            ->withSession(['active_workspace_id' => $this->workspace->id])
            ->get(route('product-service.stock.index'));
        $indexResponse->assertOk();

        // Adjust stock in
        $adjustResponse = $this->actingAs($this->user)
            ->withSession(['active_workspace_id' => $this->workspace->id])
            ->post(route('product-service.adjust-stock', $product), [
                'warehouse_id' => $warehouse->id,
                'quantity' => 50,
                'reason' => 'Opening stock inventory count',
            ]);
        $adjustResponse->assertRedirect();

        $stock = WarehouseStock::where('warehouse_id', $warehouse->id)->where('product_id', $product->id)->firstOrFail();
        $this->assertEquals(50.0, (float) $stock->quantity);

        $movement = StockMovement::where('product_id', $product->id)->firstOrFail();
        $this->assertEquals('adjusted', $movement->type);
        $this->assertEquals(50.0, (float) $movement->quantity);
        $this->assertEquals(1, $movement->direction);
        $this->assertEquals(50.0, (float) $movement->balance_after);
    }

    public function test_api_product_items_lookup(): void
    {
        $product = ProductServiceItem::create([
            'name' => 'API Product',
            'sku' => 'API-001',
            'type' => 'product',
            'sale_price' => 45.00,
            'is_active' => true,
            'organization_id' => $this->organization->id,
            'workspace_id' => $this->workspace->id,
            'created_by' => $this->user->id,
        ]);

        $response = $this->actingAs($this->user)
            ->withSession(['active_workspace_id' => $this->workspace->id])
            ->getJson(route('api.product-service.items.index'));

        $response->assertOk();
        $response->assertJsonFragment(['sku' => 'API-001']);
    }
}

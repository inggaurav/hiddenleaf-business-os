<?php

namespace Tests\Feature\POS;

use App\Models\Organization;
use App\Models\ProductServiceItem;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\Workspace;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use ReflectionClass;
use Tests\TestCase;

class PosMigrationUpgradeSafetyTest extends TestCase
{
    use RefreshDatabase;

    public function test_legacy_pos_order_items_and_returns_are_preserved_in_canonical_tables(): void
    {
        $user = User::factory()->create();
        $organization = Organization::factory()->create(['owner_id' => $user->id]);
        $workspace = Workspace::factory()->create([
            'organization_id' => $organization->id,
            'created_by' => $user->id,
        ]);
        $warehouse = Warehouse::create([
            'organization_id' => $organization->id,
            'workspace_id' => $workspace->id,
            'name' => 'Legacy Warehouse',
            'created_by' => $user->id,
        ]);
        $product = ProductServiceItem::create([
            'organization_id' => $organization->id,
            'workspace_id' => $workspace->id,
            'name' => 'Legacy Product',
            'sku' => 'LEGACY-POS-1',
            'type' => 'product',
            'sale_price' => '50.00',
            'purchase_price' => '25.00',
            'is_active' => true,
            'created_by' => $user->id,
        ]);

        $this->createLegacyTables();
        $now = now();

        DB::table('pos_registers')->insert([
            'id' => 7001,
            'organization_id' => $organization->id,
            'workspace_id' => $workspace->id,
            'warehouse_id' => $warehouse->id,
            'name' => 'Legacy Counter',
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('pos_sessions')->insert([
            'id' => 7101,
            'register_id' => 7001,
        ]);
        DB::table('pos_orders')->insert([
            'id' => 7201,
            'organization_id' => $organization->id,
            'workspace_id' => $workspace->id,
            'session_id' => 7101,
            'receipt_number' => 'LEGACY-RECEIPT-1',
            'customer_name' => 'Legacy Customer',
            'customer_email' => 'legacy@example.test',
            'subtotal' => '100.00',
            'tax_total' => '5.00',
            'discount_total' => '10.00',
            'grand_total' => '95.00',
            'payment_method' => 'cash',
            'status' => 'completed',
            'created_by' => $user->id,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('pos_order_items')->insert([
            'id' => 7301,
            'order_id' => 7201,
            'product_id' => $product->id,
            'name' => 'Legacy Product',
            'sku' => 'LEGACY-POS-1',
            'quantity' => '2.0000',
            'unit_price' => '50.0000',
            'tax_amount' => '5.0000',
            'discount_amount' => '10.0000',
            'line_total' => '95.0000',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('legacy_pos_returns')->insert([
            'id' => 7401,
            'organization_id' => $organization->id,
            'workspace_id' => $workspace->id,
            'order_id' => 7201,
            'return_number' => 'LEGACY-RETURN-1',
            'refund_total' => '47.50',
            'reason' => 'Legacy return',
            'created_by' => $user->id,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('legacy_pos_return_items')->insert([
            'id' => 7501,
            'return_id' => 7401,
            'order_item_id' => 7301,
            'quantity' => '1.0000',
            'refund_amount' => '47.5000',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->invokeLegacyMigration();

        $this->assertDatabaseHas('billing_counters', [
            'id' => 7001,
            'workspace_id' => $workspace->id,
            'warehouse_id' => $warehouse->id,
            'name' => 'Legacy Counter',
        ]);
        $this->assertDatabaseHas('pos_sales', [
            'id' => 7201,
            'workspace_id' => $workspace->id,
            'sale_number' => 'LEGACY-RECEIPT-1',
            'total' => '95.0000',
            'payment_method' => 'cash',
        ]);
        $this->assertDatabaseHas('pos_sale_items', [
            'id' => 7301,
            'pos_sale_id' => 7201,
            'product_id' => $product->id,
            'quantity' => '2.0000',
        ]);
        $this->assertDatabaseHas('pos_returns', [
            'id' => 7401,
            'pos_sale_id' => 7201,
            'return_number' => 'LEGACY-RETURN-1',
            'refund_amount' => '47.5000',
            'status' => 'completed',
        ]);
        $this->assertDatabaseHas('pos_return_items', [
            'id' => 7501,
            'pos_return_id' => 7401,
            'pos_sale_item_id' => 7301,
            'product_id' => $product->id,
            'quantity' => '1.0000',
        ]);

        // The migration is copy-forward and deliberately leaves legacy source
        // tables intact until an explicitly reviewed retirement migration.
        $this->assertDatabaseHas('pos_orders', ['id' => 7201, 'receipt_number' => 'LEGACY-RECEIPT-1']);
        $this->assertDatabaseHas('legacy_pos_returns', ['id' => 7401, 'return_number' => 'LEGACY-RETURN-1']);
    }

    private function invokeLegacyMigration(): void
    {
        $migration = require database_path('migrations/2026_08_14_140000_harden_inventory_and_pos_integrity.php');
        $reflection = new ReflectionClass($migration);
        $method = $reflection->getMethod('migrateLegacyPosData');
        $method->setAccessible(true);
        $method->invoke($migration);
    }

    private function createLegacyTables(): void
    {
        Schema::create('pos_registers', function (Blueprint $table) {
            $table->unsignedBigInteger('id')->primary();
            $table->unsignedBigInteger('organization_id');
            $table->unsignedBigInteger('workspace_id');
            $table->unsignedBigInteger('warehouse_id');
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
        Schema::create('pos_sessions', function (Blueprint $table) {
            $table->unsignedBigInteger('id')->primary();
            $table->unsignedBigInteger('register_id');
        });
        Schema::create('pos_orders', function (Blueprint $table) {
            $table->unsignedBigInteger('id')->primary();
            $table->unsignedBigInteger('organization_id');
            $table->unsignedBigInteger('workspace_id');
            $table->unsignedBigInteger('session_id');
            $table->string('receipt_number');
            $table->string('customer_name')->nullable();
            $table->string('customer_email')->nullable();
            $table->decimal('subtotal', 18, 4);
            $table->decimal('tax_total', 18, 4)->default(0);
            $table->decimal('discount_total', 18, 4)->default(0);
            $table->decimal('grand_total', 18, 4);
            $table->string('payment_method');
            $table->string('status');
            $table->unsignedBigInteger('created_by');
            $table->timestamps();
        });
        Schema::create('pos_order_items', function (Blueprint $table) {
            $table->unsignedBigInteger('id')->primary();
            $table->unsignedBigInteger('order_id');
            $table->unsignedBigInteger('product_id');
            $table->string('name');
            $table->string('sku')->nullable();
            $table->decimal('quantity', 18, 4);
            $table->decimal('unit_price', 18, 4);
            $table->decimal('tax_amount', 18, 4)->default(0);
            $table->decimal('discount_amount', 18, 4)->default(0);
            $table->decimal('line_total', 18, 4);
            $table->timestamps();
        });
        Schema::create('legacy_pos_returns', function (Blueprint $table) {
            $table->unsignedBigInteger('id')->primary();
            $table->unsignedBigInteger('organization_id');
            $table->unsignedBigInteger('workspace_id');
            $table->unsignedBigInteger('order_id');
            $table->string('return_number');
            $table->decimal('refund_total', 18, 4);
            $table->text('reason')->nullable();
            $table->unsignedBigInteger('created_by');
            $table->timestamps();
        });
        Schema::create('legacy_pos_return_items', function (Blueprint $table) {
            $table->unsignedBigInteger('id')->primary();
            $table->unsignedBigInteger('return_id');
            $table->unsignedBigInteger('order_item_id');
            $table->decimal('quantity', 18, 4);
            $table->decimal('refund_amount', 18, 4);
            $table->timestamps();
        });
    }
}

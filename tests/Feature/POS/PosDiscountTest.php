<?php

namespace Tests\Feature\POS;

use App\Domain\POS\PosDiscountService;
use App\Models\Organization;
use App\Models\Plan;
use App\Models\POS\BillingCounter;
use App\Models\POS\PosDiscount;
use App\Models\ProductServiceItem;
use App\Models\User;
use App\Models\UserActiveModule;
use App\Models\Warehouse;
use App\Models\WarehouseStock;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PosDiscountTest extends TestCase
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
            'name' => 'Widget',
            'sku' => 'WDG-01',
            'type' => 'product',
            'sale_price' => 100.00,
            'purchase_price' => 50.00,
            'is_active' => true,
            'created_by' => $this->user->id,
        ]);

        WarehouseStock::create(['warehouse_id' => $this->warehouse->id, 'product_id' => $this->product->id, 'quantity' => '100.0000']);
    }

    public function test_discount_service_rules_and_calculations(): void
    {
        $service = app(PosDiscountService::class);

        // 1. Percentage discount: 20% on $100 = $20.0000
        $pctDiscount = PosDiscount::create([
            'organization_id' => $this->organization->id,
            'workspace_id' => $this->workspace->id,
            'name' => '20% Off',
            'type' => 'percentage',
            'value' => '20.0000',
            'created_by' => $this->user->id,
        ]);
        $this->assertEquals('20.0000', $service->calculate($pctDiscount, '100.0000'));

        // 2. Fixed discount: $15 on $100 = $15.0000
        $fixedDiscount = PosDiscount::create([
            'organization_id' => $this->organization->id,
            'workspace_id' => $this->workspace->id,
            'name' => '$15 Off',
            'type' => 'fixed',
            'value' => '15.0000',
            'created_by' => $this->user->id,
        ]);
        $this->assertEquals('15.0000', $service->calculate($fixedDiscount, '100.0000'));

        // 3. Max discount cap: 50% on $200 = $100, but cap is $30 -> returns $30.0000
        $cappedDiscount = PosDiscount::create([
            'organization_id' => $this->organization->id,
            'workspace_id' => $this->workspace->id,
            'name' => '50% Capped',
            'type' => 'percentage',
            'value' => '50.0000',
            'max_discount_amount' => '30.0000',
            'created_by' => $this->user->id,
        ]);
        $this->assertEquals('30.0000', $service->calculate($cappedDiscount, '200.0000'));

        // 4. Minimum order amount requirement: min $150, but subtotal is $100 -> throws exception
        $minDiscount = PosDiscount::create([
            'organization_id' => $this->organization->id,
            'workspace_id' => $this->workspace->id,
            'name' => 'VIP Discount',
            'type' => 'fixed',
            'value' => '50.0000',
            'min_order_amount' => '150.0000',
            'created_by' => $this->user->id,
        ]);
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('minimum requirement');
        $service->calculate($minDiscount, '100.0000');
    }

    public function test_expired_discount_rejection(): void
    {
        $service = app(PosDiscountService::class);

        $expired = PosDiscount::create([
            'organization_id' => $this->organization->id,
            'workspace_id' => $this->workspace->id,
            'name' => 'Expired Promo',
            'type' => 'percentage',
            'value' => '10.0000',
            'valid_until' => now()->subDay(),
            'created_by' => $this->user->id,
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('has expired');
        $service->calculate($expired, '100.0000');
    }
}

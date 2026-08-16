<?php

namespace Tests\Feature\SaaS;

use App\Models\BankTransferPayment;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\Organization;
use App\Models\Plan;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompleteSaaSEngineTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;

    protected User $companyAdmin;

    protected Organization $org;

    protected Workspace $ws;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->superAdmin = User::factory()->create([
            'role' => 'super_admin',
            'email' => 'superadmin@hiddenleaf.test',
        ]);

        $this->companyAdmin = User::factory()->create([
            'role' => 'company_admin',
            'email' => 'company@hiddenleaf.test',
        ]);

        $this->org = Organization::factory()->create([
            'name' => 'Acme Corp',
            'owner_id' => $this->companyAdmin->id,
        ]);

        $this->ws = Workspace::factory()->create([
            'name' => 'Main Workspace',
            'organization_id' => $this->org->id,
            'created_by' => $this->companyAdmin->id,
        ]);

        $this->companyAdmin->organizations()->attach($this->org->id, ['role' => 'owner']);
        $this->companyAdmin->workspaces()->attach($this->ws->id);
    }

    public function test_super_admin_can_create_plan_and_company_cannot(): void
    {
        $planData = [
            'name' => 'Enterprise Cloud',
            'description' => 'Full enterprise plan',
            'package_price_monthly' => 99.00,
            'package_price_yearly' => 990.00,
            'number_of_users' => 50,
            'storage_limit' => 100, // 100 GB
            'modules' => ['crm', 'hrm', 'accounting'],
            'trial' => true,
            'trial_days' => 14,
            'status' => true,
        ];

        // Non-superadmin is blocked
        $response = $this->actingAs($this->companyAdmin)
            ->post('/plans', $planData);
        $response->assertForbidden();
        $this->assertDatabaseMissing('plans', ['name' => 'Enterprise Cloud']);

        // Superadmin succeeds
        $response = $this->actingAs($this->superAdmin)
            ->post('/plans', $planData);
        $response->assertRedirect('/plans');
        $this->assertDatabaseHas('plans', [
            'name' => 'Enterprise Cloud',
            'package_price_monthly' => 99.00,
            'number_of_users' => 50,
        ]);
    }

    public function test_coupon_discount_calculation_and_endpoint(): void
    {
        $coupon = Coupon::create([
            'name' => 'Save 20%',
            'code' => 'SAVE20',
            'discount' => 20,
            'type' => 'percentage',
            'limit' => 10,
            'status' => true,
            'created_by' => $this->superAdmin->id,
        ]);

        $response = $this->actingAs($this->companyAdmin)
            ->postJson('/plans/apply-coupon', [
                'coupon_code' => 'SAVE20',
                'total_amount' => 100.00,
            ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'discount_amount' => 20.00,
            'final_amount' => 80.00,
        ]);
    }

    public function test_trial_lifecycle_and_duplicate_prevention(): void
    {
        $plan = Plan::create([
            'name' => 'Pro Trial Plan',
            'package_price_monthly' => 49.00,
            'package_price_yearly' => 490.00,
            'number_of_users' => 10,
            'storage_limit' => 1024 * 1024 * 1024,
            'trial' => true,
            'trial_days' => 14,
            'status' => true,
        ]);

        // First trial activation succeeds
        $response = $this->actingAs($this->companyAdmin)
            ->withSession([
                'active_organization_id' => $this->org->id,
                'active_workspace_id' => $this->ws->id,
            ])
            ->post("/plans/{$plan->id}/start-trial");

        $response->assertSessionHas('success');
        $this->companyAdmin->refresh();
        $this->assertEquals($plan->id, $this->companyAdmin->active_plan);
        $this->assertEquals(1, $this->companyAdmin->is_trial_done);
        $this->assertNotNull($this->companyAdmin->plan_expire_date);

        // Second trial attempt is blocked
        $response2 = $this->actingAs($this->companyAdmin)
            ->withSession([
                'active_organization_id' => $this->org->id,
                'active_workspace_id' => $this->ws->id,
            ])
            ->post("/plans/{$plan->id}/start-trial");

        $response2->assertSessionHas('error');
    }

    public function test_free_plan_assignment_creates_order_and_activates_subscription(): void
    {
        $plan = Plan::create([
            'name' => 'Free Starter',
            'package_price_monthly' => 0.00,
            'package_price_yearly' => 0.00,
            'number_of_users' => 2,
            'storage_limit' => 500 * 1024 * 1024,
            'free_plan' => true,
            'status' => true,
        ]);

        $response = $this->actingAs($this->companyAdmin)
            ->withSession([
                'active_organization_id' => $this->org->id,
                'active_workspace_id' => $this->ws->id,
            ])
            ->post("/plans/{$plan->id}/assign-free", ['duration' => 'Month']);

        $response->assertSessionHas('success');
        $this->companyAdmin->refresh();
        $this->assertEquals($plan->id, $this->companyAdmin->active_plan);

        // Assert order was generated with price 0
        $this->assertDatabaseHas('orders', [
            'user_id' => $this->companyAdmin->id,
            'plan_id' => $plan->id,
            'price' => 0.00,
            'payment_status' => 'succeeded',
        ]);
    }

    public function test_bank_transfer_workflow_from_submission_to_approval_and_order_creation(): void
    {
        $plan = Plan::create([
            'name' => 'Business Standard',
            'package_price_monthly' => 50.00,
            'package_price_yearly' => 500.00,
            'number_of_users' => 15,
            'storage_limit' => 50 * 1024 * 1024 * 1024,
            'status' => true,
        ]);

        $coupon = Coupon::create([
            'name' => 'Flat $10 Off',
            'code' => 'FLAT10',
            'discount' => 10,
            'type' => 'fixed',
            'status' => true,
            'created_by' => $this->superAdmin->id,
        ]);

        // 1. Submit bank transfer
        $response = $this->actingAs($this->companyAdmin)
            ->withSession([
                'active_organization_id' => $this->org->id,
                'active_workspace_id' => $this->ws->id,
            ])
            ->post('/bank-transfer', [
                'plan_id' => $plan->id,
                'time_period' => 'Month',
                'coupon_code' => 'FLAT10',
            ]);

        $response->assertRedirect('/plans');
        $this->assertDatabaseHas('bank_transfer_payments', [
            'user_id' => $this->companyAdmin->id,
            'price' => 40.00, // 50 - 10
            'status' => 'pending',
        ]);

        $payment = BankTransferPayment::where('user_id', $this->companyAdmin->id)->first();

        // 2. Super admin approves the bank transfer
        $approveResponse = $this->actingAs($this->superAdmin)
            ->post("/bank-transfer/update/{$payment->id}", [
                'status' => 'approved',
            ]);

        $approveResponse->assertSessionHas('success');

        // Check payment updated
        $payment->refresh();
        $this->assertEquals('approved', $payment->status);

        // Check user plan activated
        $this->companyAdmin->refresh();
        $this->assertEquals($plan->id, $this->companyAdmin->active_plan);

        // Check order created
        $this->assertDatabaseHas('orders', [
            'order_id' => $payment->order_id,
            'user_id' => $this->companyAdmin->id,
            'plan_id' => $plan->id,
            'price' => 40.00,
            'payment_status' => 'succeeded',
        ]);

        // Check coupon usage recorded
        $this->assertDatabaseHas('user_coupons', [
            'user_id' => $this->companyAdmin->id,
            'coupon_id' => $coupon->id,
        ]);
        $coupon->refresh();
        $this->assertEquals(1, $coupon->used);
    }

    public function test_orders_are_tenant_isolated_for_companies(): void
    {
        $otherUser = User::factory()->create();

        $orderA = Order::create([
            'order_id' => 'ORD-AAA-111',
            'user_id' => $this->companyAdmin->id,
            'name' => 'Acme Order',
            'price' => 100,
            'currency' => 'USD',
            'payment_status' => 'succeeded',
        ]);

        $orderB = Order::create([
            'order_id' => 'ORD-BBB-222',
            'user_id' => $otherUser->id,
            'name' => 'Foreign Order',
            'price' => 200,
            'currency' => 'USD',
            'payment_status' => 'succeeded',
        ]);

        // Company admin should be forbidden from accessing foreign order
        $response = $this->actingAs($this->companyAdmin)->get("/orders/{$orderB->id}");
        $response->assertStatus(403);

        // Company admin can access own order
        $responseOwn = $this->actingAs($this->companyAdmin)->get("/orders/{$orderA->id}");
        $responseOwn->assertStatus(200);

        // Superadmin can access foreign order
        $responseSuper = $this->actingAs($this->superAdmin)->get("/orders/{$orderB->id}");
        $responseSuper->assertStatus(200);
    }
}

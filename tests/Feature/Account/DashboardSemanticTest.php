<?php

namespace Tests\Feature\Account;

use App\Domain\Accounting\AccountDashboardService;
use App\Models\AccountCustomer;
use App\Models\AccountExpense;
use App\Models\AccountRevenue;
use App\Models\CustomerPayment;
use App\Models\Organization;
use App\Models\SalesInvoice;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardSemanticTest extends TestCase
{
    use RefreshDatabase;

    protected function setupWorkspace(): array
    {
        $user = User::factory()->create();
        $org = Organization::factory()->create(['owner_id' => $user->id]);
        $ws = Workspace::factory()->create(['organization_id' => $org->id, 'created_by' => $user->id]);

        return compact('user', 'org', 'ws');
    }

    public function test_dashboard_returns_zero_when_no_accounting_entities_exist(): void
    {
        $env = $this->setupWorkspace();
        $service = app(AccountDashboardService::class);

        $metrics = $service->getMetrics($env['ws']);
        $this->assertEquals(0, $metrics['stats']['total_clients']);
        $this->assertEquals(0, $metrics['stats']['total_vendors']);
        $this->assertEquals(0, $metrics['stats']['total_revenue']);
        $this->assertEquals(0, $metrics['stats']['total_expense']);
        $this->assertEquals(0, $metrics['stats']['accounting_income']);
        $this->assertEquals(0, $metrics['stats']['accounting_expense']);
    }

    public function test_dashboard_counts_only_account_customers_not_invoice_customers(): void
    {
        $env = $this->setupWorkspace();

        AccountCustomer::create(['organization_id' => $env['org']->id, 'workspace_id' => $env['ws']->id, 'name' => 'Client 1']);
        AccountCustomer::create(['organization_id' => $env['org']->id, 'workspace_id' => $env['ws']->id, 'name' => 'Client 2']);

        for ($i = 0; $i < 5; $i++) {
            SalesInvoice::create([
                'organization_id' => $env['org']->id,
                'workspace_id' => $env['ws']->id,
                'invoice_id' => 'SI-SEM-'.$i,
                'customer_id' => 999 + $i,
                'issue_date' => now()->toDateString(),
                'total_amount' => 100,
                'status' => 'posted',
                'created_by' => $env['user']->id,
            ]);
        }

        $service = app(AccountDashboardService::class);
        $metrics = $service->getMetrics($env['ws']);

        $this->assertEquals(2, $metrics['stats']['total_clients']);
    }

    public function test_dashboard_payments_from_payment_records_only(): void
    {
        $env = $this->setupWorkspace();

        $customer = AccountCustomer::create([
            'organization_id' => $env['org']->id,
            'workspace_id' => $env['ws']->id,
            'name' => 'Client Payment Test',
        ]);

        CustomerPayment::create([
            'organization_id' => $env['org']->id,
            'workspace_id' => $env['ws']->id,
            'customer_id' => $customer->id,
            'amount' => 500,
            'payment_date' => now()->toDateString(),
            'payment_method' => 'cash',
        ]);

        $service = app(AccountDashboardService::class);
        $metrics = $service->getMetrics($env['ws']);

        $this->assertEquals(500, $metrics['stats']['total_customer_payment']);
    }

    public function test_dashboard_recent_revenues_empty_when_no_revenue_records(): void
    {
        $env = $this->setupWorkspace();

        SalesInvoice::create([
            'organization_id' => $env['org']->id,
            'workspace_id' => $env['ws']->id,
            'invoice_id' => 'SI-REV-1',
            'customer_id' => 1,
            'issue_date' => now()->toDateString(),
            'total_amount' => 1000,
            'status' => 'paid',
            'created_by' => $env['user']->id,
        ]);

        $service = app(AccountDashboardService::class);
        $metrics = $service->getMetrics($env['ws']);

        $this->assertEmpty($metrics['recentRevenues']);
        $this->assertEquals(0, $metrics['stats']['total_revenue']);
    }

    public function test_direct_revenue_and_expense_metrics_have_explicit_sources(): void
    {
        $env = $this->setupWorkspace();

        AccountRevenue::create([
            'organization_id' => $env['org']->id,
            'workspace_id' => $env['ws']->id,
            'amount' => 1250,
            'date' => now()->toDateString(),
            'payment_method' => 'cash',
            'reference' => 'REV-SEM-1',
            'created_by' => $env['user']->id,
        ]);

        AccountExpense::create([
            'organization_id' => $env['org']->id,
            'workspace_id' => $env['ws']->id,
            'amount' => 300,
            'date' => now()->toDateString(),
            'payment_method' => 'cash',
            'reference' => 'EXP-SEM-1',
            'created_by' => $env['user']->id,
        ]);

        $service = app(AccountDashboardService::class);
        $metrics = $service->getMetrics($env['ws']);

        $this->assertEquals(1250, $metrics['stats']['total_revenue']);
        $this->assertEquals(300, $metrics['stats']['total_expense']);
        $this->assertEquals(950, $metrics['stats']['net_profit']);
        $this->assertSame(
            'Sum of account_revenues direct revenue transactions.',
            $metrics['metricSemantics']['total_revenue']
        );
    }
}

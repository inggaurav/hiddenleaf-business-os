<?php

namespace Tests\Feature\MrFox;

use App\Domain\Accounting\AccountReportService;
use App\Domain\MrFox\Context\BusinessContextService;
use App\Domain\MrFox\Tools\AccountingCashPositionTool;
use App\Domain\MrFox\Tools\AccountingPnlTool;
use App\Domain\MrFox\Tools\SalesOutstandingSummaryTool;
use App\Models\AccountType;
use App\Models\LedgerAccount;
use App\Models\Organization;
use App\Models\Plan;
use App\Models\PurchaseInvoice;
use App\Models\SalesInvoice;
use App\Models\User;
use App\Models\UserActiveModule;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MrFoxFinancialParityTest extends TestCase
{
    use RefreshDatabase;

    public function test_mr_fox_financial_tools_match_canonical_domain_calculations(): void
    {
        $this->seed();
        $user = User::factory()->create(['role' => 'super_admin']);
        $plan = Plan::create(['name' => 'Finance Plan', 'modules' => ['account'], 'status' => true, 'created_by' => $user->id]);
        $org = Organization::factory()->create(['owner_id' => $user->id, 'plan_id' => $plan->id]);
        $workspace = Workspace::factory()->create(['organization_id' => $org->id, 'created_by' => $user->id]);
        $org->members()->attach($user, ['role' => 'owner']);
        $workspace->members()->attach($user);

        UserActiveModule::create(['workspace_id' => $workspace->id, 'module_name' => 'account']);

        $accType = AccountType::firstOrCreate([
            'organization_id' => $org->id,
            'workspace_id' => $workspace->id,
            'name' => 'Bank Accounts',
        ], [
            'classification' => 'asset',
            'normal_balance' => 'debit',
        ]);

        // Create posted sales invoices
        SalesInvoice::create([
            'organization_id' => $org->id,
            'workspace_id' => $workspace->id,
            'customer_id' => 1,
            'invoice_id' => 'INV-MATCH-01',
            'issue_date' => now()->subDays(15),
            'due_date' => now()->subDays(5),
            'status' => 1,
            'total_amount' => 15000,
        ]);

        // Create posted purchase invoice
        PurchaseInvoice::create([
            'organization_id' => $org->id,
            'workspace_id' => $workspace->id,
            'vendor_id' => 1,
            'invoice_id' => 'BILL-MATCH-01',
            'purchase_date' => now()->subDays(10),
            'due_date' => now()->addDays(10),
            'status' => 1,
            'total_amount' => 6000,
        ]);

        // Create bank ledger account
        LedgerAccount::create([
            'organization_id' => $org->id,
            'workspace_id' => $workspace->id,
            'account_type_id' => $accType->id,
            'name' => 'Operations Checking',
            'code' => '1020',
            'is_bank' => true,
            'opening_balance' => 45000,
        ]);

        $contextService = app(BusinessContextService::class);
        $context = $contextService->createToolContext($user, $workspace);

        // 1. P&L Test
        $pnlTool = new AccountingPnlTool();
        $pnlResult = $pnlTool->execute($context, []);
        $this->assertEquals(15000, $pnlResult->data['gross_revenue']);
        $this->assertEquals(6000, $pnlResult->data['total_expenses']);
        $this->assertEquals(9000, $pnlResult->data['net_profit']);
        $this->assertEquals(60.0, $pnlResult->data['operating_margin_percent']);

        // 2. Cash Position Test
        $cashTool = new AccountingCashPositionTool();
        $cashResult = $cashTool->execute($context, []);
        $this->assertEquals(45000, $cashResult->data[0]['balance']);

        // 3. Receivables Outstanding Test
        $salesTool = new SalesOutstandingSummaryTool();
        $salesResult = $salesTool->execute($context, []);
        $this->assertEquals(15000, $salesResult->data['total_receivables']);
        $this->assertEquals(15000, $salesResult->data['total_overdue_amount']);
        $this->assertEquals(1, $salesResult->data['overdue_invoices_count']);
    }
}

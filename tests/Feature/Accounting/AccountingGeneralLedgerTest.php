<?php

namespace Tests\Feature\Accounting;

use App\Models\AccountType;
use App\Models\JournalEntry;
use App\Models\LedgerAccount;
use App\Models\Organization;
use App\Models\Plan;
use App\Models\User;
use App\Models\UserActiveModule;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class AccountingGeneralLedgerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Organization $organization;

    private Workspace $workspace;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->user = User::factory()->create(['role' => 'company_admin']);
        $plan = Plan::create(['name' => 'Enterprise Plan', 'modules' => ['account'], 'status' => true, 'created_by' => $this->user->id]);
        $this->organization = Organization::factory()->create(['owner_id' => $this->user->id, 'plan_id' => $plan->id]);
        $this->workspace = Workspace::factory()->create(['organization_id' => $this->organization->id, 'created_by' => $this->user->id]);

        $this->organization->members()->attach($this->user, ['role' => 'owner']);
        $this->workspace->members()->attach($this->user);

        UserActiveModule::create(['workspace_id' => $this->workspace->id, 'module_name' => 'account']);
    }

    public function test_opening_balance_creates_balanced_journal_against_equity(): void
    {
        $assetType = AccountType::create([
            'organization_id' => $this->organization->id,
            'workspace_id' => $this->workspace->id,
            'name' => 'Bank & Cash',
            'classification' => 'asset',
            'normal_balance' => 'debit',
        ]);

        $response = $this->actingAs($this->user)
            ->withSession(['active_organization_id' => $this->organization->id, 'active_workspace_id' => $this->workspace->id])
            ->post('/accounting/bank-accounts', [
                'account_type_id' => $assetType->id,
                'code' => '1010-MAIN',
                'name' => 'Main Operating Bank',
                'currency' => 'USD',
                'bank_name' => 'Silicon Valley Bank',
                'account_number' => '9876543210',
                'opening_balance' => 5000.00,
                'is_active' => true,
            ]);

        $response->assertSessionHasNoErrors();

        $bankAccount = LedgerAccount::where('code', '1010-MAIN')->firstOrFail();
        $this->assertTrue($bankAccount->is_bank);

        $journal = JournalEntry::where('reference', 'BANK-OPEN-'.$bankAccount->id)->firstOrFail();
        $this->assertSame('posted', $journal->status);
        $this->assertSame('5000.00', (string) $journal->lines()->where('ledger_account_id', $bankAccount->id)->value('debit'));
        $this->assertSame('5000.00', (string) $journal->lines()->where('ledger_account_id', '!=', $bankAccount->id)->value('credit'));

        $this->assertSame(
            (float) $journal->lines()->sum('debit'),
            (float) $journal->lines()->sum('credit')
        );
    }

    public function test_multi_line_balanced_journal_and_financial_reports(): void
    {
        $assetType = AccountType::create(['organization_id' => $this->organization->id, 'workspace_id' => $this->workspace->id, 'name' => 'Current Assets', 'classification' => 'asset', 'normal_balance' => 'debit']);
        $incomeType = AccountType::create(['organization_id' => $this->organization->id, 'workspace_id' => $this->workspace->id, 'name' => 'Software Revenue', 'classification' => 'income', 'normal_balance' => 'credit']);
        $taxType = AccountType::create(['organization_id' => $this->organization->id, 'workspace_id' => $this->workspace->id, 'name' => 'Sales Tax Payable', 'classification' => 'liability', 'normal_balance' => 'credit']);

        $cash = LedgerAccount::create(['organization_id' => $this->organization->id, 'workspace_id' => $this->workspace->id, 'account_type_id' => $assetType->id, 'code' => '1000', 'name' => 'Cash', 'currency' => 'USD', 'is_bank' => true]);
        $revenue = LedgerAccount::create(['organization_id' => $this->organization->id, 'workspace_id' => $this->workspace->id, 'account_type_id' => $incomeType->id, 'code' => '4000', 'name' => 'Software Sales', 'currency' => 'USD']);
        $tax = LedgerAccount::create(['organization_id' => $this->organization->id, 'workspace_id' => $this->workspace->id, 'account_type_id' => $taxType->id, 'code' => '2200', 'name' => 'Sales Tax Payable', 'currency' => 'USD']);

        // Multi-line split entry: Debit Cash 1100, Credit Revenue 1000, Credit Tax 100
        $response = $this->actingAs($this->user)
            ->withSession(['active_organization_id' => $this->organization->id, 'active_workspace_id' => $this->workspace->id])
            ->post('/accounting/journals', [
                'entry_date' => now()->toDateString(),
                'description' => 'Software license with 10% tax',
                'lines' => [
                    ['account_id' => $cash->id, 'debit' => 1100.00, 'credit' => 0.00],
                    ['account_id' => $revenue->id, 'debit' => 0.00, 'credit' => 1000.00],
                    ['account_id' => $tax->id, 'debit' => 0.00, 'credit' => 100.00],
                ],
            ]);

        $response->assertSessionHasNoErrors();
        $entry = JournalEntry::where('description', 'Software license with 10% tax')->firstOrFail();
        $this->assertSame('draft', $entry->status);
        $this->assertStringStartsWith('JE-', $entry->entry_number);

        // Post the entry
        $this->actingAs($this->user)
            ->withSession(['active_organization_id' => $this->organization->id, 'active_workspace_id' => $this->workspace->id])
            ->post("/accounting/journals/{$entry->id}/post")
            ->assertSessionHasNoErrors();

        $this->assertSame('posted', $entry->refresh()->status);

        // Verify financial reports reflect posted double-entry amounts
        $this->actingAs($this->user)
            ->withSession(['active_organization_id' => $this->organization->id, 'active_workspace_id' => $this->workspace->id])
            ->get('/accounting/reports')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Accounting/Reports')
                ->where('profitAndLoss.income', 1000)
                ->where('profitAndLoss.net_income', 1000)
                ->where('balanceSheet.assets', 1100)
                ->where('balanceSheet.liabilities', 100)
                ->where('balanceSheet.equity', 0)
            );
    }

    public function test_unbalanced_journal_entry_is_rejected_without_database_write(): void
    {
        $assetType = AccountType::create(['organization_id' => $this->organization->id, 'workspace_id' => $this->workspace->id, 'name' => 'Current Assets', 'classification' => 'asset', 'normal_balance' => 'debit']);
        $cash = LedgerAccount::create(['organization_id' => $this->organization->id, 'workspace_id' => $this->workspace->id, 'account_type_id' => $assetType->id, 'code' => '1000', 'name' => 'Cash', 'currency' => 'USD']);

        $response = $this->actingAs($this->user)
            ->withSession(['active_organization_id' => $this->organization->id, 'active_workspace_id' => $this->workspace->id])
            ->post('/accounting/journals', [
                'entry_date' => now()->toDateString(),
                'description' => 'Unbalanced attack',
                'lines' => [
                    ['account_id' => $cash->id, 'debit' => 100.00, 'credit' => 0.00],
                    ['account_id' => $cash->id, 'debit' => 0.00, 'credit' => 90.00],
                ],
            ]);

        $response->assertServerError();
        $this->assertDatabaseMissing('journal_entries', ['description' => 'Unbalanced attack']);
    }

    public function test_bank_transfer_and_reconciliation_workflow(): void
    {
        $assetType = AccountType::create(['organization_id' => $this->organization->id, 'workspace_id' => $this->workspace->id, 'name' => 'Cash & Banks', 'classification' => 'asset', 'normal_balance' => 'debit']);
        $fromBank = LedgerAccount::create(['organization_id' => $this->organization->id, 'workspace_id' => $this->workspace->id, 'account_type_id' => $assetType->id, 'code' => '1001', 'name' => 'Checking Account', 'currency' => 'USD', 'is_bank' => true]);
        $toBank = LedgerAccount::create(['organization_id' => $this->organization->id, 'workspace_id' => $this->workspace->id, 'account_type_id' => $assetType->id, 'code' => '1002', 'name' => 'Payroll Account', 'currency' => 'USD', 'is_bank' => true]);

        // Process Bank Transfer
        $response = $this->actingAs($this->user)
            ->withSession(['active_organization_id' => $this->organization->id, 'active_workspace_id' => $this->workspace->id])
            ->post('/accounting/bank-transfers', [
                'from_account_id' => $fromBank->id,
                'to_account_id' => $toBank->id,
                'amount' => 500.00,
                'transfer_date' => now()->toDateString(),
                'reference' => 'MONTHLY-PAYROLL-FUNDING',
            ]);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('journal_entries', ['reference' => 'MONTHLY-PAYROLL-FUNDING', 'status' => 'posted']);

        // Bank Reconciliation
        $reconcileResponse = $this->actingAs($this->user)
            ->withSession(['active_organization_id' => $this->organization->id, 'active_workspace_id' => $this->workspace->id])
            ->post('/accounting/bank-reconciliations', [
                'account_id' => $toBank->id,
                'statement_date' => now()->toDateString(),
                'statement_balance' => 500.00,
            ]);

        $reconcileResponse->assertSessionHasNoErrors();
        $this->assertDatabaseHas('bank_reconciliations', [
            'ledger_account_id' => $toBank->id,
            'statement_balance' => 500.00,
            'status' => 'reconciled',
        ]);
    }

    public function test_cross_tenant_isolation_denies_foreign_account_access(): void
    {
        $foreignUser = User::factory()->create();
        $foreignOrg = Organization::factory()->create(['owner_id' => $foreignUser->id]);
        $foreignWs = Workspace::factory()->create(['organization_id' => $foreignOrg->id]);

        $foreignType = AccountType::create(['organization_id' => $foreignOrg->id, 'workspace_id' => $foreignWs->id, 'name' => 'Foreign Asset', 'classification' => 'asset', 'normal_balance' => 'debit']);
        $foreignAccount = LedgerAccount::create(['organization_id' => $foreignOrg->id, 'workspace_id' => $foreignWs->id, 'account_type_id' => $foreignType->id, 'code' => '9999', 'name' => 'Foreign Bank', 'currency' => 'USD', 'is_bank' => true]);

        // Attempt to create journal in active workspace referencing foreign account
        $assetType = AccountType::create(['organization_id' => $this->organization->id, 'workspace_id' => $this->workspace->id, 'name' => 'Own Asset', 'classification' => 'asset', 'normal_balance' => 'debit']);
        $ownAccount = LedgerAccount::create(['organization_id' => $this->organization->id, 'workspace_id' => $this->workspace->id, 'account_type_id' => $assetType->id, 'code' => '1000', 'name' => 'Own Bank', 'currency' => 'USD', 'is_bank' => true]);

        $response = $this->actingAs($this->user)
            ->withSession(['active_organization_id' => $this->organization->id, 'active_workspace_id' => $this->workspace->id])
            ->post('/accounting/journals', [
                'entry_date' => now()->toDateString(),
                'lines' => [
                    ['account_id' => $ownAccount->id, 'debit' => 100, 'credit' => 0],
                    ['account_id' => $foreignAccount->id, 'debit' => 0, 'credit' => 100],
                ],
            ]);

        $response->assertServerError();
        $this->assertDatabaseMissing('journal_entries', ['workspace_id' => $this->workspace->id]);
    }
}

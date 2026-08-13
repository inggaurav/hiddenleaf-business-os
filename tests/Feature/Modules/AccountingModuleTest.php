<?php

namespace Tests\Feature\Modules;

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

class AccountingModuleTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Organization $organization;

    private Workspace $workspace;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->owner = User::factory()->create();
        $plan = Plan::create(['name' => 'Accounting Plan', 'modules' => ['account'], 'status' => true, 'created_by' => $this->owner->id]);
        $this->organization = Organization::factory()->create(['owner_id' => $this->owner->id, 'plan_id' => $plan->id]);
        $this->workspace = Workspace::factory()->create(['organization_id' => $this->organization->id, 'created_by' => $this->owner->id]);
        $this->organization->members()->attach($this->owner, ['role' => 'owner']);
        $this->workspace->members()->attach($this->owner);
        UserActiveModule::create(['workspace_id' => $this->workspace->id, 'module_name' => 'account']);
    }

    public function test_balanced_journal_posts_and_drives_financial_reports(): void
    {
        foreach ([['Cash', 'asset', 'debit'], ['Consulting Revenue', 'income', 'credit']] as [$name, $classification, $normal]) {
            $this->request()->post('/accounting/types', ['name' => $name, 'classification' => $classification, 'normal_balance' => $normal])->assertSessionHasNoErrors();
        }
        $types = AccountType::pluck('id', 'classification');
        $this->request()->post('/accounting/accounts', ['account_type_id' => $types['asset'], 'code' => '1000', 'name' => 'Cash', 'currency' => 'USD', 'is_bank' => true, 'is_active' => true])->assertSessionHasNoErrors();
        $this->request()->post('/accounting/accounts', ['account_type_id' => $types['asset'], 'code' => '1010', 'name' => 'Savings', 'currency' => 'USD', 'is_bank' => true, 'is_active' => true])->assertSessionHasNoErrors();
        $this->request()->post('/accounting/accounts', ['account_type_id' => $types['income'], 'code' => '4000', 'name' => 'Consulting Revenue', 'currency' => 'USD', 'is_active' => true])->assertSessionHasNoErrors();
        $accounts = LedgerAccount::pluck('id', 'code');

        $this->request()->post('/accounting/journals', [
            'entry_date' => now()->toDateString(), 'description' => 'Cash consulting sale',
            'lines' => [
                ['account_id' => $accounts['1000'], 'debit' => 1000, 'credit' => 0],
                ['account_id' => $accounts['4000'], 'debit' => 0, 'credit' => 1000],
            ],
        ])->assertSessionHasNoErrors();
        $entry = JournalEntry::sole();
        $this->request()->post("/accounting/journals/{$entry->id}/post")->assertSessionHasNoErrors();

        $this->assertSame('posted', $entry->refresh()->status);
        $this->assertDatabaseHas('audit_logs', ['action' => 'journal.posted', 'entity_id' => (string) $entry->id]);
        $this->request()->post('/accounting/bank-transfers', ['from_account_id' => $accounts['1000'], 'to_account_id' => $accounts['1010'], 'amount' => 200, 'transfer_date' => now()->toDateString()])->assertSessionHasNoErrors();
        $this->request()->post('/accounting/bank-reconciliations', ['account_id' => $accounts['1010'], 'statement_date' => now()->toDateString(), 'statement_balance' => 200])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('bank_reconciliations', ['ledger_account_id' => $accounts['1010'], 'status' => 'reconciled']);
        $this->request()->get('/accounting/reports')->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Accounting/Reports')
            ->where('profitAndLoss.income', 1000)
            ->where('profitAndLoss.net_income', 1000)
            ->where('balanceSheet.assets', 1000));
    }

    public function test_unbalanced_and_cross_tenant_journals_are_rejected(): void
    {
        $type = AccountType::create(['organization_id' => $this->organization->id, 'workspace_id' => $this->workspace->id, 'name' => 'Asset', 'classification' => 'asset', 'normal_balance' => 'debit']);
        $account = LedgerAccount::create(['organization_id' => $this->organization->id, 'workspace_id' => $this->workspace->id, 'account_type_id' => $type->id, 'code' => '1000', 'name' => 'Cash', 'currency' => 'USD']);
        $this->request()->post('/accounting/journals', ['entry_date' => now()->toDateString(), 'lines' => [
            ['account_id' => $account->id, 'debit' => 10, 'credit' => 0], ['account_id' => $account->id, 'debit' => 0, 'credit' => 5],
        ]])->assertServerError();
        $this->assertDatabaseCount('journal_entries', 0);

        $foreignOwner = User::factory()->create();
        $foreignOrg = Organization::factory()->create(['owner_id' => $foreignOwner->id]);
        $foreignWorkspace = Workspace::factory()->create(['organization_id' => $foreignOrg->id]);
        $foreignEntry = JournalEntry::create(['organization_id' => $foreignOrg->id, 'workspace_id' => $foreignWorkspace->id, 'entry_number' => 'JE-X', 'entry_date' => now(), 'status' => 'draft']);
        $this->request()->post("/accounting/journals/{$foreignEntry->id}/post")->assertNotFound();
    }

    private function request(): self
    {
        return $this->actingAs($this->owner)->withSession(['active_organization_id' => $this->organization->id, 'active_workspace_id' => $this->workspace->id]);
    }
}

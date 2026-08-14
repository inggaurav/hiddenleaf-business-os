<?php

namespace App\Http\Controllers;

use App\Domain\Accounting\LedgerService;
use App\Models\AccountBankTransfer;
use App\Models\AccountType;
use App\Models\BankReconciliation;
use App\Models\JournalEntry;
use App\Models\LedgerAccount;
use App\Models\Workspace;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use App\Domain\Accounting\AccountDashboardService;
use Inertia\Inertia;

class AccountingController extends Controller
{
    public function dashboard(Request $request, AccountDashboardService $dashboardService)
    {
        $workspace = $this->workspace($request, 'account.view');
        $data = $dashboardService->getMetrics($workspace);

        return Inertia::render('Accounting/Dashboard', $data);
    }

    public function accounts(Request $request)
    {
        $workspace = $this->workspace($request, 'account.view');

        return Inertia::render('Accounting/Accounts', [
            'accounts' => LedgerAccount::forWorkspace($workspace->organization_id, $workspace->id)->with('type')->orderBy('code')->paginate(50),
            'types' => AccountType::where('workspace_id', $workspace->id)->get(),
            'metrics' => [
                'accounts' => LedgerAccount::forWorkspace($workspace->organization_id, $workspace->id)->count(),
                'bank_accounts' => LedgerAccount::forWorkspace($workspace->organization_id, $workspace->id)->where('is_bank', true)->count(),
                'posted_journals' => JournalEntry::where('organization_id', $workspace->organization_id)->where('workspace_id', $workspace->id)->where('status', 'posted')->count(),
                'bank_transfers' => (float) AccountBankTransfer::where('organization_id', $workspace->organization_id)->where('workspace_id', $workspace->id)->sum('amount'),
            ],
        ]);
    }

    public function storeType(Request $request)
    {
        $workspace = $this->workspace($request, 'account.manage');
        $data = $request->validate(['name' => ['required', 'string', 'max:100'], 'classification' => ['required', Rule::in(['asset', 'liability', 'equity', 'income', 'expense'])], 'normal_balance' => ['required', Rule::in(['debit', 'credit'])]]);
        AccountType::firstOrCreate(['workspace_id' => $workspace->id, 'name' => $data['name']], $data + ['organization_id' => $workspace->organization_id]);

        return back()->with('success', 'Account type saved.');
    }

    public function storeAccount(Request $request)
    {
        $workspace = $this->workspace($request, 'account.manage');
        $data = $request->validate([
            'account_type_id' => ['required', 'integer'], 'parent_id' => ['nullable', 'integer'],
            'code' => ['required', 'string', 'max:32', Rule::unique('ledger_accounts')->where('workspace_id', $workspace->id)],
            'name' => ['required', 'string', 'max:255'], 'currency' => ['required', 'string', 'size:3'],
            'is_bank' => ['boolean'], 'is_active' => ['boolean'],
        ]);
        abort_unless(AccountType::where('workspace_id', $workspace->id)->whereKey($data['account_type_id'])->exists(), 422);
        if (! empty($data['parent_id'])) {
            abort_unless(LedgerAccount::forWorkspace($workspace->organization_id, $workspace->id)->whereKey($data['parent_id'])->exists(), 422);
        }
        LedgerAccount::create($data + ['organization_id' => $workspace->organization_id, 'workspace_id' => $workspace->id]);

        return back()->with('success', 'Ledger account created.');
    }

    public function journals(Request $request)
    {
        $workspace = $this->workspace($request, 'account.view');

        return Inertia::render('Accounting/Journals', [
            'entries' => JournalEntry::where('organization_id', $workspace->organization_id)->where('workspace_id', $workspace->id)->with('lines.account')->latest('entry_date')->paginate(30),
            'accounts' => LedgerAccount::forWorkspace($workspace->organization_id, $workspace->id)->where('is_active', true)->orderBy('code')->get(),
        ]);
    }

    public function storeJournal(Request $request, LedgerService $ledger)
    {
        $workspace = $this->workspace($request, 'account.manage');
        $data = $request->validate([
            'entry_date' => ['required', 'date'], 'reference' => ['nullable', 'string', 'max:100'], 'description' => ['nullable', 'string'],
            'lines' => ['required', 'array', 'min:2'], 'lines.*.account_id' => ['required', 'integer'],
            'lines.*.description' => ['nullable', 'string'], 'lines.*.debit' => ['nullable', 'numeric', 'min:0'], 'lines.*.credit' => ['nullable', 'numeric', 'min:0'],
        ]);
        $ledger->createEntry($workspace->organization_id, $workspace->id, $data, $request->user());

        return back()->with('success', 'Draft journal entry created.');
    }

    public function postJournal(Request $request, JournalEntry $entry, LedgerService $ledger)
    {
        $workspace = $this->workspace($request, 'account.manage');
        abort_unless((int) $entry->organization_id === (int) $workspace->organization_id && (int) $entry->workspace_id === (int) $workspace->id, 404);
        $ledger->post($entry, $request->user());

        return back()->with('success', 'Journal entry posted.');
    }

    public function reports(Request $request, LedgerService $ledger)
    {
        $workspace = $this->workspace($request, 'account.view');
        $data = $request->validate(['from' => ['nullable', 'date'], 'to' => ['nullable', 'date', 'after_or_equal:from']]);
        $balances = $ledger->balances($workspace->organization_id, $workspace->id, $data['from'] ?? null, $data['to'] ?? null);
        $byClass = $balances->groupBy(fn ($account) => $account->type->classification)->map->sum('balance');

        return Inertia::render('Accounting/Reports', [
            'trialBalance' => $balances,
            'profitAndLoss' => ['income' => $byClass['income'] ?? 0, 'expenses' => $byClass['expense'] ?? 0, 'net_income' => ($byClass['income'] ?? 0) - ($byClass['expense'] ?? 0)],
            'balanceSheet' => ['assets' => $byClass['asset'] ?? 0, 'liabilities' => $byClass['liability'] ?? 0, 'equity' => $byClass['equity'] ?? 0],
        ]);
    }

    public function bankTransfer(Request $request, LedgerService $ledger)
    {
        $workspace = $this->workspace($request, 'account.manage');
        $data = $request->validate([
            'from_account_id' => ['required', 'integer'], 'to_account_id' => ['required', 'integer', 'different:from_account_id'],
            'amount' => ['required', 'numeric', 'gt:0'], 'transfer_date' => ['required', 'date'], 'reference' => ['nullable', 'string', 'max:100'],
        ]);
        $accounts = LedgerAccount::forWorkspace($workspace->organization_id, $workspace->id)->where('is_bank', true)->whereIn('id', [$data['from_account_id'], $data['to_account_id']])->get()->keyBy('id');
        abort_unless($accounts->count() === 2, 422, 'Both transfer accounts must be tenant bank or cash accounts.');

        DB::transaction(function () use ($data, $workspace, $request, $ledger) {
            $entry = $ledger->createEntry($workspace->organization_id, $workspace->id, [
                'entry_date' => $data['transfer_date'], 'reference' => $data['reference'] ?? null, 'description' => 'Bank transfer',
                'lines' => [
                    ['account_id' => $data['to_account_id'], 'debit' => $data['amount'], 'credit' => 0],
                    ['account_id' => $data['from_account_id'], 'debit' => 0, 'credit' => $data['amount']],
                ],
            ], $request->user());
            $ledger->post($entry, $request->user());
            AccountBankTransfer::create($data + ['organization_id' => $workspace->organization_id, 'workspace_id' => $workspace->id, 'journal_entry_id' => $entry->id, 'created_by' => $request->user()->id]);
        });

        return back()->with('success', 'Bank transfer posted.');
    }

    public function reconcile(Request $request, LedgerService $ledger)
    {
        $workspace = $this->workspace($request, 'account.manage');
        $data = $request->validate(['account_id' => ['required', 'integer'], 'statement_date' => ['required', 'date'], 'statement_balance' => ['required', 'numeric']]);
        $account = LedgerAccount::forWorkspace($workspace->organization_id, $workspace->id)->where('is_bank', true)->findOrFail($data['account_id']);
        $balance = (float) $ledger->balances($workspace->organization_id, $workspace->id, null, $data['statement_date'])->firstWhere('id', $account->id)?->balance;
        BankReconciliation::create([
            'organization_id' => $workspace->organization_id, 'workspace_id' => $workspace->id, 'ledger_account_id' => $account->id,
            'statement_date' => $data['statement_date'], 'statement_balance' => $data['statement_balance'], 'ledger_balance' => $balance,
            'status' => abs($balance - (float) $data['statement_balance']) < 0.01 ? 'reconciled' : 'difference',
            'reconciled_by' => $request->user()->id, 'reconciled_at' => now(),
        ]);

        return back()->with('success', 'Bank reconciliation recorded.');
    }

    private function workspace(Request $request, string $permission): Workspace
    {
        $workspace = Workspace::with('organization')->find($request->session()->get('active_workspace_id'));
        abort_unless($workspace && $request->user()->canInWorkspace($permission, $workspace), 403);

        return $workspace;
    }
}

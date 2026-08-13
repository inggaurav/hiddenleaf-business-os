<?php

namespace App\Http\Controllers;

use App\Domain\Accounting\LedgerService;
use App\Models\AccountType;
use App\Models\JournalEntry;
use App\Models\LedgerAccount;
use App\Models\Workspace;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class AccountingController extends Controller
{
    public function accounts(Request $request)
    {
        $workspace = $this->workspace($request, 'account.view');

        return Inertia::render('Accounting/Accounts', [
            'accounts' => LedgerAccount::forWorkspace($workspace->organization_id, $workspace->id)->with('type')->orderBy('code')->paginate(50),
            'types' => AccountType::where('workspace_id', $workspace->id)->get(),
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

    private function workspace(Request $request, string $permission): Workspace
    {
        $workspace = Workspace::with('organization')->find($request->session()->get('active_workspace_id'));
        abort_unless($workspace && $request->user()->canInWorkspace($permission, $workspace), 403);

        return $workspace;
    }
}

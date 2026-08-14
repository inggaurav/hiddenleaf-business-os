<?php

namespace App\Http\Controllers;

use App\Domain\Accounting\LedgerService;
use App\Domain\Accounting\Money;
use App\Models\AccountBankTransfer;
use App\Models\AccountType;
use App\Models\JournalEntry;
use App\Models\LedgerAccount;
use App\Models\Workspace;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class AccountBankTransferController extends Controller
{
    public function index(Request $request)
    {
        $workspace = $this->workspace($request);

        return Inertia::render('Accounting/BankTransfers/Index', [
            'transfers' => AccountBankTransfer::forWorkspace($workspace->organization_id, $workspace->id)
                ->with(['fromAccount', 'toAccount', 'journalEntry'])
                ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
                ->when($request->filled('from_account_id'), fn ($q) => $q->where('from_account_id', $request->integer('from_account_id')))
                ->when($request->filled('to_account_id'), fn ($q) => $q->where('to_account_id', $request->integer('to_account_id')))
                ->latest('transfer_date')
                ->paginate(30)
                ->withQueryString(),
            'accounts' => LedgerAccount::forWorkspace($workspace->organization_id, $workspace->id)
                ->where('is_bank', true)->where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request, LedgerService $ledger)
    {
        $workspace = $this->workspace($request);
        $data = $this->validateTransfer($request, $workspace);

        return DB::transaction(function () use ($data, $workspace, $request, $ledger) {
            $this->assertSufficientBalance($workspace, (int) $data['from_account_id'], Money::of($data['amount'])->add(Money::of($data['transfer_charges'] ?? 0)), $ledger);
            $feeAccount = $this->feeAccount($workspace);

            $entry = $ledger->createEntry($workspace->organization_id, $workspace->id, [
                'entry_date' => $data['transfer_date'],
                'reference' => $data['reference'] ?? null,
                'description' => $data['description'] ?? 'Pending bank transfer',
                'lines' => $this->transferLines($data, $feeAccount->id),
            ], $request->user());

            $transfer = AccountBankTransfer::create($data + [
                'organization_id' => $workspace->organization_id,
                'workspace_id' => $workspace->id,
                'transfer_number' => $this->nextTransferNumber($workspace),
                'journal_entry_id' => $entry->id,
                'status' => 'pending',
                'created_by' => $request->user()->id,
            ]);

            return back()->with('success', 'Pending bank transfer '.$transfer->transfer_number.' created.');
        });
    }

    public function update(Request $request, AccountBankTransfer $bankTransfer, LedgerService $ledger)
    {
        $workspace = $this->workspace($request);
        $this->assertTransfer($bankTransfer, $workspace);
        abort_unless(in_array($bankTransfer->status, ['pending', 'draft'], true), 422, 'Only pending transfers can be edited.');
        $data = $this->validateTransfer($request, $workspace);

        DB::transaction(function () use ($bankTransfer, $data, $workspace, $ledger) {
            $locked = AccountBankTransfer::forWorkspace($workspace->organization_id, $workspace->id)->lockForUpdate()->findOrFail($bankTransfer->id);
            abort_unless(in_array($locked->status, ['pending', 'draft'], true), 422, 'Only pending transfers can be edited.');
            $this->assertSufficientBalance($workspace, (int) $data['from_account_id'], Money::of($data['amount'])->add(Money::of($data['transfer_charges'] ?? 0)), $ledger);
            $feeAccount = $this->feeAccount($workspace);
            $entry = JournalEntry::whereKey($locked->journal_entry_id)->lockForUpdate()->firstOrFail();
            abort_unless($entry->status === 'draft', 422, 'Transfer journal is already posted.');
            $entry->update([
                'entry_date' => $data['transfer_date'],
                'reference' => $data['reference'] ?? null,
                'description' => $data['description'] ?? 'Pending bank transfer',
            ]);
            $entry->lines()->delete();
            foreach ($this->transferLines($data, $feeAccount->id) as $line) {
                $entry->lines()->create([
                    'ledger_account_id' => $line['account_id'],
                    'debit' => $line['debit'],
                    'credit' => $line['credit'],
                ]);
            }
            $locked->update($data + ['status' => 'pending']);
        });

        return back()->with('success', 'Pending bank transfer updated.');
    }

    public function destroy(Request $request, AccountBankTransfer $bankTransfer)
    {
        $workspace = $this->workspace($request);
        $this->assertTransfer($bankTransfer, $workspace);
        abort_unless(in_array($bankTransfer->status, ['pending', 'draft'], true), 422, 'Only pending transfers can be deleted.');

        DB::transaction(function () use ($bankTransfer, $workspace) {
            $locked = AccountBankTransfer::forWorkspace($workspace->organization_id, $workspace->id)->lockForUpdate()->findOrFail($bankTransfer->id);
            abort_unless(in_array($locked->status, ['pending', 'draft'], true), 422, 'Only pending transfers can be deleted.');
            $journalId = $locked->journal_entry_id;
            $locked->delete();
            JournalEntry::whereKey($journalId)->where('status', 'draft')->delete();
        });

        return back()->with('success', 'Pending bank transfer deleted.');
    }

    public function process(Request $request, AccountBankTransfer $bankTransfer, LedgerService $ledger)
    {
        $workspace = $this->workspace($request);
        $this->assertTransfer($bankTransfer, $workspace);
        if (in_array($bankTransfer->status, ['posted', 'completed'], true)) {
            return back()->with('success', 'Bank transfer already processed.');
        }
        abort_unless(in_array($bankTransfer->status, ['pending', 'draft'], true), 422, 'Transfer is not pending.');

        try {
            DB::transaction(function () use ($bankTransfer, $workspace, $request, $ledger) {
                $locked = AccountBankTransfer::forWorkspace($workspace->organization_id, $workspace->id)->lockForUpdate()->findOrFail($bankTransfer->id);
                if (in_array($locked->status, ['posted', 'completed'], true)) {
                    return;
                }
                abort_unless(in_array($locked->status, ['pending', 'draft'], true), 422, 'Transfer is not pending.');

                $totalDebit = Money::of($locked->amount)->add(Money::of($locked->transfer_charges ?? 0));
                $this->assertSufficientBalance($workspace, (int) $locked->from_account_id, $totalDebit, $ledger, true);

                $entry = JournalEntry::whereKey($locked->journal_entry_id)->lockForUpdate()->firstOrFail();
                $ledger->post($entry, $request->user());
                $locked->update([
                    'status' => 'completed',
                    'processed_at' => now(),
                    'processed_by' => $request->user()->id,
                ]);
            });
        } catch (\Throwable $e) {
            // Do not partially post. The transaction above has rolled back. Marking
            // failed is deliberately outside it so the operational failure is visible.
            AccountBankTransfer::forWorkspace($workspace->organization_id, $workspace->id)
                ->whereKey($bankTransfer->id)
                ->whereIn('status', ['pending', 'draft'])
                ->update(['status' => 'failed']);
            throw $e;
        }

        return back()->with('success', 'Bank transfer processed successfully.');
    }

    private function validateTransfer(Request $request, Workspace $workspace): array
    {
        $data = $request->validate([
            'from_account_id' => ['required', 'integer'],
            'to_account_id' => ['required', 'integer', 'different:from_account_id'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'transfer_charges' => ['nullable', 'numeric', 'min:0'],
            'transfer_date' => ['required', 'date'],
            'reference' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:2000'],
        ]);
        $data['transfer_charges'] = Money::of($data['transfer_charges'] ?? 0)->toStorageString();
        $data['amount'] = Money::of($data['amount'])->toStorageString();

        $count = LedgerAccount::forWorkspace($workspace->organization_id, $workspace->id)
            ->where('is_bank', true)
            ->where('is_active', true)
            ->whereIn('id', [$data['from_account_id'], $data['to_account_id']])
            ->count();
        abort_unless($count === 2, 422, 'Both transfer accounts must be active bank/cash accounts in the workspace.');

        return $data;
    }

    private function assertSufficientBalance(Workspace $workspace, int $accountId, Money $required, LedgerService $ledger, bool $lock = false): void
    {
        $query = LedgerAccount::forWorkspace($workspace->organization_id, $workspace->id)
            ->where('is_bank', true)->whereKey($accountId);
        if ($lock) {
            $query->lockForUpdate();
        }
        $account = $query->firstOrFail();
        $balanceRow = $ledger->balances($workspace->organization_id, $workspace->id)->firstWhere('id', $account->id);
        $available = Money::of($balanceRow?->balance ?? 0);
        abort_if($required->isGreaterThan($available), 422, 'Insufficient balance in source account.');
    }

    private function feeAccount(Workspace $workspace): LedgerAccount
    {
        $type = AccountType::firstOrCreate(
            ['workspace_id' => $workspace->id, 'name' => 'Expenses'],
            ['organization_id' => $workspace->organization_id, 'classification' => 'expense', 'normal_balance' => 'debit']
        );
        $currency = LedgerAccount::forWorkspace($workspace->organization_id, $workspace->id)->value('currency') ?: 'USD';

        return LedgerAccount::firstOrCreate(
            ['workspace_id' => $workspace->id, 'code' => 'BANK-FEE'],
            [
                'organization_id' => $workspace->organization_id,
                'account_type_id' => $type->id,
                'name' => 'Bank Transfer Charges',
                'currency' => $currency,
                'is_bank' => false,
                'is_active' => true,
            ]
        );
    }

    private function transferLines(array $data, int $feeAccountId): array
    {
        $amount = Money::of($data['amount']);
        $charges = Money::of($data['transfer_charges'] ?? 0);
        $total = $amount->add($charges);
        $lines = [
            ['account_id' => $data['to_account_id'], 'debit' => $amount->toStorageString(), 'credit' => '0.00'],
            ['account_id' => $data['from_account_id'], 'debit' => '0.00', 'credit' => $total->toStorageString()],
        ];
        if ($charges->isPositive()) {
            $lines[] = ['account_id' => $feeAccountId, 'debit' => $charges->toStorageString(), 'credit' => '0.00'];
        }

        return $lines;
    }

    private function nextTransferNumber(Workspace $workspace): string
    {
        $next = AccountBankTransfer::forWorkspace($workspace->organization_id, $workspace->id)->lockForUpdate()->count() + 1;
        return 'TRF-'.now()->format('Ymd').'-'.str_pad((string) $next, 5, '0', STR_PAD_LEFT);
    }

    private function assertTransfer(AccountBankTransfer $transfer, Workspace $workspace): void
    {
        abort_unless((int) $transfer->organization_id === (int) $workspace->organization_id && (int) $transfer->workspace_id === (int) $workspace->id, 404);
    }

    private function workspace(Request $request): Workspace
    {
        return Workspace::with('organization')->findOrFail($request->session()->get('active_workspace_id'));
    }
}

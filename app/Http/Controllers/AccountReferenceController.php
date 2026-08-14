<?php

namespace App\Http\Controllers;

use App\Domain\Accounting\AccountReportService;
use App\Domain\Accounting\FinancialBalanceService;
use App\Domain\Accounting\LedgerService;
use App\Domain\Accounting\Money;
use App\Models\AccountBankTransfer;
use App\Models\AccountCreditNote;
use App\Models\AccountDebitNote;
use App\Models\AccountExpense;
use App\Models\AccountRevenue;
use App\Models\AccountTransactionCategory;
use App\Models\AccountType;
use App\Models\CustomerPayment;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use App\Models\LedgerAccount;
use App\Models\PurchaseInvoice;
use App\Models\SalesInvoice;
use App\Models\VendorPayment;
use App\Models\Workspace;
use HiddenLeaf\Kernel\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/**
 * WorkDo Account functional-coverage adapter.
 *
 * HiddenLeaf intentionally keeps its own routes, RBAC and UI architecture,
 * while this controller fills business capabilities found in the reference
 * Account module that were not present in the original V1 controller.
 */
class AccountReferenceController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    // ---------------------------------------------------------------------
    // Bank accounts
    // ---------------------------------------------------------------------
    public function bankAccounts(Request $request)
    {
        $workspace = $this->workspace($request, 'account.view');

        return Inertia::render('Accounting/BankAccounts/Index', [
            'bankAccounts' => LedgerAccount::forWorkspace($workspace->organization_id, $workspace->id)
                ->where('is_bank', true)->with('type')->orderBy('code')->paginate(30),
            'accountTypes' => AccountType::where('organization_id', $workspace->organization_id)
                ->where('workspace_id', $workspace->id)->orderBy('name')->get(),
        ]);
    }

    public function storeBankAccount(Request $request, LedgerService $ledger)
    {
        $workspace = $this->workspace($request, 'account.manage');
        $data = $this->validateBankAccount($request, $workspace);

        return DB::transaction(function () use ($data, $workspace, $request, $ledger) {
            $account = LedgerAccount::create($data + [
                'organization_id' => $workspace->organization_id,
                'workspace_id' => $workspace->id,
                'is_bank' => true,
                'is_active' => $data['is_active'] ?? true,
            ]);

            $opening = Money::of($data['opening_balance'] ?? 0);
            if (! $opening->isZero()) {
                $equityType = AccountType::firstOrCreate(
                    ['workspace_id' => $workspace->id, 'name' => 'Equity'],
                    [
                        'organization_id' => $workspace->organization_id,
                        'classification' => 'equity',
                        'normal_balance' => 'credit',
                    ]
                );
                $offset = LedgerAccount::firstOrCreate(
                    ['workspace_id' => $workspace->id, 'code' => 'OBE'],
                    [
                        'organization_id' => $workspace->organization_id,
                        'account_type_id' => $equityType->id,
                        'name' => 'Opening Balance Equity',
                        'currency' => $account->currency,
                        'is_bank' => false,
                        'is_active' => true,
                    ]
                );

                $positive = $opening->isPositive();
                $entry = $ledger->createEntry($workspace->organization_id, $workspace->id, [
                    'entry_date' => now()->toDateString(),
                    'reference' => 'BANK-OPEN-'.$account->id,
                    'description' => 'Opening balance for '.$account->name,
                    'lines' => [
                        ['account_id' => $account->id, 'debit' => $positive ? $opening->toStorageString() : '0', 'credit' => $positive ? '0' : $opening->abs()->toStorageString()],
                        ['account_id' => $offset->id, 'debit' => $positive ? '0' : $opening->abs()->toStorageString(), 'credit' => $positive ? $opening->toStorageString() : '0'],
                    ],
                ], $request->user());
                $ledger->post($entry, $request->user());
            }

            $this->audit->log($request->user()->id, $workspace->organization_id, $workspace->id, 'bank_account.created', 'ledger_account', (string) $account->id, ['name' => $account->name]);

            return back()->with('success', 'Bank account created.');
        });
    }

    public function editBankAccount(Request $request, LedgerAccount $bankAccount)
    {
        $workspace = $this->workspace($request, 'account.view');
        $this->assertLedgerAccount($bankAccount, $workspace, true);

        return Inertia::render('Accounting/BankAccounts/Index', [
            'bankAccounts' => LedgerAccount::forWorkspace($workspace->organization_id, $workspace->id)
                ->where('is_bank', true)->with('type')->orderBy('code')->paginate(30),
            'accountTypes' => AccountType::where('organization_id', $workspace->organization_id)
                ->where('workspace_id', $workspace->id)->orderBy('name')->get(),
            'editing' => $bankAccount,
        ]);
    }

    public function updateBankAccount(Request $request, LedgerAccount $bankAccount)
    {
        $workspace = $this->workspace($request, 'account.manage');
        $this->assertLedgerAccount($bankAccount, $workspace, true);
        $data = $this->validateBankAccount($request, $workspace, $bankAccount->id);
        unset($data['opening_balance']);
        $bankAccount->update($data + ['is_bank' => true]);

        return back()->with('success', 'Bank account updated.');
    }

    public function destroyBankAccount(Request $request, LedgerAccount $bankAccount)
    {
        $workspace = $this->workspace($request, 'account.manage');
        $this->assertLedgerAccount($bankAccount, $workspace, true);

        $hasHistory = $bankAccount->journalLines()->exists()
            || AccountBankTransfer::forWorkspace($workspace->organization_id, $workspace->id)
                ->where(fn ($q) => $q->where('from_account_id', $bankAccount->id)->orWhere('to_account_id', $bankAccount->id))->exists();
        abort_if($hasHistory, 422, 'Bank accounts with financial history cannot be deleted. Deactivate the account instead.');
        $bankAccount->delete();

        return back()->with('success', 'Bank account deleted.');
    }

    public function bankAccountList(Request $request): JsonResponse
    {
        $workspace = $this->workspace($request, 'account.view');

        return response()->json(LedgerAccount::forWorkspace($workspace->organization_id, $workspace->id)
            ->where('is_bank', true)->where('is_active', true)
            ->orderBy('name')->get(['id', 'code', 'name', 'currency', 'bank_name', 'account_number']));
    }

    // ---------------------------------------------------------------------
    // Account types / chart of accounts
    // ---------------------------------------------------------------------
    public function accountTypes(Request $request)
    {
        $workspace = $this->workspace($request, 'account.view');

        return Inertia::render('Accounting/AccountTypes/Index', [
            'types' => AccountType::where('organization_id', $workspace->organization_id)
                ->where('workspace_id', $workspace->id)->withCount('accounts')->orderBy('name')->get(),
        ]);
    }

    public function updateAccountType(Request $request, AccountType $accountType)
    {
        $workspace = $this->workspace($request, 'account.manage');
        $this->assertAccountType($accountType, $workspace);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('account_types')->where('workspace_id', $workspace->id)->ignore($accountType->id)],
            'classification' => ['required', Rule::in(['asset', 'liability', 'equity', 'income', 'expense'])],
            'normal_balance' => ['required', Rule::in(['debit', 'credit'])],
        ]);
        $accountType->update($data);

        return back()->with('success', 'Account type updated.');
    }

    public function destroyAccountType(Request $request, AccountType $accountType)
    {
        $workspace = $this->workspace($request, 'account.manage');
        $this->assertAccountType($accountType, $workspace);
        abort_if(LedgerAccount::forWorkspace($workspace->organization_id, $workspace->id)->where('account_type_id', $accountType->id)->exists(), 422, 'Account types in use cannot be deleted.');
        $accountType->delete();

        return back()->with('success', 'Account type deleted.');
    }

    public function showLedgerAccount(Request $request, LedgerAccount $ledgerAccount)
    {
        $workspace = $this->workspace($request, 'account.view');
        $this->assertLedgerAccount($ledgerAccount, $workspace);

        return Inertia::render('Accounting/Accounts/Show', [
            'account' => $ledgerAccount->load('type'),
            'transactions' => $ledgerAccount->journalLines()->with('entry')->latest()->paginate(50),
        ]);
    }

    public function editLedgerAccount(Request $request, LedgerAccount $ledgerAccount)
    {
        $workspace = $this->workspace($request, 'account.manage');
        $this->assertLedgerAccount($ledgerAccount, $workspace);

        return Inertia::render('Accounting/Accounts/Show', [
            'account' => $ledgerAccount->load('type'),
            'types' => AccountType::where('organization_id', $workspace->organization_id)->where('workspace_id', $workspace->id)->get(),
            'editMode' => true,
        ]);
    }

    public function updateLedgerAccount(Request $request, LedgerAccount $ledgerAccount)
    {
        $workspace = $this->workspace($request, 'account.manage');
        $this->assertLedgerAccount($ledgerAccount, $workspace);
        $data = $request->validate([
            'account_type_id' => ['required', 'integer'],
            'parent_id' => ['nullable', 'integer', 'not_in:'.$ledgerAccount->id],
            'code' => ['required', 'string', 'max:32', Rule::unique('ledger_accounts')->where('workspace_id', $workspace->id)->ignore($ledgerAccount->id)],
            'name' => ['required', 'string', 'max:255'],
            'currency' => ['required', 'string', 'size:3'],
            'is_active' => ['boolean'],
        ]);
        abort_unless(AccountType::where('organization_id', $workspace->organization_id)->where('workspace_id', $workspace->id)->whereKey($data['account_type_id'])->exists(), 422);
        if (! empty($data['parent_id'])) {
            abort_unless(LedgerAccount::forWorkspace($workspace->organization_id, $workspace->id)->whereKey($data['parent_id'])->exists(), 422);
        }
        $ledgerAccount->update($data);

        return back()->with('success', 'Ledger account updated.');
    }

    public function destroyLedgerAccount(Request $request, LedgerAccount $ledgerAccount)
    {
        $workspace = $this->workspace($request, 'account.manage');
        $this->assertLedgerAccount($ledgerAccount, $workspace);
        abort_if($ledgerAccount->journalLines()->exists(), 422, 'Ledger accounts with journal history cannot be deleted.');
        abort_if(LedgerAccount::forWorkspace($workspace->organization_id, $workspace->id)->where('parent_id', $ledgerAccount->id)->exists(), 422, 'Remove or re-parent child accounts before deletion.');
        $ledgerAccount->delete();

        return back()->with('success', 'Ledger account deleted.');
    }

    // ---------------------------------------------------------------------
    // Payment outstanding + immutable void lifecycle
    // ---------------------------------------------------------------------
    public function customerOutstanding(Request $request, int $customerId, AccountReportService $reports): JsonResponse
    {
        $workspace = $this->workspace($request, 'account.view');
        $customer = \App\Models\AccountCustomer::forWorkspace($workspace->organization_id, $workspace->id)->findOrFail($customerId);
        $rows = SalesInvoice::where('organization_id', $workspace->organization_id)->where('workspace_id', $workspace->id)
            ->where('customer_id', $customer->id)->whereNotIn('status', ['draft', 0, 'void'])->get()
            ->map(fn (SalesInvoice $invoice) => [
                'id' => $invoice->id,
                'number' => $invoice->invoice_id,
                'due_date' => optional($invoice->due_date)->toDateString(),
                'total' => Money::of($invoice->total_amount)->toFloat(),
                'outstanding' => $reports->salesOutstanding($workspace, $invoice)->toFloat(),
            ])->filter(fn (array $row) => Money::of($row['outstanding'])->isPositive())->values();

        return response()->json(['customer' => $customer->only(['id', 'name']), 'invoices' => $rows]);
    }

    public function vendorOutstanding(Request $request, int $vendorId, AccountReportService $reports): JsonResponse
    {
        $workspace = $this->workspace($request, 'account.view');
        $vendor = \App\Models\AccountVendor::forWorkspace($workspace->organization_id, $workspace->id)->findOrFail($vendorId);
        $rows = PurchaseInvoice::where('organization_id', $workspace->organization_id)->where('workspace_id', $workspace->id)
            ->where('vendor_id', $vendor->id)->whereNotIn('status', ['draft', 0, 'void'])->get()
            ->map(fn (PurchaseInvoice $invoice) => [
                'id' => $invoice->id,
                'number' => $invoice->invoice_id,
                'due_date' => optional($invoice->due_date)->toDateString(),
                'total' => Money::of($invoice->total_amount)->toFloat(),
                'outstanding' => $reports->purchaseOutstanding($workspace, $invoice)->toFloat(),
            ])->filter(fn (array $row) => Money::of($row['outstanding'])->isPositive())->values();

        return response()->json(['vendor' => $vendor->only(['id', 'name']), 'invoices' => $rows]);
    }

    public function updateCustomerPaymentStatus(Request $request, CustomerPayment $customerPayment, LedgerService $ledger, FinancialBalanceService $balances)
    {
        $status = $request->validate(['status' => ['required', Rule::in(['posted', 'void'])]])['status'];
        if ($status === 'posted') {
            return back()->with('success', 'Payment is already posted.');
        }

        return $this->voidCustomerPayment($request, $customerPayment, $ledger, $balances);
    }

    public function destroyCustomerPayment(Request $request, CustomerPayment $customerPayment, LedgerService $ledger, FinancialBalanceService $balances)
    {
        return $this->voidCustomerPayment($request, $customerPayment, $ledger, $balances);
    }

    public function updateVendorPaymentStatus(Request $request, VendorPayment $vendorPayment, LedgerService $ledger, FinancialBalanceService $balances)
    {
        $status = $request->validate(['status' => ['required', Rule::in(['posted', 'void'])]])['status'];
        if ($status === 'posted') {
            return back()->with('success', 'Payment is already posted.');
        }

        return $this->voidVendorPayment($request, $vendorPayment, $ledger, $balances);
    }

    public function destroyVendorPayment(Request $request, VendorPayment $vendorPayment, LedgerService $ledger, FinancialBalanceService $balances)
    {
        return $this->voidVendorPayment($request, $vendorPayment, $ledger, $balances);
    }

    // ---------------------------------------------------------------------
    // Bank transactions / transfer lifecycle
    // ---------------------------------------------------------------------
    public function bankTransactions(Request $request)
    {
        $workspace = $this->workspace($request, 'account.view');
        $lines = JournalLine::whereHas('account', fn ($q) => $q->where('organization_id', $workspace->organization_id)->where('workspace_id', $workspace->id)->where('is_bank', true))
            ->whereHas('entry', fn ($q) => $q->where('organization_id', $workspace->organization_id)->where('workspace_id', $workspace->id)->where('status', 'posted'))
            ->with(['account', 'entry'])->latest()->paginate(50);

        return Inertia::render('Accounting/BankTransactions/Index', ['transactions' => $lines]);
    }

    public function markBankTransactionReconciled(Request $request, JournalLine $journalLine)
    {
        $workspace = $this->workspace($request, 'account.manage');
        $journalLine->load(['account', 'entry']);
        abort_unless($journalLine->account && $journalLine->entry
            && (int) $journalLine->account->organization_id === (int) $workspace->organization_id
            && (int) $journalLine->account->workspace_id === (int) $workspace->id
            && $journalLine->account->is_bank, 404);
        $journalLine->update(['is_reconciled' => true, 'reconciled_at' => now(), 'reconciled_by' => $request->user()->id]);

        return back()->with('success', 'Bank transaction reconciled.');
    }

    public function bankTransfers(Request $request)
    {
        $workspace = $this->workspace($request, 'account.view');

        return Inertia::render('Accounting/BankTransfers/Index', [
            'transfers' => AccountBankTransfer::forWorkspace($workspace->organization_id, $workspace->id)
                ->with(['fromAccount', 'toAccount', 'journalEntry'])->latest('transfer_date')->paginate(30),
            'accounts' => LedgerAccount::forWorkspace($workspace->organization_id, $workspace->id)->where('is_bank', true)->where('is_active', true)->get(),
        ]);
    }

    public function storeBankTransferDraft(Request $request, LedgerService $ledger)
    {
        $workspace = $this->workspace($request, 'account.manage');
        $data = $this->validateTransfer($request, $workspace);

        return DB::transaction(function () use ($data, $workspace, $request, $ledger) {
            $entry = $ledger->createEntry($workspace->organization_id, $workspace->id, [
                'entry_date' => $data['transfer_date'],
                'reference' => $data['reference'] ?? null,
                'description' => 'Draft bank transfer',
                'lines' => [
                    ['account_id' => $data['to_account_id'], 'debit' => $data['amount'], 'credit' => 0],
                    ['account_id' => $data['from_account_id'], 'debit' => 0, 'credit' => $data['amount']],
                ],
            ], $request->user());

            AccountBankTransfer::create($data + [
                'organization_id' => $workspace->organization_id,
                'workspace_id' => $workspace->id,
                'journal_entry_id' => $entry->id,
                'status' => 'draft',
                'created_by' => $request->user()->id,
            ]);

            return back()->with('success', 'Draft bank transfer created.');
        });
    }

    public function updateBankTransfer(Request $request, AccountBankTransfer $bankTransfer)
    {
        $workspace = $this->workspace($request, 'account.manage');
        $this->assertTransfer($bankTransfer, $workspace);
        abort_unless($bankTransfer->status === 'draft', 422, 'Only draft transfers can be edited.');
        $data = $this->validateTransfer($request, $workspace);

        DB::transaction(function () use ($bankTransfer, $data) {
            $entry = JournalEntry::whereKey($bankTransfer->journal_entry_id)->lockForUpdate()->firstOrFail();
            abort_unless($entry->status === 'draft', 422, 'Transfer journal is no longer editable.');
            $entry->update(['entry_date' => $data['transfer_date'], 'reference' => $data['reference'] ?? null]);
            $entry->lines()->delete();
            $entry->lines()->createMany([
                ['ledger_account_id' => $data['to_account_id'], 'debit' => $data['amount'], 'credit' => 0],
                ['ledger_account_id' => $data['from_account_id'], 'debit' => 0, 'credit' => $data['amount']],
            ]);
            $bankTransfer->update($data);
        });

        return back()->with('success', 'Draft bank transfer updated.');
    }

    public function destroyBankTransfer(Request $request, AccountBankTransfer $bankTransfer)
    {
        $workspace = $this->workspace($request, 'account.manage');
        $this->assertTransfer($bankTransfer, $workspace);
        abort_unless($bankTransfer->status === 'draft', 422, 'Posted transfers cannot be deleted; reverse them with a journal entry instead.');

        DB::transaction(function () use ($bankTransfer) {
            $journalId = $bankTransfer->journal_entry_id;
            $bankTransfer->delete();
            JournalEntry::whereKey($journalId)->where('status', 'draft')->delete();
        });

        return back()->with('success', 'Draft bank transfer deleted.');
    }

    public function processBankTransfer(Request $request, AccountBankTransfer $bankTransfer, LedgerService $ledger)
    {
        $workspace = $this->workspace($request, 'account.manage');
        $this->assertTransfer($bankTransfer, $workspace);
        if ($bankTransfer->status === 'posted') {
            return back()->with('success', 'Bank transfer already processed.');
        }
        abort_unless($bankTransfer->status === 'draft', 422, 'Only draft transfers can be processed.');

        DB::transaction(function () use ($bankTransfer, $request, $ledger) {
            $locked = AccountBankTransfer::whereKey($bankTransfer->id)->lockForUpdate()->firstOrFail();
            if ($locked->status === 'posted') {
                return;
            }
            $entry = JournalEntry::whereKey($locked->journal_entry_id)->lockForUpdate()->firstOrFail();
            $ledger->post($entry, $request->user());
            $locked->update(['status' => 'posted', 'processed_at' => now(), 'processed_by' => $request->user()->id]);
        });

        return back()->with('success', 'Bank transfer processed.');
    }

    // ---------------------------------------------------------------------
    // Revenue / expense categories
    // ---------------------------------------------------------------------
    public function categories(Request $request, string $type)
    {
        $workspace = $this->workspace($request, 'account.view');
        $type = $this->categoryType($type);

        return Inertia::render('Accounting/Categories/Index', [
            'type' => $type,
            'categories' => AccountTransactionCategory::forWorkspace($workspace->organization_id, $workspace->id)
                ->where('type', $type)->orderBy('name')->paginate(30),
        ]);
    }

    public function storeCategory(Request $request, string $type)
    {
        $workspace = $this->workspace($request, 'account.manage');
        $type = $this->categoryType($type);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('account_transaction_categories')->where('workspace_id', $workspace->id)->where('type', $type)],
            'description' => ['nullable', 'string'],
            'is_active' => ['boolean'],
        ]);
        AccountTransactionCategory::create($data + ['organization_id' => $workspace->organization_id, 'workspace_id' => $workspace->id, 'type' => $type, 'created_by' => $request->user()->id]);

        return back()->with('success', ucfirst($type).' category created.');
    }

    public function editCategory(Request $request, string $type, AccountTransactionCategory $category)
    {
        $workspace = $this->workspace($request, 'account.view');
        $type = $this->categoryType($type);
        $this->assertCategory($category, $workspace, $type);

        return Inertia::render('Accounting/Categories/Index', [
            'type' => $type,
            'categories' => AccountTransactionCategory::forWorkspace($workspace->organization_id, $workspace->id)->where('type', $type)->orderBy('name')->paginate(30),
            'editing' => $category,
        ]);
    }

    public function updateCategory(Request $request, string $type, AccountTransactionCategory $category)
    {
        $workspace = $this->workspace($request, 'account.manage');
        $type = $this->categoryType($type);
        $this->assertCategory($category, $workspace, $type);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('account_transaction_categories')->where('workspace_id', $workspace->id)->where('type', $type)->ignore($category->id)],
            'description' => ['nullable', 'string'],
            'is_active' => ['boolean'],
        ]);
        $category->update($data);

        return back()->with('success', ucfirst($type).' category updated.');
    }

    public function destroyCategory(Request $request, string $type, AccountTransactionCategory $category)
    {
        $workspace = $this->workspace($request, 'account.manage');
        $type = $this->categoryType($type);
        $this->assertCategory($category, $workspace, $type);
        $inUse = $type === 'revenue'
            ? AccountRevenue::forWorkspace($workspace->organization_id, $workspace->id)->where('category_id', $category->id)->exists()
            : AccountExpense::forWorkspace($workspace->organization_id, $workspace->id)->where('category_id', $category->id)->exists();
        abort_if($inUse, 422, 'Categories used by financial history cannot be deleted. Deactivate instead.');
        $category->delete();

        return back()->with('success', ucfirst($type).' category deleted.');
    }

    // ---------------------------------------------------------------------
    // Revenue / expense detail lifecycle
    // ---------------------------------------------------------------------
    public function showRevenue(Request $request, AccountRevenue $revenue)
    {
        $workspace = $this->workspace($request, 'account.view');
        $this->assertRevenue($revenue, $workspace);
        return Inertia::render('Accounting/Transactions/Show', ['kind' => 'revenue', 'transaction' => $revenue->load(['customer', 'account', 'category', 'journalEntry'])]);
    }

    public function showExpense(Request $request, AccountExpense $expense)
    {
        $workspace = $this->workspace($request, 'account.view');
        $this->assertExpense($expense, $workspace);
        return Inertia::render('Accounting/Transactions/Show', ['kind' => 'expense', 'transaction' => $expense->load(['vendor', 'account', 'category', 'journalEntry'])]);
    }

    public function storeRevenueDraft(Request $request)
    {
        $workspace = $this->workspace($request, 'account.manage');
        $data = $this->validateDirectTransaction($request, $workspace, 'revenue');
        AccountRevenue::create($data + ['organization_id' => $workspace->organization_id, 'workspace_id' => $workspace->id, 'status' => 'draft', 'created_by' => $request->user()->id]);
        return back()->with('success', 'Draft revenue created.');
    }

    public function storeExpenseDraft(Request $request)
    {
        $workspace = $this->workspace($request, 'account.manage');
        $data = $this->validateDirectTransaction($request, $workspace, 'expense');
        AccountExpense::create($data + ['organization_id' => $workspace->organization_id, 'workspace_id' => $workspace->id, 'status' => 'draft', 'created_by' => $request->user()->id]);
        return back()->with('success', 'Draft expense created.');
    }

    public function updateRevenue(Request $request, AccountRevenue $revenue)
    {
        $workspace = $this->workspace($request, 'account.manage');
        $this->assertRevenue($revenue, $workspace);
        abort_unless(in_array($revenue->status, ['draft', 'approved'], true) && ! $revenue->journal_entry_id, 422, 'Posted revenue cannot be edited.');
        $revenue->update($this->validateDirectTransaction($request, $workspace, 'revenue'));
        return back()->with('success', 'Revenue updated.');
    }

    public function updateExpense(Request $request, AccountExpense $expense)
    {
        $workspace = $this->workspace($request, 'account.manage');
        $this->assertExpense($expense, $workspace);
        abort_unless(in_array($expense->status, ['draft', 'approved'], true) && ! $expense->journal_entry_id, 422, 'Posted expense cannot be edited.');
        $expense->update($this->validateDirectTransaction($request, $workspace, 'expense'));
        return back()->with('success', 'Expense updated.');
    }

    public function destroyRevenue(Request $request, AccountRevenue $revenue)
    {
        $workspace = $this->workspace($request, 'account.manage');
        $this->assertRevenue($revenue, $workspace);
        abort_unless($revenue->status === 'draft' && ! $revenue->journal_entry_id, 422, 'Only unposted draft revenue can be deleted.');
        $revenue->delete();
        return back()->with('success', 'Draft revenue deleted.');
    }

    public function destroyExpense(Request $request, AccountExpense $expense)
    {
        $workspace = $this->workspace($request, 'account.manage');
        $this->assertExpense($expense, $workspace);
        abort_unless($expense->status === 'draft' && ! $expense->journal_entry_id, 422, 'Only unposted draft expense can be deleted.');
        $expense->delete();
        return back()->with('success', 'Draft expense deleted.');
    }

    public function approveRevenue(Request $request, AccountRevenue $revenue)
    {
        $workspace = $this->workspace($request, 'account.manage');
        $this->assertRevenue($revenue, $workspace);
        abort_unless($revenue->status === 'draft', 422, 'Only draft revenue can be approved.');
        $revenue->update(['status' => 'approved', 'approved_at' => now(), 'approved_by' => $request->user()->id]);
        return back()->with('success', 'Revenue approved.');
    }

    public function approveExpense(Request $request, AccountExpense $expense)
    {
        $workspace = $this->workspace($request, 'account.manage');
        $this->assertExpense($expense, $workspace);
        abort_unless($expense->status === 'draft', 422, 'Only draft expense can be approved.');
        $expense->update(['status' => 'approved', 'approved_at' => now(), 'approved_by' => $request->user()->id]);
        return back()->with('success', 'Expense approved.');
    }

    public function postRevenue(Request $request, AccountRevenue $revenue, LedgerService $ledger)
    {
        $workspace = $this->workspace($request, 'account.manage');
        $this->assertRevenue($revenue, $workspace);
        if ($revenue->status === 'posted') {
            return back()->with('success', 'Revenue already posted.');
        }
        abort_unless($revenue->status === 'approved', 422, 'Revenue must be approved before posting.');
        $this->postDirectTransaction($revenue, 'revenue', $workspace, $request, $ledger);
        return back()->with('success', 'Revenue posted.');
    }

    public function postExpense(Request $request, AccountExpense $expense, LedgerService $ledger)
    {
        $workspace = $this->workspace($request, 'account.manage');
        $this->assertExpense($expense, $workspace);
        if ($expense->status === 'posted') {
            return back()->with('success', 'Expense already posted.');
        }
        abort_unless($expense->status === 'approved', 422, 'Expense must be approved before posting.');
        $this->postDirectTransaction($expense, 'expense', $workspace, $request, $ledger);
        return back()->with('success', 'Expense posted.');
    }

    // ---------------------------------------------------------------------
    // Credit / debit note detail and approval. Applied notes are immutable.
    // ---------------------------------------------------------------------
    public function showCreditNote(Request $request, AccountCreditNote $creditNote)
    {
        $workspace = $this->workspace($request, 'account.view');
        $this->assertCreditNote($creditNote, $workspace);
        return Inertia::render('Accounting/Notes/Show', ['kind' => 'credit', 'note' => $creditNote->load(['customer', 'invoice', 'journalEntry'])]);
    }

    public function showDebitNote(Request $request, AccountDebitNote $debitNote)
    {
        $workspace = $this->workspace($request, 'account.view');
        $this->assertDebitNote($debitNote, $workspace);
        return Inertia::render('Accounting/Notes/Show', ['kind' => 'debit', 'note' => $debitNote->load(['vendor', 'purchaseInvoice', 'journalEntry'])]);
    }

    public function approveCreditNote(Request $request, AccountCreditNote $creditNote)
    {
        $workspace = $this->workspace($request, 'account.manage');
        $this->assertCreditNote($creditNote, $workspace);
        if ($creditNote->status === 'applied') {
            return back()->with('success', 'Credit note already applied.');
        }
        abort_unless($creditNote->status === 'pending', 422, 'Only pending credit notes can be approved.');
        $creditNote->update(['status' => 'applied', 'approved_at' => now(), 'approved_by' => $request->user()->id]);
        return back()->with('success', 'Credit note approved.');
    }

    public function approveDebitNote(Request $request, AccountDebitNote $debitNote)
    {
        $workspace = $this->workspace($request, 'account.manage');
        $this->assertDebitNote($debitNote, $workspace);
        if ($debitNote->status === 'applied') {
            return back()->with('success', 'Debit note already applied.');
        }
        abort_unless($debitNote->status === 'pending', 422, 'Only pending debit notes can be approved.');
        $debitNote->update(['status' => 'applied', 'approved_at' => now(), 'approved_by' => $request->user()->id]);
        return back()->with('success', 'Debit note approved.');
    }

    public function destroyCreditNote(Request $request, AccountCreditNote $creditNote)
    {
        $workspace = $this->workspace($request, 'account.manage');
        $this->assertCreditNote($creditNote, $workspace);
        abort_unless($creditNote->status === 'pending' && ! $creditNote->journal_entry_id, 422, 'Applied credit notes are immutable.');
        $creditNote->delete();
        return back()->with('success', 'Pending credit note deleted.');
    }

    public function destroyDebitNote(Request $request, AccountDebitNote $debitNote)
    {
        $workspace = $this->workspace($request, 'account.manage');
        $this->assertDebitNote($debitNote, $workspace);
        abort_unless($debitNote->status === 'pending' && ! $debitNote->journal_entry_id, 422, 'Applied debit notes are immutable.');
        $debitNote->delete();
        return back()->with('success', 'Pending debit note deleted.');
    }

    // ---------------------------------------------------------------------
    // Reports
    // ---------------------------------------------------------------------
    public function invoiceAging(Request $request, AccountReportService $reports): JsonResponse
    {
        $workspace = $this->workspace($request, 'account.view');
        return response()->json($reports->invoiceAging($workspace, $request->input('as_of_date')));
    }

    public function billAging(Request $request, AccountReportService $reports): JsonResponse
    {
        $workspace = $this->workspace($request, 'account.view');
        return response()->json($reports->billAging($workspace, $request->input('as_of_date')));
    }

    public function taxSummary(Request $request, AccountReportService $reports): JsonResponse
    {
        $workspace = $this->workspace($request, 'account.view');
        return response()->json($reports->taxSummary($workspace, $request->input('from_date'), $request->input('to_date')));
    }

    public function customerBalance(Request $request, AccountReportService $reports): JsonResponse
    {
        $workspace = $this->workspace($request, 'account.view');
        return response()->json($reports->customerBalanceSummary($workspace, $request->input('as_of_date'), $request->boolean('show_zero_balances')));
    }

    public function vendorBalance(Request $request, AccountReportService $reports): JsonResponse
    {
        $workspace = $this->workspace($request, 'account.view');
        return response()->json($reports->vendorBalanceSummary($workspace, $request->input('as_of_date'), $request->boolean('show_zero_balances')));
    }

    public function customerDetail(Request $request, int $customerId, AccountReportService $reports)
    {
        $workspace = $this->workspace($request, 'account.view');
        return Inertia::render('Accounting/Reports/PartyDetail', ['kind' => 'customer', 'data' => $reports->customerDetail($workspace, $customerId, $request->input('start_date'), $request->input('end_date'))]);
    }

    public function vendorDetail(Request $request, int $vendorId, AccountReportService $reports)
    {
        $workspace = $this->workspace($request, 'account.view');
        return Inertia::render('Accounting/Reports/PartyDetail', ['kind' => 'vendor', 'data' => $reports->vendorDetail($workspace, $vendorId, $request->input('start_date'), $request->input('end_date'))]);
    }

    public function printReport(Request $request, string $report, AccountReportService $reports)
    {
        $workspace = $this->workspace($request, 'account.view');
        $data = match ($report) {
            'invoice-aging' => $reports->invoiceAging($workspace, $request->input('as_of_date')),
            'bill-aging' => $reports->billAging($workspace, $request->input('as_of_date')),
            'tax-summary' => $reports->taxSummary($workspace, $request->input('from_date'), $request->input('to_date')),
            'customer-balance' => $reports->customerBalanceSummary($workspace, $request->input('as_of_date'), $request->boolean('show_zero_balances')),
            'vendor-balance' => $reports->vendorBalanceSummary($workspace, $request->input('as_of_date'), $request->boolean('show_zero_balances')),
            default => abort(404),
        };

        return Inertia::render('Accounting/Reports/Print', ['reportType' => $report, 'data' => $data]);
    }

    public function printCustomerDetail(Request $request, int $customerId, AccountReportService $reports)
    {
        $workspace = $this->workspace($request, 'account.view');
        return Inertia::render('Accounting/Reports/Print', ['reportType' => 'customer-detail', 'data' => $reports->customerDetail($workspace, $customerId, $request->input('start_date'), $request->input('end_date'))]);
    }

    public function printVendorDetail(Request $request, int $vendorId, AccountReportService $reports)
    {
        $workspace = $this->workspace($request, 'account.view');
        return Inertia::render('Accounting/Reports/Print', ['reportType' => 'vendor-detail', 'data' => $reports->vendorDetail($workspace, $vendorId, $request->input('start_date'), $request->input('end_date'))]);
    }

    // ---------------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------------
    private function voidCustomerPayment(Request $request, CustomerPayment $payment, LedgerService $ledger, FinancialBalanceService $balances)
    {
        $workspace = $this->workspace($request, 'account.manage');
        abort_unless((int) $payment->organization_id === (int) $workspace->organization_id && (int) $payment->workspace_id === (int) $workspace->id, 404);
        if ($payment->status === 'void') {
            return back()->with('success', 'Customer payment already voided.');
        }

        DB::transaction(function () use ($payment, $request, $ledger, $balances, $workspace) {
            $locked = CustomerPayment::forWorkspace($workspace->organization_id, $workspace->id)->lockForUpdate()->findOrFail($payment->id);
            if ($locked->status === 'void') {
                return;
            }
            if ($locked->journal_entry_id) {
                $entry = JournalEntry::whereKey($locked->journal_entry_id)->first();
                if ($entry && $entry->status === 'posted') {
                    $ledger->reverse($entry, $request->user(), 'VOID-CUST-PAY-'.$locked->id, 'Void customer payment #'.$locked->id);
                }
            }
            $locked->update(['status' => 'void', 'voided_at' => now(), 'voided_by' => $request->user()->id]);
            if ($locked->invoice_id) {
                $this->refreshSalesInvoiceStatus($workspace, $locked->invoice_id);
            }
            if ($locked->customer_id) {
                $customer = \App\Models\AccountCustomer::forWorkspace($workspace->organization_id, $workspace->id)->findOrFail($locked->customer_id);
                $balances->syncCustomerBalance($customer);
            }
        });

        return back()->with('success', 'Customer payment voided with reversal journal.');
    }

    private function voidVendorPayment(Request $request, VendorPayment $payment, LedgerService $ledger, FinancialBalanceService $balances)
    {
        $workspace = $this->workspace($request, 'account.manage');
        abort_unless((int) $payment->organization_id === (int) $workspace->organization_id && (int) $payment->workspace_id === (int) $workspace->id, 404);
        if ($payment->status === 'void') {
            return back()->with('success', 'Vendor payment already voided.');
        }

        DB::transaction(function () use ($payment, $request, $ledger, $balances, $workspace) {
            $locked = VendorPayment::forWorkspace($workspace->organization_id, $workspace->id)->lockForUpdate()->findOrFail($payment->id);
            if ($locked->status === 'void') {
                return;
            }
            if ($locked->journal_entry_id) {
                $entry = JournalEntry::whereKey($locked->journal_entry_id)->first();
                if ($entry && $entry->status === 'posted') {
                    $ledger->reverse($entry, $request->user(), 'VOID-VEND-PAY-'.$locked->id, 'Void vendor payment #'.$locked->id);
                }
            }
            $locked->update(['status' => 'void', 'voided_at' => now(), 'voided_by' => $request->user()->id]);
            if ($locked->purchase_invoice_id) {
                $this->refreshPurchaseInvoiceStatus($workspace, $locked->purchase_invoice_id);
            }
            if ($locked->vendor_id) {
                $vendor = \App\Models\AccountVendor::forWorkspace($workspace->organization_id, $workspace->id)->findOrFail($locked->vendor_id);
                $balances->syncVendorBalance($vendor);
            }
        });

        return back()->with('success', 'Vendor payment voided with reversal journal.');
    }

    private function refreshSalesInvoiceStatus(Workspace $workspace, int $invoiceId): void
    {
        $invoice = SalesInvoice::where('organization_id', $workspace->organization_id)->where('workspace_id', $workspace->id)->lockForUpdate()->findOrFail($invoiceId);
        $paid = Money::of(CustomerPayment::forWorkspace($workspace->organization_id, $workspace->id)->where('invoice_id', $invoice->id)->where('status', '!=', 'void')->sum('amount'));
        $total = Money::of($invoice->total_amount);
        $status = $paid->isZero() ? 'posted' : ($paid->isGreaterThanOrEqual($total) ? 'paid' : 'partial');
        $invoice->update(['status' => $status]);
    }

    private function refreshPurchaseInvoiceStatus(Workspace $workspace, int $invoiceId): void
    {
        $invoice = PurchaseInvoice::where('organization_id', $workspace->organization_id)->where('workspace_id', $workspace->id)->lockForUpdate()->findOrFail($invoiceId);
        $paid = Money::of(VendorPayment::forWorkspace($workspace->organization_id, $workspace->id)->where('purchase_invoice_id', $invoice->id)->where('status', '!=', 'void')->sum('amount'));
        $total = Money::of($invoice->total_amount);
        $status = $paid->isZero() ? 'posted' : ($paid->isGreaterThanOrEqual($total) ? 'paid' : 'partial');
        $invoice->update(['status' => $status]);
    }

    private function postDirectTransaction(AccountRevenue|AccountExpense $transaction, string $kind, Workspace $workspace, Request $request, LedgerService $ledger): void
    {
        DB::transaction(function () use ($transaction, $kind, $workspace, $request, $ledger) {
            $locked = $kind === 'revenue'
                ? AccountRevenue::forWorkspace($workspace->organization_id, $workspace->id)->lockForUpdate()->findOrFail($transaction->id)
                : AccountExpense::forWorkspace($workspace->organization_id, $workspace->id)->lockForUpdate()->findOrFail($transaction->id);
            if ($locked->status === 'posted') {
                return;
            }
            abort_unless($locked->status === 'approved', 422, 'Transaction must be approved before posting.');
            abort_unless($locked->account_id, 422, 'A bank/cash account is required before posting.');
            $cash = LedgerAccount::forWorkspace($workspace->organization_id, $workspace->id)->whereKey($locked->account_id)->where('is_bank', true)->firstOrFail();
            $classification = $kind === 'revenue' ? 'income' : 'expense';
            $counter = LedgerAccount::forWorkspace($workspace->organization_id, $workspace->id)
                ->whereHas('type', fn ($q) => $q->where('classification', $classification))->first();
            abort_unless($counter, 422, ucfirst($classification).' ledger account is required before posting.');

            $lines = $kind === 'revenue'
                ? [
                    ['account_id' => $cash->id, 'debit' => $locked->amount, 'credit' => 0],
                    ['account_id' => $counter->id, 'debit' => 0, 'credit' => $locked->amount],
                ]
                : [
                    ['account_id' => $counter->id, 'debit' => $locked->amount, 'credit' => 0],
                    ['account_id' => $cash->id, 'debit' => 0, 'credit' => $locked->amount],
                ];

            $entry = $ledger->createEntry($workspace->organization_id, $workspace->id, [
                'entry_date' => $locked->date->toDateString(),
                'reference' => $locked->reference ?? strtoupper(substr($kind, 0, 3)).'-'.$locked->id,
                'description' => $locked->description ?? ucfirst($kind).' transaction',
                'lines' => $lines,
            ], $request->user());
            $ledger->post($entry, $request->user());
            $locked->update(['status' => 'posted', 'journal_entry_id' => $entry->id, 'posted_at' => now(), 'posted_by' => $request->user()->id]);
        });
    }

    private function validateBankAccount(Request $request, Workspace $workspace, ?int $ignoreId = null): array
    {
        $data = $request->validate([
            'account_type_id' => ['required', 'integer'],
            'code' => ['required', 'string', 'max:32', Rule::unique('ledger_accounts')->where('workspace_id', $workspace->id)->ignore($ignoreId)],
            'name' => ['required', 'string', 'max:255'],
            'currency' => ['required', 'string', 'size:3'],
            'bank_name' => ['nullable', 'string', 'max:255'],
            'account_holder' => ['nullable', 'string', 'max:255'],
            'account_number' => ['nullable', 'string', 'max:100'],
            'branch_name' => ['nullable', 'string', 'max:255'],
            'iban' => ['nullable', 'string', 'max:100'],
            'swift_code' => ['nullable', 'string', 'max:50'],
            'opening_balance' => ['nullable', 'numeric'],
            'is_active' => ['boolean'],
        ]);
        abort_unless(AccountType::where('organization_id', $workspace->organization_id)->where('workspace_id', $workspace->id)->whereKey($data['account_type_id'])->exists(), 422);
        return $data;
    }

    private function validateTransfer(Request $request, Workspace $workspace): array
    {
        $data = $request->validate([
            'from_account_id' => ['required', 'integer'],
            'to_account_id' => ['required', 'integer', 'different:from_account_id'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'transfer_date' => ['required', 'date'],
            'reference' => ['nullable', 'string', 'max:100'],
        ]);
        $count = LedgerAccount::forWorkspace($workspace->organization_id, $workspace->id)
            ->where('is_bank', true)->whereIn('id', [$data['from_account_id'], $data['to_account_id']])->count();
        abort_unless($count === 2, 422, 'Both transfer accounts must belong to the active workspace and be bank/cash accounts.');
        return $data;
    }

    private function validateDirectTransaction(Request $request, Workspace $workspace, string $kind): array
    {
        $foreign = $kind === 'revenue' ? 'customer_id' : 'vendor_id';
        $data = $request->validate([
            $foreign => ['nullable', 'integer'],
            'account_id' => ['nullable', 'integer'],
            'category_id' => ['nullable', 'integer'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'date' => ['required', 'date'],
            'payment_method' => ['required', 'string', 'max:50'],
            'reference' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string'],
        ]);
        if (! empty($data['account_id'])) {
            abort_unless(LedgerAccount::forWorkspace($workspace->organization_id, $workspace->id)->where('is_bank', true)->whereKey($data['account_id'])->exists(), 422);
        }
        if (! empty($data['category_id'])) {
            abort_unless(AccountTransactionCategory::forWorkspace($workspace->organization_id, $workspace->id)->where('type', $kind)->whereKey($data['category_id'])->exists(), 422);
        }
        if ($kind === 'revenue' && ! empty($data['customer_id'])) {
            abort_unless(\App\Models\AccountCustomer::forWorkspace($workspace->organization_id, $workspace->id)->whereKey($data['customer_id'])->exists(), 422);
        }
        if ($kind === 'expense' && ! empty($data['vendor_id'])) {
            abort_unless(\App\Models\AccountVendor::forWorkspace($workspace->organization_id, $workspace->id)->whereKey($data['vendor_id'])->exists(), 422);
        }
        return $data;
    }

    private function categoryType(string $type): string
    {
        abort_unless(in_array($type, ['revenue', 'expense'], true), 404);
        return $type;
    }

    private function assertLedgerAccount(LedgerAccount $account, Workspace $workspace, bool $mustBeBank = false): void
    {
        abort_unless((int) $account->organization_id === (int) $workspace->organization_id && (int) $account->workspace_id === (int) $workspace->id && (! $mustBeBank || $account->is_bank), 404);
    }

    private function assertAccountType(AccountType $type, Workspace $workspace): void
    {
        abort_unless((int) $type->organization_id === (int) $workspace->organization_id && (int) $type->workspace_id === (int) $workspace->id, 404);
    }

    private function assertTransfer(AccountBankTransfer $transfer, Workspace $workspace): void
    {
        abort_unless((int) $transfer->organization_id === (int) $workspace->organization_id && (int) $transfer->workspace_id === (int) $workspace->id, 404);
    }

    private function assertCategory(AccountTransactionCategory $category, Workspace $workspace, string $type): void
    {
        abort_unless((int) $category->organization_id === (int) $workspace->organization_id && (int) $category->workspace_id === (int) $workspace->id && $category->type === $type, 404);
    }

    private function assertRevenue(AccountRevenue $revenue, Workspace $workspace): void
    {
        abort_unless((int) $revenue->organization_id === (int) $workspace->organization_id && (int) $revenue->workspace_id === (int) $workspace->id, 404);
    }

    private function assertExpense(AccountExpense $expense, Workspace $workspace): void
    {
        abort_unless((int) $expense->organization_id === (int) $workspace->organization_id && (int) $expense->workspace_id === (int) $workspace->id, 404);
    }

    private function assertCreditNote(AccountCreditNote $note, Workspace $workspace): void
    {
        abort_unless((int) $note->organization_id === (int) $workspace->organization_id && (int) $note->workspace_id === (int) $workspace->id, 404);
    }

    private function assertDebitNote(AccountDebitNote $note, Workspace $workspace): void
    {
        abort_unless((int) $note->organization_id === (int) $workspace->organization_id && (int) $note->workspace_id === (int) $workspace->id, 404);
    }

    private function workspace(Request $request, string $permission): Workspace
    {
        $workspace = Workspace::with('organization')->find($request->session()->get('active_workspace_id'));
        abort_unless($workspace && $request->user()->canInWorkspace($permission, $workspace), 403);
        return $workspace;
    }
}

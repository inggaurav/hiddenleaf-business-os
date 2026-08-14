<?php

namespace App\Domain\Accounting;

use App\Models\AccountCreditNote;
use App\Models\AccountCustomer;
use App\Models\AccountDebitNote;
use App\Models\AccountType;
use App\Models\AccountVendor;
use App\Models\JournalEntry;
use App\Models\LedgerAccount;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseReturn;
use App\Models\SalesInvoice;
use App\Models\SalesInvoiceReturn;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Bridges commercial document state changes into the general ledger.
 *
 * The service intentionally owns only deterministic accounting outcomes. It
 * does not know about WorkDo controllers or UI conventions.
 */
class CommercialAccountingService
{
    public function __construct(
        private readonly LedgerService $ledger,
        private readonly FinancialBalanceService $balances,
    ) {}

    public function postSalesInvoice(SalesInvoice $invoice, User $actor): ?JournalEntry
    {
        return DB::transaction(function () use ($invoice, $actor) {
            $locked = SalesInvoice::whereKey($invoice->id)->lockForUpdate()->firstOrFail();
            $reference = 'SALE-'.$locked->invoice_id;
            if ($existing = $this->postedEntry($locked->organization_id, $locked->workspace_id, $reference)) {
                return $existing;
            }

            $accounts = $this->systemAccounts($locked->organization_id, $locked->workspace_id);
            $entry = $this->ledger->createEntry($locked->organization_id, $locked->workspace_id, [
                'entry_date' => $locked->issue_date->toDateString(),
                'reference' => $reference,
                'description' => 'Sales invoice '.$locked->invoice_id,
                'lines' => [
                    ['account_id' => $accounts['receivable']->id, 'debit' => $locked->total_amount, 'credit' => 0],
                    ['account_id' => $accounts['sales_revenue']->id, 'debit' => 0, 'credit' => $locked->total_amount],
                ],
            ], $actor);
            $this->ledger->post($entry, $actor);

            if ($locked->customer_id) {
                $customer = AccountCustomer::forWorkspace($locked->organization_id, $locked->workspace_id)->find($locked->customer_id);
                if ($customer) {
                    $this->balances->syncCustomerBalance($customer);
                }
            }

            return $entry->refresh();
        });
    }

    public function postPurchaseInvoice(PurchaseInvoice $invoice, User $actor): ?JournalEntry
    {
        return DB::transaction(function () use ($invoice, $actor) {
            $locked = PurchaseInvoice::whereKey($invoice->id)->lockForUpdate()->firstOrFail();
            $reference = 'PURCHASE-'.$locked->invoice_id;
            if ($existing = $this->postedEntry($locked->organization_id, $locked->workspace_id, $reference)) {
                return $existing;
            }

            $accounts = $this->systemAccounts($locked->organization_id, $locked->workspace_id);
            $entry = $this->ledger->createEntry($locked->organization_id, $locked->workspace_id, [
                'entry_date' => $locked->purchase_date->toDateString(),
                'reference' => $reference,
                'description' => 'Purchase invoice '.$locked->invoice_id,
                'lines' => [
                    ['account_id' => $accounts['purchase_expense']->id, 'debit' => $locked->total_amount, 'credit' => 0],
                    ['account_id' => $accounts['payable']->id, 'debit' => 0, 'credit' => $locked->total_amount],
                ],
            ], $actor);
            $this->ledger->post($entry, $actor);

            if ($locked->vendor_id) {
                $vendor = AccountVendor::forWorkspace($locked->organization_id, $locked->workspace_id)->find($locked->vendor_id);
                if ($vendor) {
                    $this->balances->syncVendorBalance($vendor);
                }
            }

            return $entry->refresh();
        });
    }

    public function postSalesReturn(SalesInvoiceReturn $return, SalesInvoice $invoice, User $actor): AccountCreditNote
    {
        return DB::transaction(function () use ($return, $invoice, $actor) {
            $existing = AccountCreditNote::forWorkspace($return->organization_id, $return->workspace_id)
                ->where('source_type', SalesInvoiceReturn::class)
                ->where('source_id', $return->id)
                ->first();
            if ($existing) {
                return $existing;
            }

            $accounts = $this->systemAccounts($return->organization_id, $return->workspace_id);
            $reference = 'SRETURN-'.$return->return_id;
            $entry = $this->postedEntry($return->organization_id, $return->workspace_id, $reference);
            if (! $entry) {
                $entry = $this->ledger->createEntry($return->organization_id, $return->workspace_id, [
                    'entry_date' => now()->toDateString(),
                    'reference' => $reference,
                    'description' => 'Sales return '.$return->return_id,
                    'lines' => [
                        ['account_id' => $accounts['sales_revenue']->id, 'debit' => $return->total_amount, 'credit' => 0],
                        ['account_id' => $accounts['receivable']->id, 'debit' => 0, 'credit' => $return->total_amount],
                    ],
                ], $actor);
                $this->ledger->post($entry, $actor);
            }

            $customerId = null;
            if ($invoice->customer_id && AccountCustomer::where('organization_id', $return->organization_id)->where('id', $invoice->customer_id)->exists()) {
                $customerId = $invoice->customer_id;
            }

            $note = AccountCreditNote::create([
                'organization_id' => $return->organization_id,
                'workspace_id' => $return->workspace_id,
                'invoice_id' => $invoice->id,
                'customer_id' => $customerId,
                'amount' => $return->total_amount,
                'date' => now()->toDateString(),
                'description' => 'Credit from sales return '.$return->return_id,
                'source_type' => SalesInvoiceReturn::class,
                'source_id' => $return->id,
                'status' => 'applied',
                'journal_entry_id' => $entry->id,
                'approved_at' => now(),
                'approved_by' => $actor->id,
                'created_by' => $actor->id,
            ]);

            if ($customerId) {
                $customer = AccountCustomer::forWorkspace($return->organization_id, $return->workspace_id)->find($customerId);
                if ($customer) {
                    $this->balances->syncCustomerBalance($customer);
                }
            }

            return $note;
        });
    }

    public function postPurchaseReturn(PurchaseReturn $return, PurchaseInvoice $invoice, User $actor): AccountDebitNote
    {
        return DB::transaction(function () use ($return, $invoice, $actor) {
            $existing = AccountDebitNote::forWorkspace($return->organization_id, $return->workspace_id)
                ->where('source_type', PurchaseReturn::class)
                ->where('source_id', $return->id)
                ->first();
            if ($existing) {
                return $existing;
            }

            $accounts = $this->systemAccounts($return->organization_id, $return->workspace_id);
            $reference = 'PRETURN-'.$return->return_id;
            $entry = $this->postedEntry($return->organization_id, $return->workspace_id, $reference);
            if (! $entry) {
                $entry = $this->ledger->createEntry($return->organization_id, $return->workspace_id, [
                    'entry_date' => now()->toDateString(),
                    'reference' => $reference,
                    'description' => 'Purchase return '.$return->return_id,
                    'lines' => [
                        ['account_id' => $accounts['payable']->id, 'debit' => $return->total_amount, 'credit' => 0],
                        ['account_id' => $accounts['purchase_expense']->id, 'debit' => 0, 'credit' => $return->total_amount],
                    ],
                ], $actor);
                $this->ledger->post($entry, $actor);
            }

            $vendorId = null;
            if ($invoice->vendor_id && AccountVendor::where('organization_id', $return->organization_id)->where('id', $invoice->vendor_id)->exists()) {
                $vendorId = $invoice->vendor_id;
            }

            $note = AccountDebitNote::create([
                'organization_id' => $return->organization_id,
                'workspace_id' => $return->workspace_id,
                'purchase_invoice_id' => $invoice->id,
                'vendor_id' => $vendorId,
                'amount' => $return->total_amount,
                'date' => now()->toDateString(),
                'description' => 'Debit from purchase return '.$return->return_id,
                'source_type' => PurchaseReturn::class,
                'source_id' => $return->id,
                'status' => 'applied',
                'journal_entry_id' => $entry->id,
                'approved_at' => now(),
                'approved_by' => $actor->id,
                'created_by' => $actor->id,
            ]);

            if ($vendorId) {
                $vendor = AccountVendor::forWorkspace($return->organization_id, $return->workspace_id)->find($vendorId);
                if ($vendor) {
                    $this->balances->syncVendorBalance($vendor);
                }
            }

            return $note;
        });
    }

    /**
     * @return array{receivable: LedgerAccount, payable: LedgerAccount, sales_revenue: LedgerAccount, purchase_expense: LedgerAccount}
     */
    public function systemAccounts(int $organizationId, int $workspaceId): array
    {
        $currency = LedgerAccount::forWorkspace($organizationId, $workspaceId)->value('currency') ?: 'USD';

        $asset = AccountType::firstOrCreate(
            ['workspace_id' => $workspaceId, 'name' => 'Assets'],
            ['organization_id' => $organizationId, 'classification' => 'asset', 'normal_balance' => 'debit']
        );
        $liability = AccountType::firstOrCreate(
            ['workspace_id' => $workspaceId, 'name' => 'Liabilities'],
            ['organization_id' => $organizationId, 'classification' => 'liability', 'normal_balance' => 'credit']
        );
        $income = AccountType::firstOrCreate(
            ['workspace_id' => $workspaceId, 'name' => 'Income'],
            ['organization_id' => $organizationId, 'classification' => 'income', 'normal_balance' => 'credit']
        );
        $expense = AccountType::firstOrCreate(
            ['workspace_id' => $workspaceId, 'name' => 'Expenses'],
            ['organization_id' => $organizationId, 'classification' => 'expense', 'normal_balance' => 'debit']
        );

        return [
            'receivable' => LedgerAccount::firstOrCreate(
                ['workspace_id' => $workspaceId, 'code' => '1200'],
                ['organization_id' => $organizationId, 'account_type_id' => $asset->id, 'name' => 'Accounts Receivable', 'currency' => $currency, 'is_bank' => false, 'is_active' => true]
            ),
            'payable' => LedgerAccount::firstOrCreate(
                ['workspace_id' => $workspaceId, 'code' => '2000'],
                ['organization_id' => $organizationId, 'account_type_id' => $liability->id, 'name' => 'Accounts Payable', 'currency' => $currency, 'is_bank' => false, 'is_active' => true]
            ),
            'sales_revenue' => LedgerAccount::firstOrCreate(
                ['workspace_id' => $workspaceId, 'code' => '4000'],
                ['organization_id' => $organizationId, 'account_type_id' => $income->id, 'name' => 'Sales Revenue', 'currency' => $currency, 'is_bank' => false, 'is_active' => true]
            ),
            'purchase_expense' => LedgerAccount::firstOrCreate(
                ['workspace_id' => $workspaceId, 'code' => '5000'],
                ['organization_id' => $organizationId, 'account_type_id' => $expense->id, 'name' => 'Purchases / Cost', 'currency' => $currency, 'is_bank' => false, 'is_active' => true]
            ),
        ];
    }

    private function postedEntry(int $organizationId, int $workspaceId, string $reference): ?JournalEntry
    {
        return JournalEntry::where('organization_id', $organizationId)
            ->where('workspace_id', $workspaceId)
            ->where('reference', $reference)
            ->where('status', 'posted')
            ->first();
    }
}

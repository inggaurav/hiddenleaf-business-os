<?php

namespace App\Domain\Accounting;

use App\Models\AccountCreditNote;
use App\Models\AccountCustomer;
use App\Models\AccountDebitNote;
use App\Models\AccountExpense;
use App\Models\AccountRevenue;
use App\Models\AccountVendor;
use App\Models\CustomerPayment;
use App\Models\JournalEntry;
use App\Models\LedgerAccount;
use App\Models\PurchaseInvoice;
use App\Models\SalesInvoice;
use App\Models\VendorPayment;
use App\Models\Workspace;
use Carbon\Carbon;

class AccountDashboardService
{
    public function __construct(
        protected LedgerService $ledgerService
    ) {}

    public function getMetrics(Workspace $workspace): array
    {
        $orgId = $workspace->organization_id;
        $wsId = $workspace->id;

        $totalClients = AccountCustomer::forWorkspace($orgId, $wsId)->count();
        $totalVendors = AccountVendor::forWorkspace($orgId, $wsId)->count();

        $totalCustomerPayment = Money::of(CustomerPayment::forWorkspace($orgId, $wsId)->sum('amount'));
        $totalVendorPayment = Money::of(VendorPayment::forWorkspace($orgId, $wsId)->sum('amount'));

        $balances = $this->ledgerService->balances($orgId, $wsId);
        $byClass = $balances
            ->groupBy(fn ($account) => $account->type->classification ?? 'asset')
            ->map->sum('balance');

        // Explicit metric semantics: direct transactions never replace ledger income,
        // and ledger income never masquerades as direct WorkDo-style revenue records.
        $directRevenue = Money::of(AccountRevenue::forWorkspace($orgId, $wsId)->sum('amount'));
        $directExpense = Money::of(AccountExpense::forWorkspace($orgId, $wsId)->sum('amount'));
        $accountingIncome = Money::of($byClass['income'] ?? 0);
        $accountingExpense = Money::of($byClass['expense'] ?? 0);
        $directNetProfit = $directRevenue->subtract($directExpense);
        $netAccountingIncome = $accountingIncome->subtract($accountingExpense);

        $bankAccounts = LedgerAccount::forWorkspace($orgId, $wsId)->where('is_bank', true)->get();
        $cashBankBalance = Money::of($balances->whereIn('id', $bankAccounts->pluck('id'))->sum('balance'));

        // Receivable = posted/sent/partial invoice total - applied payments - applied credit notes.
        $invoiceTotal = Money::of(
            SalesInvoice::where('organization_id', $orgId)
                ->where('workspace_id', $wsId)
                ->where(fn ($q) => $q->whereIn('status', ['posted', 'sent', 'partial', 1, 2]))
                ->sum('total_amount')
        );
        $appliedCustomerPayments = Money::of(
            CustomerPayment::forWorkspace($orgId, $wsId)
                ->whereNotNull('invoice_id')
                ->sum('amount')
        );
        $appliedCreditNotes = Money::of(
            AccountCreditNote::forWorkspace($orgId, $wsId)
                ->whereNotNull('invoice_id')
                ->sum('amount')
        );
        $receivables = $invoiceTotal
            ->subtract($appliedCustomerPayments)
            ->subtract($appliedCreditNotes)
            ->max(Money::zero());

        // Payable = posted/ordered/partial purchase total - applied payments - applied debit notes.
        $purchaseTotal = Money::of(
            PurchaseInvoice::where('organization_id', $orgId)
                ->where('workspace_id', $wsId)
                ->where(fn ($q) => $q->whereIn('status', ['posted', 'ordered', 'partial', 1, 2]))
                ->sum('total_amount')
        );
        $appliedVendorPayments = Money::of(
            VendorPayment::forWorkspace($orgId, $wsId)
                ->whereNotNull('purchase_invoice_id')
                ->sum('amount')
        );
        $appliedDebitNotes = Money::of(
            AccountDebitNote::forWorkspace($orgId, $wsId)
                ->whereNotNull('purchase_invoice_id')
                ->sum('amount')
        );
        $payables = $purchaseTotal
            ->subtract($appliedVendorPayments)
            ->subtract($appliedDebitNotes)
            ->max(Money::zero());

        $monthlyCustomerPayments = [];
        $monthlyVendorPayments = [];

        for ($i = 5; $i >= 0; $i--) {
            $targetDate = Carbon::now()->subMonths($i);
            $monthLabel = $targetDate->format('M');
            $year = $targetDate->year;
            $month = $targetDate->month;

            $custAmount = Money::of(
                CustomerPayment::forWorkspace($orgId, $wsId)
                    ->whereYear('payment_date', $year)
                    ->whereMonth('payment_date', $month)
                    ->sum('amount')
            );

            $vendAmount = Money::of(
                VendorPayment::forWorkspace($orgId, $wsId)
                    ->whereYear('payment_date', $year)
                    ->whereMonth('payment_date', $month)
                    ->sum('amount')
            );

            $monthlyCustomerPayments[] = [
                'month' => $monthLabel,
                'customer_payments' => $custAmount->toFloat(),
            ];

            $monthlyVendorPayments[] = [
                'month' => $monthLabel,
                'vendor_payments' => $vendAmount->toFloat(),
            ];
        }

        $recentRevenues = AccountRevenue::forWorkspace($orgId, $wsId)
            ->with('customer')
            ->latest('date')
            ->limit(5)
            ->get()
            ->map(fn ($revenue) => [
                'id' => $revenue->id,
                'title' => $revenue->reference ?? 'REV-'.$revenue->id,
                'description' => ($revenue->customer?->name ?? 'Direct Revenue')
                    .($revenue->payment_method ? ' via '.ucfirst($revenue->payment_method) : ''),
                'amount' => Money::of($revenue->amount)->toFloat(),
                'date' => $revenue->date ? $revenue->date->toDateString() : now()->toDateString(),
                'status' => 'received',
            ]);

        $recentExpenses = AccountExpense::forWorkspace($orgId, $wsId)
            ->with('vendor')
            ->latest('date')
            ->limit(5)
            ->get()
            ->map(fn ($expense) => [
                'id' => $expense->id,
                'title' => $expense->reference ?? 'EXP-'.$expense->id,
                'description' => ($expense->vendor?->name ?? 'Direct Expense')
                    .($expense->payment_method ? ' via '.ucfirst($expense->payment_method) : ''),
                'amount' => Money::of($expense->amount)->toFloat(),
                'date' => $expense->date ? $expense->date->toDateString() : now()->toDateString(),
                'status' => 'paid',
            ]);

        $recentJournals = JournalEntry::where('organization_id', $orgId)
            ->where('workspace_id', $wsId)
            ->latest('entry_date')
            ->limit(5)
            ->get()
            ->map(fn ($journal) => [
                'id' => $journal->id,
                'reference' => $journal->reference ?? 'JRN-'.$journal->id,
                'description' => $journal->description ?? 'Journal Entry',
                'entry_date' => $journal->entry_date,
                'status' => $journal->status,
            ]);

        return [
            'stats' => [
                'total_clients' => $totalClients,
                'total_vendors' => $totalVendors,

                // WorkDo-equivalent direct Revenue / Expense entities.
                'total_revenue' => $directRevenue->toFloat(),
                'total_expense' => $directExpense->toFloat(),
                'net_profit' => $directNetProfit->toFloat(),

                // Explicit ledger-based accounting metrics.
                'direct_revenue' => $directRevenue->toFloat(),
                'direct_expense' => $directExpense->toFloat(),
                'accounting_income' => $accountingIncome->toFloat(),
                'accounting_expense' => $accountingExpense->toFloat(),
                'net_accounting_income' => $netAccountingIncome->toFloat(),

                'total_customer_payment' => $totalCustomerPayment->toFloat(),
                'total_vendor_payment' => $totalVendorPayment->toFloat(),
                'cash_bank_balance' => $cashBankBalance->toFloat(),
                'receivables' => $receivables->toFloat(),
                'payables' => $payables->toFloat(),
            ],
            'metricSemantics' => [
                'total_revenue' => 'Sum of account_revenues direct revenue transactions.',
                'total_expense' => 'Sum of account_expenses direct expense transactions.',
                'accounting_income' => 'Net balance of ledger accounts classified as income.',
                'accounting_expense' => 'Net balance of ledger accounts classified as expense.',
                'customer_payments' => 'Recorded customer_payments grouped by payment_date.',
                'vendor_payments' => 'Recorded vendor_payments grouped by payment_date.',
            ],
            'monthlyCustomerPayments' => $monthlyCustomerPayments,
            'monthlyVendorPayments' => $monthlyVendorPayments,
            'recentRevenues' => $recentRevenues,
            'recentExpenses' => $recentExpenses,
            'recentJournals' => $recentJournals,
            'financialHealth' => [
                'assets' => Money::of($byClass['asset'] ?? 0)->toFloat(),
                'liabilities' => Money::of($byClass['liability'] ?? 0)->toFloat(),
                'equity' => Money::of($byClass['equity'] ?? 0)->toFloat(),
                'net_income' => $netAccountingIncome->toFloat(),
            ],
        ];
    }
}

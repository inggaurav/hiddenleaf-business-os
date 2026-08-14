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

        // 1. Customer & Vendor counts — ONLY from canonical entities, no fallbacks
        $totalClients = AccountCustomer::forWorkspace($orgId, $wsId)->count();
        $totalVendors = AccountVendor::forWorkspace($orgId, $wsId)->count();

        // 2. Customer & Vendor Payments — ONLY from payment records, no invoice fallback
        $totalCustomerPayment = Money::of(CustomerPayment::forWorkspace($orgId, $wsId)->sum('amount'));
        $totalVendorPayment = Money::of(VendorPayment::forWorkspace($orgId, $wsId)->sum('amount'));

        // 3. Ledger Balances & Revenue / Expense from canonical sources
        $balances = $this->ledgerService->balances($orgId, $wsId);
        $byClass = $balances->groupBy(fn ($account) => $account->type->classification ?? 'asset')->map->sum('balance');

        $ledgerRevenue = Money::of($byClass['income'] ?? 0);
        $ledgerExpense = Money::of($byClass['expense'] ?? 0);

        $directRevenue = Money::of(AccountRevenue::forWorkspace($orgId, $wsId)->sum('amount'));
        $directExpense = Money::of(AccountExpense::forWorkspace($orgId, $wsId)->sum('amount'));

        $revenue = $directRevenue->isPositive() ? $directRevenue : $ledgerRevenue;
        $expense = $directExpense->isPositive() ? $directExpense : $ledgerExpense;
        $netProfit = $revenue->subtract($expense);

        // 4. Cash & Bank balances
        $bankAccounts = LedgerAccount::forWorkspace($orgId, $wsId)->where('is_bank', true)->get();
        $cashBankBalance = Money::of($balances->whereIn('id', $bankAccounts->pluck('id'))->sum('balance'));

        // 5. Receivables & Payables — correct partial calculation
        //    receivable = invoice total - applied payments - applied credit notes
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
        $receivables = $invoiceTotal->subtract($appliedCustomerPayments)->subtract($appliedCreditNotes)->max(Money::zero());

        //    payable = purchase total - applied payments - applied debit notes
        $purchaseTotal = Money::of(
            PurchaseInvoice::where('organization_id', $orgId)
                ->where('workspace_id', $wsId)
                ->where(fn ($q) => $q->whereIn('status', ['posted', 'ordered', 1, 2]))
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
        $payables = $purchaseTotal->subtract($appliedVendorPayments)->subtract($appliedDebitNotes)->max(Money::zero());

        // 6. Monthly trends (past 6 months) — ONLY from payment records by payment_date
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

        // 7. Recent Revenues — ONLY from AccountRevenue, empty if none
        $recentRevenues = AccountRevenue::forWorkspace($orgId, $wsId)->with('customer')->latest('date')->limit(5)->get()
            ->map(fn ($r) => [
                'id' => $r->id,
                'title' => $r->reference ?? 'REV-' . $r->id,
                'description' => ($r->customer?->name ?? 'Direct Revenue') . ($r->payment_method ? ' via ' . ucfirst($r->payment_method) : ''),
                'amount' => Money::of($r->amount)->toFloat(),
                'date' => $r->date ? $r->date->toDateString() : now()->toDateString(),
                'status' => 'received',
            ]);

        // 8. Recent Expenses — ONLY from AccountExpense, empty if none
        $recentExpenses = AccountExpense::forWorkspace($orgId, $wsId)->with('vendor')->latest('date')->limit(5)->get()
            ->map(fn ($e) => [
                'id' => $e->id,
                'title' => $e->reference ?? 'EXP-' . $e->id,
                'description' => ($e->vendor?->name ?? 'Direct Expense') . ($e->payment_method ? ' via ' . ucfirst($e->payment_method) : ''),
                'amount' => Money::of($e->amount)->toFloat(),
                'date' => $e->date ? $e->date->toDateString() : now()->toDateString(),
                'status' => 'paid',
            ]);

        // 9. Recent Journals
        $recentJournals = JournalEntry::where('organization_id', $orgId)
            ->where('workspace_id', $wsId)
            ->latest('entry_date')
            ->limit(5)
            ->get()
            ->map(fn ($j) => [
                'id' => $j->id,
                'reference' => $j->reference ?? 'JRN-' . $j->id,
                'description' => $j->description ?? 'Journal Entry',
                'entry_date' => $j->entry_date,
                'status' => $j->status,
            ]);

        return [
            'stats' => [
                'total_clients' => $totalClients,
                'total_vendors' => $totalVendors,
                'total_revenue' => $revenue->toFloat(),
                'total_expense' => $expense->toFloat(),
                'total_customer_payment' => $totalCustomerPayment->toFloat(),
                'total_vendor_payment' => $totalVendorPayment->toFloat(),
                'net_profit' => $netProfit->toFloat(),
                'cash_bank_balance' => $cashBankBalance->toFloat(),
                'receivables' => $receivables->toFloat(),
                'payables' => $payables->toFloat(),
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
                'net_income' => $netProfit->toFloat(),
            ],
        ];
    }
}

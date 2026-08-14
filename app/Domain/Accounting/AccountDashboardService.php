<?php

namespace App\Domain\Accounting;

use App\Models\AccountCustomer;
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

        // 1. Real Customer & Vendor entity counts
        $customerCount = AccountCustomer::forWorkspace($orgId, $wsId)->count();
        $vendorCount = AccountVendor::forWorkspace($orgId, $wsId)->count();

        $invoiceClients = (int) SalesInvoice::where('organization_id', $orgId)
            ->where('workspace_id', $wsId)
            ->distinct('customer_id')
            ->count('customer_id');

        $billVendors = (int) PurchaseInvoice::where('organization_id', $orgId)
            ->where('workspace_id', $wsId)
            ->distinct('vendor_id')
            ->count('vendor_id');

        $totalClients = $customerCount > 0 ? $customerCount : $invoiceClients;
        $totalVendors = $vendorCount > 0 ? $vendorCount : $billVendors;

        // 2. Customer Payments & Vendor Payments
        $recordedCustomerPayments = (float) CustomerPayment::forWorkspace($orgId, $wsId)->sum('amount');
        $invoiceCustomerPayments = (float) SalesInvoice::where('organization_id', $orgId)
            ->where('workspace_id', $wsId)
            ->where(fn ($q) => $q->where('status', 'paid')->orWhere('status', 3))
            ->sum('total_amount');

        $totalCustomerPayment = $recordedCustomerPayments > 0 ? $recordedCustomerPayments : $invoiceCustomerPayments;

        $recordedVendorPayments = (float) VendorPayment::forWorkspace($orgId, $wsId)->sum('amount');
        $billVendorPayments = (float) PurchaseInvoice::where('organization_id', $orgId)
            ->where('workspace_id', $wsId)
            ->where(fn ($q) => $q->where('status', 'paid')->orWhere('status', 3))
            ->sum('total_amount');

        $totalVendorPayment = $recordedVendorPayments > 0 ? $recordedVendorPayments : $billVendorPayments;

        // 3. Ledger Balances & Revenue / Expense
        $balances = $this->ledgerService->balances($orgId, $wsId);
        $byClass = $balances->groupBy(fn ($account) => $account->type->classification ?? 'asset')->map->sum('balance');

        $revenue = (float) ($byClass['income'] ?? 0);
        $expense = (float) ($byClass['expense'] ?? 0);

        $directRevenue = (float) AccountRevenue::forWorkspace($orgId, $wsId)->sum('amount');
        $directExpense = (float) AccountExpense::forWorkspace($orgId, $wsId)->sum('amount');

        if ($revenue == 0) {
            $invoiceRevenue = (float) SalesInvoice::where('organization_id', $orgId)
                ->where('workspace_id', $wsId)
                ->where(fn ($q) => $q->whereNotIn('status', ['draft', 0]))
                ->sum('total_amount');
            $revenue = $directRevenue > 0 ? $directRevenue : $invoiceRevenue;
        }

        if ($expense == 0) {
            $billExpense = (float) PurchaseInvoice::where('organization_id', $orgId)
                ->where('workspace_id', $wsId)
                ->where(fn ($q) => $q->whereNotIn('status', ['draft', 0]))
                ->sum('total_amount');
            $expense = $directExpense > 0 ? $directExpense : $billExpense;
        }

        $netProfit = $revenue - $expense;

        // 4. Cash & Bank balances
        $bankAccounts = LedgerAccount::forWorkspace($orgId, $wsId)->where('is_bank', true)->get();
        $cashBankBalance = (float) $balances->whereIn('id', $bankAccounts->pluck('id'))->sum('balance');

        // 5. Receivables & Payables
        $receivables = (float) SalesInvoice::where('organization_id', $orgId)
            ->where('workspace_id', $wsId)
            ->where(fn ($q) => $q->whereIn('status', ['posted', 'sent', 'partial', 1, 2]))
            ->sum('total_amount');

        $payables = (float) PurchaseInvoice::where('organization_id', $orgId)
            ->where('workspace_id', $wsId)
            ->where(fn ($q) => $q->whereIn('status', ['posted', 'ordered', 1, 2]))
            ->sum('total_amount');

        // 6. Monthly trends (past 6 months)
        $monthlyCustomerPayments = [];
        $monthlyVendorPayments = [];

        for ($i = 5; $i >= 0; $i--) {
            $targetDate = Carbon::now()->subMonths($i);
            $monthLabel = $targetDate->format('M');
            $year = $targetDate->year;
            $month = $targetDate->month;

            $custAmount = (float) CustomerPayment::forWorkspace($orgId, $wsId)
                ->whereYear('payment_date', $year)
                ->whereMonth('payment_date', $month)
                ->sum('amount');

            if ($custAmount == 0) {
                $custAmount = (float) SalesInvoice::where('organization_id', $orgId)
                    ->where('workspace_id', $wsId)
                    ->where(fn ($q) => $q->where('status', 'paid')->orWhere('status', 3))
                    ->whereYear('issue_date', $year)
                    ->whereMonth('issue_date', $month)
                    ->sum('total_amount');
            }

            $vendAmount = (float) VendorPayment::forWorkspace($orgId, $wsId)
                ->whereYear('payment_date', $year)
                ->whereMonth('payment_date', $month)
                ->sum('amount');

            if ($vendAmount == 0) {
                $vendAmount = (float) PurchaseInvoice::where('organization_id', $orgId)
                    ->where('workspace_id', $wsId)
                    ->where(fn ($q) => $q->where('status', 'paid')->orWhere('status', 3))
                    ->whereYear('purchase_date', $year)
                    ->whereMonth('purchase_date', $month)
                    ->sum('total_amount');
            }

            $monthlyCustomerPayments[] = [
                'month' => $monthLabel,
                'customer_payments' => $custAmount,
            ];

            $monthlyVendorPayments[] = [
                'month' => $monthLabel,
                'vendor_payments' => $vendAmount,
            ];
        }

        // 7. Recent Revenues
        $directRevs = AccountRevenue::forWorkspace($orgId, $wsId)->with('customer')->latest('date')->limit(5)->get();
        if ($directRevs->isNotEmpty()) {
            $recentRevenues = $directRevs->map(fn ($r) => [
                'id' => $r->id,
                'title' => $r->reference ?? 'REV-' . $r->id,
                'description' => ($r->customer?->name ?? 'Direct Revenue') . ($r->payment_method ? ' via ' . ucfirst($r->payment_method) : ''),
                'amount' => (float) $r->amount,
                'date' => $r->date ? $r->date->toDateString() : now()->toDateString(),
                'status' => 'received',
            ]);
        } else {
            $recentRevenues = SalesInvoice::where('organization_id', $orgId)
                ->where('workspace_id', $wsId)
                ->latest('issue_date')
                ->limit(5)
                ->get()
                ->map(fn ($inv) => [
                    'id' => $inv->id,
                    'title' => (string) ($inv->invoice_id ?? 'INV-' . $inv->id),
                    'description' => 'Customer #' . ($inv->customer_id ?? $inv->id) . ' (' . ucfirst((string) $inv->status) . ')',
                    'amount' => (float) $inv->total_amount,
                    'date' => $inv->issue_date ? $inv->issue_date->toDateString() : now()->toDateString(),
                    'status' => (string) $inv->status,
                ]);
        }

        // 8. Recent Expenses
        $directExps = AccountExpense::forWorkspace($orgId, $wsId)->with('vendor')->latest('date')->limit(5)->get();
        if ($directExps->isNotEmpty()) {
            $recentExpenses = $directExps->map(fn ($e) => [
                'id' => $e->id,
                'title' => $e->reference ?? 'EXP-' . $e->id,
                'description' => ($e->vendor?->name ?? 'Direct Expense') . ($e->payment_method ? ' via ' . ucfirst($e->payment_method) : ''),
                'amount' => (float) $e->amount,
                'date' => $e->date ? $e->date->toDateString() : now()->toDateString(),
                'status' => 'paid',
            ]);
        } else {
            $recentExpenses = PurchaseInvoice::where('organization_id', $orgId)
                ->where('workspace_id', $wsId)
                ->latest('purchase_date')
                ->limit(5)
                ->get()
                ->map(fn ($bill) => [
                    'id' => $bill->id,
                    'title' => (string) ($bill->invoice_id ?? 'BILL-' . $bill->id),
                    'description' => 'Vendor #' . ($bill->vendor_id ?? $bill->id) . ' (' . ucfirst((string) $bill->status) . ')',
                    'amount' => (float) $bill->total_amount,
                    'date' => $bill->purchase_date ? $bill->purchase_date->toDateString() : now()->toDateString(),
                    'status' => (string) $bill->status,
                ]);
        }

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
                'total_clients' => max($totalClients, $recentRevenues->count()),
                'total_vendors' => max($totalVendors, $recentExpenses->count()),
                'total_revenue' => $revenue,
                'total_expense' => $expense,
                'total_customer_payment' => $totalCustomerPayment,
                'total_vendor_payment' => $totalVendorPayment,
                'net_profit' => $netProfit,
                'cash_bank_balance' => $cashBankBalance,
                'receivables' => $receivables,
                'payables' => $payables,
            ],
            'monthlyCustomerPayments' => $monthlyCustomerPayments,
            'monthlyVendorPayments' => $monthlyVendorPayments,
            'recentRevenues' => $recentRevenues,
            'recentExpenses' => $recentExpenses,
            'recentJournals' => $recentJournals,
            'financialHealth' => [
                'assets' => (float) ($byClass['asset'] ?? 0),
                'liabilities' => (float) ($byClass['liability'] ?? 0),
                'equity' => (float) ($byClass['equity'] ?? 0),
                'net_income' => $netProfit,
            ],
        ];
    }
}

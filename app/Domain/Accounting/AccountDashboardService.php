<?php

namespace App\Domain\Accounting;

use App\Models\AccountBankTransfer;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use App\Models\LedgerAccount;
use App\Models\PurchaseInvoice;
use App\Models\SalesInvoice;
use App\Models\Workspace;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class AccountDashboardService
{
    public function __construct(
        protected LedgerService $ledgerService
    ) {}

    public function getMetrics(Workspace $workspace): array
    {
        $orgId = $workspace->organization_id;
        $wsId = $workspace->id;

        // Clients & Vendors count
        $totalClients = (int) SalesInvoice::where('organization_id', $orgId)
            ->where('workspace_id', $wsId)
            ->distinct('customer_id')
            ->count('customer_id');

        $totalVendors = (int) PurchaseInvoice::where('organization_id', $orgId)
            ->where('workspace_id', $wsId)
            ->distinct('vendor_id')
            ->count('vendor_id');

        // Customer Payments & Vendor Payments (status 3 = paid, or status 'paid')
        $totalCustomerPayment = (float) SalesInvoice::where('organization_id', $orgId)
            ->where('workspace_id', $wsId)
            ->where(fn ($q) => $q->where('status', 'paid')->orWhere('status', 3))
            ->sum('total_amount');

        $totalVendorPayment = (float) PurchaseInvoice::where('organization_id', $orgId)
            ->where('workspace_id', $wsId)
            ->where(fn ($q) => $q->where('status', 'paid')->orWhere('status', 3))
            ->sum('total_amount');

        // Balances from Ledger
        $balances = $this->ledgerService->balances($orgId, $wsId);
        $byClass = $balances->groupBy(fn ($account) => $account->type->classification ?? 'asset')->map->sum('balance');

        $revenue = (float) ($byClass['income'] ?? 0);
        $expense = (float) ($byClass['expense'] ?? 0);
        
        // If no ledger postings yet, fallback to posted sales and purchases
        if ($revenue == 0) {
            $revenue = (float) SalesInvoice::where('organization_id', $orgId)
                ->where('workspace_id', $wsId)
                ->where(fn ($q) => $q->whereNotIn('status', ['draft', 0]))
                ->sum('total_amount');
        }

        if ($expense == 0) {
            $expense = (float) PurchaseInvoice::where('organization_id', $orgId)
                ->where('workspace_id', $wsId)
                ->where(fn ($q) => $q->whereNotIn('status', ['draft', 0]))
                ->sum('total_amount');
        }

        $netProfit = $revenue - $expense;

        // Cash & Bank balance
        $bankAccounts = LedgerAccount::forWorkspace($orgId, $wsId)->where('is_bank', true)->get();
        $cashBankBalance = (float) $balances->whereIn('id', $bankAccounts->pluck('id'))->sum('balance');

        // Receivables & Payables
        $receivables = (float) SalesInvoice::where('organization_id', $orgId)
            ->where('workspace_id', $wsId)
            ->where(fn ($q) => $q->whereIn('status', ['posted', 'sent', 'partial', 1, 2]))
            ->sum('total_amount');

        $payables = (float) PurchaseInvoice::where('organization_id', $orgId)
            ->where('workspace_id', $wsId)
            ->where(fn ($q) => $q->whereIn('status', ['posted', 'ordered', 1, 2]))
            ->sum('total_amount');

        // Monthly trends (past 6 months)
        $monthlyCustomerPayments = [];
        $monthlyVendorPayments = [];

        for ($i = 5; $i >= 0; $i--) {
            $targetDate = Carbon::now()->subMonths($i);
            $monthLabel = $targetDate->format('M');
            $year = $targetDate->year;
            $month = $targetDate->month;

            $custAmount = (float) SalesInvoice::where('organization_id', $orgId)
                ->where('workspace_id', $wsId)
                ->where(fn ($q) => $q->where('status', 'paid')->orWhere('status', 3))
                ->whereYear('issue_date', $year)
                ->whereMonth('issue_date', $month)
                ->sum('total_amount');

            $vendAmount = (float) PurchaseInvoice::where('organization_id', $orgId)
                ->where('workspace_id', $wsId)
                ->where(fn ($q) => $q->where('status', 'paid')->orWhere('status', 3))
                ->whereYear('purchase_date', $year)
                ->whereMonth('purchase_date', $month)
                ->sum('total_amount');

            $monthlyCustomerPayments[] = [
                'month' => $monthLabel,
                'customer_payments' => $custAmount,
            ];

            $monthlyVendorPayments[] = [
                'month' => $monthLabel,
                'vendor_payments' => $vendAmount,
            ];
        }

        // Recent Revenues
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

        // Recent Expenses
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

        // Recent Journals
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

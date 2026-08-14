<?php

namespace App\Domain\Procurement;

use App\Models\PurchaseInvoice;
use App\Models\PurchaseReturn;
use App\Models\Warehouse;
use App\Models\Workspace;
use Carbon\Carbon;

class ProcurementDashboardService
{
    public function getMetrics(Workspace $workspace): array
    {
        $orgId = $workspace->organization_id;
        $wsId = $workspace->id;

        $invoices = PurchaseInvoice::where('organization_id', $orgId)->where('workspace_id', $wsId);
        $totalInvoices = (clone $invoices)->count();
        $draftInvoices = (clone $invoices)->where(fn ($q) => $q->where('status', 'draft')->orWhere('status', 0))->count();
        $postedInvoices = (clone $invoices)->where(fn ($q) => $q->whereIn('status', ['posted', 'ordered', 1, 2]))->count();
        $paidInvoices = (clone $invoices)->where(fn ($q) => $q->where('status', 'paid')->orWhere('status', 3))->count();

        $totalPurchasingAmount = (float) (clone $invoices)->where(fn ($q) => $q->whereNotIn('status', ['draft', 0]))->sum('total_amount');
        $outstandingPayables = (float) (clone $invoices)->where(fn ($q) => $q->whereIn('status', ['posted', 'ordered', 1, 2]))->sum('total_amount');

        $returns = PurchaseReturn::where('organization_id', $orgId)->where('workspace_id', $wsId);
        $totalReturns = (clone $returns)->count();
        $returnsAmount = (float) (clone $returns)->sum('total_amount');

        $warehousesCount = Warehouse::where('organization_id', $orgId)->where('workspace_id', $wsId)->count();

        // Monthly purchases trend (past 6 months)
        $monthlyPurchases = [];
        for ($i = 5; $i >= 0; $i--) {
            $date = Carbon::now()->subMonths($i);
            $monthName = $date->format('M');
            $year = $date->year;
            $month = $date->month;

            $purchases = (float) PurchaseInvoice::where('organization_id', $orgId)
                ->where('workspace_id', $wsId)
                ->where(fn ($q) => $q->whereNotIn('status', ['draft', 0]))
                ->whereYear('purchase_date', $year)
                ->whereMonth('purchase_date', $month)
                ->sum('total_amount');

            $monthlyPurchases[] = [
                'month' => $monthName,
                'purchases' => $purchases,
            ];
        }

        // Recent Purchase Invoices
        $recentInvoices = (clone $invoices)->latest('purchase_date')->limit(5)->get()->map(fn ($inv) => [
            'id' => $inv->id,
            'invoice_number' => (string) ($inv->invoice_id ?? 'BILL-'.$inv->id),
            'vendor_name' => 'Vendor #'.($inv->vendor_id ?? $inv->id),
            'grand_total' => (float) $inv->total_amount,
            'status' => (string) $inv->status,
            'issue_date' => $inv->purchase_date ? $inv->purchase_date->toDateString() : now()->toDateString(),
        ]);

        // Recent Purchase Returns
        $recentReturns = (clone $returns)->latest('created_at')->limit(5)->get()->map(fn ($ret) => [
            'id' => $ret->id,
            'return_number' => (string) ($ret->return_number ?? 'PUR-RET-'.$ret->id),
            'vendor_name' => 'Vendor #'.($ret->vendor_id ?? $ret->id),
            'total_amount' => (float) $ret->total_amount,
            'status' => (string) ($ret->status ?? 'pending'),
            'created_at' => $ret->created_at ? $ret->created_at->format('M d, Y') : now()->format('M d, Y'),
        ]);

        return [
            'stats' => [
                'total_purchase_invoices' => $totalInvoices,
                'draft_purchase_invoices' => $draftInvoices,
                'posted_purchase_invoices' => $postedInvoices,
                'paid_purchase_invoices' => $paidInvoices,
                'total_purchasing_amount' => $totalPurchasingAmount,
                'outstanding_payables' => $outstandingPayables,
                'total_purchase_returns' => $totalReturns,
                'purchase_returns_amount' => $returnsAmount,
                'warehouses_count' => $warehousesCount,
            ],
            'monthlyPurchases' => $monthlyPurchases,
            'recentInvoices' => $recentInvoices,
            'recentReturns' => $recentReturns,
        ];
    }
}

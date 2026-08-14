<?php

namespace App\Domain\Sales;

use App\Models\SalesInvoice;
use App\Models\SalesInvoiceReturn;
use App\Models\SalesProposal;
use App\Models\Workspace;
use Carbon\Carbon;

class SalesDashboardService
{
    public function getMetrics(Workspace $workspace): array
    {
        $orgId = $workspace->organization_id;
        $wsId = $workspace->id;

        $invoices = SalesInvoice::where('organization_id', $orgId)->where('workspace_id', $wsId);
        $totalInvoices = (clone $invoices)->count();
        $draftInvoices = (clone $invoices)->where(fn ($q) => $q->where('status', 'draft')->orWhere('status', 0))->count();
        $postedInvoices = (clone $invoices)->where(fn ($q) => $q->whereIn('status', ['posted', 'sent', 1, 2]))->count();
        $paidInvoices = (clone $invoices)->where(fn ($q) => $q->where('status', 'paid')->orWhere('status', 3))->count();

        $totalSalesAmount = (float) (clone $invoices)->where(fn ($q) => $q->whereNotIn('status', ['draft', 0]))->sum('total_amount');
        $outstandingReceivables = (float) (clone $invoices)->where(fn ($q) => $q->whereIn('status', ['posted', 'sent', 'partial', 1, 2]))->sum('total_amount');

        $proposals = SalesProposal::where('organization_id', $orgId)->where('workspace_id', $wsId);
        $totalProposals = (clone $proposals)->count();
        $acceptedProposals = (clone $proposals)->where(fn ($q) => $q->where('status', 'accepted')->orWhere('status', 2))->count();
        $pendingProposals = (clone $proposals)->where(fn ($q) => $q->whereIn('status', ['draft', 'sent', 0, 1]))->count();
        $rejectedProposals = (clone $proposals)->where(fn ($q) => $q->where('status', 'rejected')->orWhere('status', 3))->count();

        $conversionRate = $totalProposals > 0 ? round(($acceptedProposals / $totalProposals) * 100, 1) : 0;

        $returns = SalesInvoiceReturn::where('organization_id', $orgId)->where('workspace_id', $wsId);
        $totalReturns = (clone $returns)->count();
        $returnsAmount = (float) (clone $returns)->sum('total_amount');

        // Monthly sales trend (past 6 months)
        $monthlySales = [];
        for ($i = 5; $i >= 0; $i--) {
            $date = Carbon::now()->subMonths($i);
            $monthName = $date->format('M');
            $year = $date->year;
            $month = $date->month;

            $sales = (float) SalesInvoice::where('organization_id', $orgId)
                ->where('workspace_id', $wsId)
                ->where(fn ($q) => $q->whereNotIn('status', ['draft', 0]))
                ->whereYear('issue_date', $year)
                ->whereMonth('issue_date', $month)
                ->sum('total_amount');

            $monthlySales[] = [
                'month' => $monthName,
                'sales' => $sales,
            ];
        }

        // Recent Invoices
        $recentInvoices = (clone $invoices)->latest('issue_date')->limit(5)->get()->map(fn ($inv) => [
            'id' => $inv->id,
            'invoice_number' => (string) ($inv->invoice_id ?? 'INV-' . $inv->id),
            'customer_name' => 'Customer #' . ($inv->customer_id ?? $inv->id),
            'grand_total' => (float) $inv->total_amount,
            'status' => (string) $inv->status,
            'issue_date' => $inv->issue_date ? $inv->issue_date->toDateString() : now()->toDateString(),
        ]);

        // Recent Proposals
        $recentProposals = (clone $proposals)->latest('issue_date')->limit(5)->get()->map(fn ($prop) => [
            'id' => $prop->id,
            'proposal_number' => (string) ($prop->proposal_id ?? $prop->proposal_number ?? 'PROP-' . $prop->id),
            'customer_name' => 'Customer #' . ($prop->customer_id ?? $prop->id),
            'grand_total' => (float) ($prop->total_amount ?? $prop->grand_total ?? 0),
            'status' => (string) $prop->status,
            'issue_date' => $prop->issue_date ? Carbon::parse($prop->issue_date)->toDateString() : now()->toDateString(),
        ]);

        return [
            'stats' => [
                'total_invoices' => $totalInvoices,
                'draft_invoices' => $draftInvoices,
                'posted_invoices' => $postedInvoices,
                'paid_invoices' => $paidInvoices,
                'total_sales_amount' => $totalSalesAmount,
                'outstanding_receivables' => $outstandingReceivables,
                'total_proposals' => $totalProposals,
                'accepted_proposals' => $acceptedProposals,
                'pending_proposals' => $pendingProposals,
                'rejected_proposals' => $rejectedProposals,
                'conversion_rate' => $conversionRate,
                'total_returns' => $totalReturns,
                'returns_amount' => $returnsAmount,
            ],
            'monthlySales' => $monthlySales,
            'recentInvoices' => $recentInvoices,
            'recentProposals' => $recentProposals,
        ];
    }
}

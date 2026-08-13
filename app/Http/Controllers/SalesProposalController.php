<?php

namespace App\Http\Controllers;

use App\Domain\ProductService\Services\CatalogLookupService;
use App\Models\SalesInvoice;
use App\Models\SalesInvoiceItem;
use App\Models\SalesProposal;
use App\Models\SalesProposalItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class SalesProposalController extends Controller
{
    public function index(Request $request)
    {
        $wsId = session('active_workspace_id');
        $orgId = session('active_organization_id');

        $proposals = SalesProposal::query()
            ->when($wsId, fn ($q) => $q->where('workspace_id', $wsId))
            ->when($orgId, fn ($q) => $q->where('organization_id', $orgId))
            ->when($request->search, fn ($q) => $q->where('proposal_id', 'like', "%{$request->search}%"))
            ->when($request->status !== null, fn ($q) => $q->where('status', $request->status))
            ->latest()
            ->paginate($request->input('per_page', 10))
            ->withQueryString();

        return Inertia::render('SalesProposals/Index', [
            'proposals' => $proposals,
        ]);
    }

    public function create()
    {
        return Inertia::render('SalesProposals/Create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'customer_id' => 'nullable|integer',
            'issue_date' => 'required|date',
            'type' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.item_name' => 'required|string',
            'items.*.quantity' => 'required|numeric|min:1',
            'items.*.price' => 'required|numeric|min:0',
        ]);

        $wsId = session('active_workspace_id');
        $orgId = session('active_organization_id');

        return DB::transaction(function () use ($validated, $wsId, $orgId) {
            $proposalId = strtoupper(substr(uniqid('PROP-'), -10));

            $total = 0;
            foreach ($validated['items'] as $item) {
                $total += $item['quantity'] * $item['price'];
            }

            $proposal = SalesProposal::create([
                'proposal_id' => $proposalId,
                'customer_id' => $validated['customer_id'] ?? null,
                'issue_date' => $validated['issue_date'],
                'type' => $validated['type'] ?? 'proposal',
                'total_amount' => $total,
                'status' => 0, // Draft
                'organization_id' => $orgId,
                'workspace_id' => $wsId,
                'created_by' => Auth::id(),
            ]);

            foreach ($validated['items'] as $item) {
                SalesProposalItem::create([
                    'proposal_id' => $proposal->id,
                    'item_name' => $item['item_name'],
                    'quantity' => $item['quantity'],
                    'price' => $item['price'],
                    'tax' => $item['tax'] ?? 0,
                    'discount' => $item['discount'] ?? 0,
                ]);
            }

            return redirect()->route('sales-proposals.index')->with('success', 'Sales proposal created.');
        });
    }

    public function show(SalesProposal $salesProposal)
    {
        $salesProposal->load(['items']);

        return Inertia::render('SalesProposals/Show', [
            'proposal' => $salesProposal,
        ]);
    }

    public function edit(SalesProposal $salesProposal)
    {
        $salesProposal->load(['items']);

        return Inertia::render('SalesProposals/Edit', [
            'proposal' => $salesProposal,
        ]);
    }

    public function update(Request $request, SalesProposal $salesProposal)
    {
        $validated = $request->validate([
            'issue_date' => 'required|date',
            'type' => 'nullable|string',
        ]);

        $salesProposal->update($validated);

        return redirect()->route('sales-proposals.index')->with('success', 'Sales proposal updated.');
    }

    public function destroy(SalesProposal $salesProposal)
    {
        $salesProposal->items()->delete();
        $salesProposal->delete();

        return redirect()->route('sales-proposals.index')->with('success', 'Sales proposal deleted.');
    }

    public function print(SalesProposal $salesProposal)
    {
        $salesProposal->load(['items']);

        return view('print.sales_proposal', ['proposal' => $salesProposal]);
    }

    public function sent(SalesProposal $salesProposal)
    {
        $salesProposal->update(['status' => 1]); // Sent

        return back()->with('success', 'Proposal marked as sent.');
    }

    public function accept(SalesProposal $salesProposal)
    {
        $salesProposal->update(['status' => 2]); // Accepted

        return back()->with('success', 'Proposal accepted.');
    }

    public function reject(SalesProposal $salesProposal)
    {
        $salesProposal->update(['status' => 3]); // Rejected

        return back()->with('success', 'Proposal rejected.');
    }

    public function convertToInvoice(SalesProposal $salesProposal)
    {
        $salesProposal->load(['items']);

        $invoiceId = strtoupper(substr(uniqid('SI-'), -10));

        $invoice = SalesInvoice::create([
            'invoice_id' => $invoiceId,
            'customer_id' => $salesProposal->customer_id,
            'warehouse_id' => null,
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addDays(30)->toDateString(),
            'total_amount' => $salesProposal->total_amount,
            'status' => 0,
            'organization_id' => $salesProposal->organization_id,
            'workspace_id' => $salesProposal->workspace_id,
            'created_by' => Auth::id(),
        ]);

        foreach ($salesProposal->items as $pItem) {
            SalesInvoiceItem::create([
                'invoice_id' => $invoice->id,
                'item_name' => $pItem->item_name,
                'quantity' => $pItem->quantity,
                'price' => $pItem->price,
                'tax' => $pItem->tax,
                'discount' => $pItem->discount,
            ]);
        }

        $salesProposal->update(['status' => 4]); // Converted

        return redirect()->route('sales-invoices.show', $invoice->id)->with('success', 'Proposal converted to invoice.');
    }

    public function getWarehouseProducts(Request $request, CatalogLookupService $catalog)
    {
        $validated = $request->validate([
            'warehouse_id' => ['required', 'integer'],
        ]);

        return response()->json($catalog->productsForWarehouse(
            (int) $validated['warehouse_id'],
            (int) session('active_organization_id'),
            (int) session('active_workspace_id'),
        ));
    }

    public function getServices(Request $request, CatalogLookupService $catalog)
    {
        return response()->json($catalog->services(
            (int) session('active_organization_id'),
            (int) session('active_workspace_id'),
        ));
    }
}

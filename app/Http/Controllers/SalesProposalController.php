<?php

namespace App\Http\Controllers;

use App\Domain\ProductService\Services\CatalogLookupService;
use App\Models\SalesInvoice;
use App\Models\SalesInvoiceItem;
use App\Models\ProductServiceItem;
use App\Models\SalesProposal;
use App\Models\SalesProposalItem;
use App\Models\Workspace;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class SalesProposalController extends Controller
{
    public function index(Request $request)
    {
        $workspace = $this->workspace($request);
        $wsId = $workspace->id;
        $orgId = $workspace->organization_id;

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

    public function create(Request $request)
    {
        $workspace = $this->workspace($request);

        return Inertia::render('SalesProposals/Create', [
            'products' => ProductServiceItem::forTenant($workspace->organization_id, $workspace->id)->where('is_active', true)->get(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'customer_id' => 'nullable|integer',
            'issue_date' => 'required|date',
            'type' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|integer',
            'items.*.quantity' => 'required|numeric|min:1',
            'items.*.price' => 'required|numeric|min:0',
            'items.*.tax' => 'nullable|numeric|min:0',
            'items.*.discount' => 'nullable|numeric|min:0',
        ]);

        $workspace = $this->workspace($request);
        $wsId = $workspace->id;
        $orgId = $workspace->organization_id;
        $products = ProductServiceItem::forTenant($orgId, $wsId)->whereIn('id', collect($validated['items'])->pluck('product_id')->unique())->get()->keyBy('id');
        abort_unless($products->count() === collect($validated['items'])->pluck('product_id')->unique()->count(), 422, 'A proposal item is outside the active tenant catalog.');

        return DB::transaction(function () use ($validated, $wsId, $orgId, $products) {
            $proposalId = strtoupper(substr(uniqid('PROP-'), -10));

            $total = 0;
            foreach ($validated['items'] as $item) {
                $total += ($item['quantity'] * $item['price']) + ($item['tax'] ?? 0) - ($item['discount'] ?? 0);
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
                    'product_id' => $item['product_id'],
                    'item_name' => $products[$item['product_id']]->name,
                    'quantity' => $item['quantity'],
                    'price' => $item['price'],
                    'tax' => $item['tax'] ?? 0,
                    'discount' => $item['discount'] ?? 0,
                ]);
            }

            return redirect()->route('sales-proposals.index')->with('success', 'Sales proposal created.');
        });
    }

    public function show(Request $request, SalesProposal $salesProposal)
    {
        $this->assertProposal($salesProposal, $this->workspace($request));
        $salesProposal->load(['items']);

        return Inertia::render('SalesProposals/Show', [
            'proposal' => $salesProposal,
        ]);
    }

    public function edit(Request $request, SalesProposal $salesProposal)
    {
        $workspace = $this->workspace($request);
        $this->assertProposal($salesProposal, $workspace);
        abort_unless((int) $salesProposal->status === 0, 422, 'Only draft proposals can be edited.');
        $salesProposal->load(['items']);

        return Inertia::render('SalesProposals/Edit', [
            'proposal' => $salesProposal,
            'products' => ProductServiceItem::forTenant($workspace->organization_id, $workspace->id)->where('is_active', true)->get(),
        ]);
    }

    public function update(Request $request, SalesProposal $salesProposal)
    {
        $this->assertProposal($salesProposal, $this->workspace($request));
        abort_unless((int) $salesProposal->status === 0, 422, 'Only draft proposals can be edited.');
        $validated = $request->validate([
            'issue_date' => 'required|date',
            'type' => 'nullable|string',
        ]);

        $salesProposal->update($validated);

        return redirect()->route('sales-proposals.index')->with('success', 'Sales proposal updated.');
    }

    public function destroy(Request $request, SalesProposal $salesProposal)
    {
        $this->assertProposal($salesProposal, $this->workspace($request));
        abort_unless((int) $salesProposal->status === 0, 422, 'Only draft proposals can be deleted.');
        $salesProposal->items()->delete();
        $salesProposal->delete();

        return redirect()->route('sales-proposals.index')->with('success', 'Sales proposal deleted.');
    }

    public function print(Request $request, SalesProposal $salesProposal)
    {
        $this->assertProposal($salesProposal, $this->workspace($request));
        $salesProposal->load(['items']);

        return view('print.sales_proposal', ['proposal' => $salesProposal]);
    }

    public function sent(Request $request, SalesProposal $salesProposal)
    {
        $this->assertProposal($salesProposal, $this->workspace($request));
        abort_unless((int) $salesProposal->status === 0, 422, 'Only draft proposals can be sent.');
        $salesProposal->update(['status' => 1]); // Sent

        return back()->with('success', 'Proposal marked as sent.');
    }

    public function accept(Request $request, SalesProposal $salesProposal)
    {
        $this->assertProposal($salesProposal, $this->workspace($request));
        abort_unless((int) $salesProposal->status === 1, 422, 'Only sent proposals can be accepted.');
        $salesProposal->update(['status' => 2]); // Accepted

        return back()->with('success', 'Proposal accepted.');
    }

    public function reject(Request $request, SalesProposal $salesProposal)
    {
        $this->assertProposal($salesProposal, $this->workspace($request));
        abort_unless((int) $salesProposal->status === 1, 422, 'Only sent proposals can be rejected.');
        $salesProposal->update(['status' => 3]); // Rejected

        return back()->with('success', 'Proposal rejected.');
    }

    public function convertToInvoice(Request $request, SalesProposal $salesProposal)
    {
        $this->assertProposal($salesProposal, $this->workspace($request));
        abort_unless((int) $salesProposal->status === 2, 422, 'Only accepted proposals can be converted.');
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
                'product_id' => $pItem->product_id,
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
        $workspace = $this->workspace($request);
        $validated = $request->validate([
            'warehouse_id' => ['required', 'integer'],
        ]);

        return response()->json($catalog->productsForWarehouse(
            (int) $validated['warehouse_id'],
            (int) $workspace->organization_id,
            (int) $workspace->id,
        ));
    }

    public function getServices(Request $request, CatalogLookupService $catalog)
    {
        $workspace = $this->workspace($request);

        return response()->json($catalog->services(
            (int) $workspace->organization_id,
            (int) $workspace->id,
        ));
    }

    private function workspace(Request $request): Workspace
    {
        $workspace = Workspace::query()->with('organization')->find($request->session()->get('active_workspace_id'));
        abort_unless($workspace && $request->user()->canInWorkspace('sales.manage', $workspace), 403);

        return $workspace;
    }

    private function assertProposal(SalesProposal $proposal, Workspace $workspace): void
    {
        abort_unless((int) $proposal->organization_id === (int) $workspace->organization_id && (int) $proposal->workspace_id === (int) $workspace->id, 404);
    }
}

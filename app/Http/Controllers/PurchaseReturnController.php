<?php

namespace App\Http\Controllers;

use App\Domain\Inventory\ReturnPostingService;
use App\Domain\Shared\DocumentNumberService;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseReturn;
use App\Models\PurchaseReturnItem;
use App\Models\Workspace;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class PurchaseReturnController extends Controller
{
    public function index(Request $request)
    {
        $workspace = $this->workspace($request);

        return Inertia::render('PurchaseReturns/Index', ['returns' => PurchaseReturn::where('organization_id', $workspace->organization_id)->where('workspace_id', $workspace->id)->latest()->paginate(20)]);
    }

    public function create(Request $request)
    {
        $workspace = $this->workspace($request);

        return Inertia::render('PurchaseReturns/Create', [
            'invoices' => PurchaseInvoice::with('items')
                ->where('organization_id', $workspace->organization_id)
                ->where('workspace_id', $workspace->id)
                ->where(fn ($q) => $q->whereIn('status', [1, 2, 3, 'posted', 'received', 'paid']))
                ->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'purchase_invoice_id' => ['required', 'integer'],
            'date' => ['required', 'date'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
            'items.*.price' => ['required', 'numeric', 'min:0'],
        ]);
        $workspace = $this->workspace($request);
        $invoice = PurchaseInvoice::with('items')
            ->where('organization_id', $workspace->organization_id)
            ->where('workspace_id', $workspace->id)
            ->where(fn ($q) => $q->whereIn('status', [1, 2, 3, 'posted', 'received', 'paid']))
            ->findOrFail($data['purchase_invoice_id']);
        $lines = $invoice->items->groupBy('product_id');
        foreach ($data['items'] as $item) {
            $originalQty = (float) $lines->get($item['product_id'], collect())->sum('quantity');
            $previouslyReturned = (float) PurchaseReturnItem::whereHas('purchaseReturn', fn ($q) => $q->where('purchase_invoice_id', $invoice->id)->whereIn('status', [0, 1, 2]))->where('product_id', $item['product_id'])->sum('quantity');
            abort_unless(((float) $item['quantity'] + $previouslyReturned) <= $originalQty, 422, 'Cumulative return quantity exceeds the original purchase invoice quantity.');
        }

        return DB::transaction(function () use ($data, $workspace, $invoice, $request, $lines) {
            $return = PurchaseReturn::create([
                'return_id' => app(DocumentNumberService::class)->next($workspace->id, 'purchase_return', 'PR'),
                'vendor_id' => $invoice->vendor_id,
                'purchase_invoice_id' => $invoice->id, 'date' => $data['date'],
                'total_amount' => collect($data['items'])->sum(fn ($item) => $item['quantity'] * $item['price']),
                'status' => 0, 'organization_id' => $workspace->organization_id, 'workspace_id' => $workspace->id, 'created_by' => $request->user()->id,
            ]);
            foreach ($data['items'] as $item) {
                PurchaseReturnItem::create($item + ['purchase_return_id' => $return->id, 'item_name' => $lines[$item['product_id']]->first()->item_name]);
            }

            return to_route('purchase-returns.index')->with('success', 'Purchase return recorded.');
        });
    }

    public function show(Request $request, PurchaseReturn $return)
    {
        $this->assertReturn($return, $this->workspace($request));

        return Inertia::render('PurchaseReturns/Show', ['return' => $return->load('items')]);
    }

    public function destroy(Request $request, PurchaseReturn $return)
    {
        $this->assertReturn($return, $this->workspace($request));
        abort_unless((int) $return->status === 0, 422, 'Only pending returns can be deleted.');
        $return->items()->delete();
        $return->delete();

        return to_route('purchase-returns.index')->with('success', 'Purchase return deleted.');
    }

    public function approve(Request $request, PurchaseReturn $return)
    {
        $this->assertReturn($return, $this->workspace($request));
        abort_unless((int) $return->status === 0, 422, 'Only pending returns can be approved.');
        $return->update(['status' => 1]);

        return back()->with('success', 'Purchase return approved.');
    }

    public function complete(Request $request, PurchaseReturn $return, ReturnPostingService $posting)
    {
        $this->assertReturn($return, $this->workspace($request));
        $posting->completePurchase($return, $request->user());

        return back()->with('success', 'Purchase return completed.');
    }

    private function workspace(Request $request): Workspace
    {
        $workspace = Workspace::with('organization')->find($request->session()->get('active_workspace_id'));
        abort_unless($workspace && $request->user()->canInWorkspace('procurement.manage', $workspace), 403);

        return $workspace;
    }

    private function assertReturn(PurchaseReturn $return, Workspace $workspace): void
    {
        abort_unless((int) $return->organization_id === (int) $workspace->organization_id && (int) $return->workspace_id === (int) $workspace->id, 404);
    }
}

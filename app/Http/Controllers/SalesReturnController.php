<?php

namespace App\Http\Controllers;

use App\Domain\Inventory\ReturnPostingService;
use App\Models\SalesInvoice;
use App\Models\SalesInvoiceReturn;
use App\Models\SalesInvoiceReturnItem;
use App\Models\Workspace;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class SalesReturnController extends Controller
{
    public function index(Request $request)
    {
        $workspace = $this->workspace($request);

        return Inertia::render('SalesReturns/Index', ['returns' => SalesInvoiceReturn::where('organization_id', $workspace->organization_id)->where('workspace_id', $workspace->id)->latest()->paginate(20)]);
    }

    public function create(Request $request)
    {
        $workspace = $this->workspace($request);

        return Inertia::render('SalesReturns/Create', [
            'invoices' => SalesInvoice::with('items')
                ->where('organization_id', $workspace->organization_id)
                ->where('workspace_id', $workspace->id)
                ->where(fn ($q) => $q->whereIn('status', [1, 2, 3, 'posted', 'sent', 'paid']))
                ->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'sales_invoice_id' => ['required', 'integer'],
            'date' => ['required', 'date'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
            'items.*.price' => ['required', 'numeric', 'min:0'],
        ]);
        $workspace = $this->workspace($request);
        $invoice = SalesInvoice::with('items')
            ->where('organization_id', $workspace->organization_id)
            ->where('workspace_id', $workspace->id)
            ->where(fn ($q) => $q->whereIn('status', [1, 2, 3, 'posted', 'sent', 'paid']))
            ->findOrFail($data['sales_invoice_id']);
        $lines = $invoice->items->groupBy('product_id');
        foreach ($data['items'] as $item) {
            abort_unless((float) $item['quantity'] <= (float) $lines->get($item['product_id'], collect())->sum('quantity'), 422, 'Return quantity exceeds the sales invoice.');
        }

        return DB::transaction(function () use ($data, $workspace, $invoice, $request, $lines) {
            $return = SalesInvoiceReturn::create([
                'return_id' => strtoupper(substr(uniqid('SR-'), -10)), 'customer_id' => $invoice->customer_id,
                'sales_invoice_id' => $invoice->id, 'date' => $data['date'],
                'total_amount' => collect($data['items'])->sum(fn ($item) => $item['quantity'] * $item['price']),
                'status' => 0, 'organization_id' => $workspace->organization_id, 'workspace_id' => $workspace->id, 'created_by' => $request->user()->id,
            ]);
            foreach ($data['items'] as $item) {
                SalesInvoiceReturnItem::create($item + ['sales_invoice_return_id' => $return->id, 'item_name' => $lines[$item['product_id']]->first()->item_name]);
            }

            return to_route('sales-returns.index')->with('success', 'Sales return recorded.');
        });
    }

    public function show(Request $request, SalesInvoiceReturn $salesReturn)
    {
        $this->assertReturn($salesReturn, $this->workspace($request));

        return Inertia::render('SalesReturns/Show', ['return' => $salesReturn->load('items')]);
    }

    public function destroy(Request $request, SalesInvoiceReturn $salesReturn)
    {
        $this->assertReturn($salesReturn, $this->workspace($request));
        abort_unless((int) $salesReturn->status === 0, 422, 'Only pending returns can be deleted.');
        $salesReturn->items()->delete();
        $salesReturn->delete();

        return to_route('sales-returns.index')->with('success', 'Sales return deleted.');
    }

    public function approve(Request $request, SalesInvoiceReturn $salesReturn)
    {
        $this->assertReturn($salesReturn, $this->workspace($request));
        abort_unless((int) $salesReturn->status === 0, 422, 'Only pending returns can be approved.');
        $salesReturn->update(['status' => 1]);

        return back()->with('success', 'Sales return approved.');
    }

    public function complete(Request $request, SalesInvoiceReturn $salesReturn, ReturnPostingService $posting)
    {
        $this->assertReturn($salesReturn, $this->workspace($request));
        $posting->completeSale($salesReturn, $request->user());

        return back()->with('success', 'Sales return completed.');
    }

    private function workspace(Request $request): Workspace
    {
        $workspace = Workspace::with('organization')->find($request->session()->get('active_workspace_id'));
        abort_unless($workspace && $request->user()->canInWorkspace('sales.manage', $workspace), 403);

        return $workspace;
    }

    private function assertReturn(SalesInvoiceReturn $return, Workspace $workspace): void
    {
        abort_unless((int) $return->organization_id === (int) $workspace->organization_id && (int) $return->workspace_id === (int) $workspace->id, 404);
    }
}

<?php

namespace App\Http\Controllers\POS;

use App\Domain\POS\PosReturnService;
use App\Http\Controllers\Controller;
use App\Models\POS\PosReturn;
use App\Models\POS\PosSale;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class PosReturnController extends Controller
{
    public function __construct(private readonly PosReturnService $returnService) {}

    public function index(Request $request): Response
    {
        $query = PosReturn::with(['sale.counter', 'journalEntry'])
            ->where('organization_id', $request->session()->get('active_organization_id'))
            ->where('workspace_id', $request->session()->get('active_workspace_id'));

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }
        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('return_number', 'like', "%{$search}%")
                    ->orWhere('reason', 'like', "%{$search}%");
            });
        }

        return Inertia::render('POS/Returns/Index', [
            'returns' => $query->latest()->paginate(15)->withQueryString(),
        ]);
    }

    public function create(Request $request): Response
    {
        $sale = null;
        if ($request->filled('pos_sale_id')) {
            $sale = PosSale::with('items')
                ->where('organization_id', $request->session()->get('active_organization_id'))
                ->where('workspace_id', $request->session()->get('active_workspace_id'))
                ->where('id', $request->input('pos_sale_id'))
                ->firstOrFail();
        }

        return Inertia::render('POS/Returns/Create', ['sale' => $sale]);
    }

    public function store(Request $request): JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'pos_sale_id' => ['required', 'integer'],
            'reason' => ['required', 'string', 'max:500'],
            'refund_method' => ['nullable', Rule::in(['cash', 'card', 'bank'])],
            'refund_reference' => ['nullable', 'string', 'max:255'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.pos_sale_item_id' => ['required', 'integer'],
            'items.*.product_id' => ['required', 'integer'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.0001'],
        ]);

        $sale = PosSale::where('organization_id', $request->session()->get('active_organization_id'))
            ->where('workspace_id', $request->session()->get('active_workspace_id'))
            ->where('id', $validated['pos_sale_id'])
            ->firstOrFail();

        $return = $this->returnService->createReturn(
            $sale,
            $request->user(),
            $validated['items'],
            $validated['reason'],
            $validated['refund_method'] ?? $sale->payment_method,
            $validated['refund_reference'] ?? null,
        );

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'return' => $return->load('items')]);
        }

        return redirect()->route('pos.returns.show', $return->id)->with('success', 'Return request created.');
    }

    public function show(Request $request, int|string $id): Response
    {
        $return = $this->returnForTenant($request, $id, ['sale.items', 'items.saleItem', 'journalEntry']);

        return Inertia::render('POS/Returns/Show', ['return' => $return]);
    }

    public function approve(Request $request, int|string $id): JsonResponse|RedirectResponse
    {
        $approved = $this->returnService->approve($this->returnForTenant($request, $id), $request->user());

        return $request->wantsJson()
            ? response()->json(['success' => true, 'return' => $approved])
            : redirect()->back()->with('success', 'Return approved.');
    }

    public function complete(Request $request, int|string $id): JsonResponse|RedirectResponse
    {
        $completed = $this->returnService->complete(
            $this->returnForTenant($request, $id, ['sale', 'items.saleItem']),
            $request->user(),
        );

        return $request->wantsJson()
            ? response()->json(['success' => true, 'return' => $completed->load('journalEntry')])
            : redirect()->back()->with('success', 'Return completed; stock and accounting were reversed atomically.');
    }

    public function destroy(Request $request, int|string $id): RedirectResponse
    {
        $return = $this->returnForTenant($request, $id);
        if ($return->status === 'completed') {
            return redirect()->back()->with('error', 'Cannot delete a completed return.');
        }

        $return->items()->delete();
        $return->delete();

        return redirect()->route('pos.returns.index')->with('success', 'Return deleted.');
    }

    private function returnForTenant(Request $request, int|string $id, array $relations = []): PosReturn
    {
        return PosReturn::with($relations)
            ->where('organization_id', $request->session()->get('active_organization_id'))
            ->where('workspace_id', $request->session()->get('active_workspace_id'))
            ->where('id', $id)
            ->firstOrFail();
    }
}

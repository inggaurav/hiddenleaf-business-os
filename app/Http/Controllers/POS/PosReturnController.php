<?php

namespace App\Http\Controllers\POS;

use App\Domain\POS\PosReturnService;
use App\Http\Controllers\Controller;
use App\Models\POS\PosReturn;
use App\Models\POS\PosSale;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PosReturnController extends Controller
{
    public function __construct(
        private readonly PosReturnService $returnService,
    ) {}

    public function index(Request $request): Response
    {
        $wsId = $request->session()->get('active_workspace_id');
        $orgId = $request->session()->get('active_organization_id');

        $query = PosReturn::with(['sale.counter'])
            ->where('organization_id', $orgId)
            ->where('workspace_id', $wsId);

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

        $returns = $query->latest()->paginate(15)->withQueryString();

        return Inertia::render('POS/Returns/Index', [
            'returns' => $returns,
        ]);
    }

    public function create(Request $request): Response
    {
        $wsId = $request->session()->get('active_workspace_id');
        $orgId = $request->session()->get('active_organization_id');

        $saleId = $request->input('pos_sale_id');
        $sale = null;
        if ($saleId) {
            $sale = PosSale::with('items')
                ->where('organization_id', $orgId)
                ->where('workspace_id', $wsId)
                ->where('id', $saleId)
                ->first();
        }

        return Inertia::render('POS/Returns/Create', [
            'sale' => $sale,
        ]);
    }

    public function store(Request $request): JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'pos_sale_id' => 'required|integer',
            'reason' => 'required|string|max:500',
            'refund_method' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.pos_sale_item_id' => 'required|integer',
            'items.*.product_id' => 'required|integer',
            'items.*.quantity' => 'required|numeric|min:0.0001',
        ]);

        $wsId = (int) $request->session()->get('active_workspace_id');
        $orgId = (int) $request->session()->get('active_organization_id');
        $user = $request->user();

        $sale = PosSale::where('organization_id', $orgId)
            ->where('workspace_id', $wsId)
            ->where('id', $validated['pos_sale_id'])
            ->firstOrFail();

        $return = $this->returnService->createReturn(
            $sale,
            $user,
            $validated['items'],
            $validated['reason'],
            $validated['refund_method'] ?? 'cash'
        );

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'return' => $return->load('items')]);
        }

        return redirect()->route('pos.returns.show', $return->id)->with('success', 'Return request created.');
    }

    public function show(Request $request, int|string $id): Response
    {
        $wsId = $request->session()->get('active_workspace_id');
        $orgId = $request->session()->get('active_organization_id');

        $return = PosReturn::with(['sale.items', 'items.saleItem'])
            ->where('organization_id', $orgId)
            ->where('workspace_id', $wsId)
            ->where('id', $id)
            ->firstOrFail();

        return Inertia::render('POS/Returns/Show', [
            'return' => $return,
        ]);
    }

    public function approve(Request $request, int|string $id): JsonResponse|RedirectResponse
    {
        $wsId = (int) $request->session()->get('active_workspace_id');
        $orgId = (int) $request->session()->get('active_organization_id');
        $user = $request->user();

        $return = PosReturn::where('organization_id', $orgId)
            ->where('workspace_id', $wsId)
            ->where('id', $id)
            ->firstOrFail();

        $approved = $this->returnService->approve($return, $user);

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'return' => $approved]);
        }

        return redirect()->back()->with('success', 'Return approved.');
    }

    public function complete(Request $request, int|string $id): JsonResponse|RedirectResponse
    {
        $wsId = (int) $request->session()->get('active_workspace_id');
        $orgId = (int) $request->session()->get('active_organization_id');
        $user = $request->user();

        $return = PosReturn::with(['sale', 'items.saleItem'])
            ->where('organization_id', $orgId)
            ->where('workspace_id', $wsId)
            ->where('id', $id)
            ->firstOrFail();

        $completed = $this->returnService->complete($return, $user);

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'return' => $completed]);
        }

        return redirect()->back()->with('success', 'Return completed and stock restored.');
    }

    public function destroy(Request $request, int|string $id): RedirectResponse
    {
        $wsId = (int) $request->session()->get('active_workspace_id');
        $orgId = (int) $request->session()->get('active_organization_id');

        $return = PosReturn::where('organization_id', $orgId)
            ->where('workspace_id', $wsId)
            ->where('id', $id)
            ->firstOrFail();

        if ($return->status === 'completed') {
            return redirect()->back()->with('error', 'Cannot delete a completed return.');
        }

        $return->items()->delete();
        $return->delete();

        return redirect()->route('pos.returns.index')->with('success', 'Return deleted.');
    }
}

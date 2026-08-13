<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\PurchaseInvoice;
use App\Models\SalesInvoice;
use App\Models\SalesProposal;
use App\Models\Warehouse;
use Illuminate\Http\Request;

class SalesProcurementApiController extends Controller
{
    public function warehouses(Request $request)
    {
        $wsId = $request->header('X-Workspace-ID') ?: session('active_workspace_id');
        $warehouses = Warehouse::when($wsId, fn ($q) => $q->where('workspace_id', $wsId))->get();

        return response()->json([
            'success' => true,
            'warehouses' => $warehouses,
        ]);
    }

    public function purchaseInvoices(Request $request)
    {
        $wsId = $request->header('X-Workspace-ID') ?: session('active_workspace_id');
        $invoices = PurchaseInvoice::with('items')
            ->when($wsId, fn ($q) => $q->where('workspace_id', $wsId))
            ->latest()
            ->paginate($request->input('per_page', 15));

        return response()->json([
            'success' => true,
            'data' => $invoices,
        ]);
    }

    public function salesInvoices(Request $request)
    {
        $wsId = $request->header('X-Workspace-ID') ?: session('active_workspace_id');
        $invoices = SalesInvoice::with('items')
            ->when($wsId, fn ($q) => $q->where('workspace_id', $wsId))
            ->latest()
            ->paginate($request->input('per_page', 15));

        return response()->json([
            'success' => true,
            'data' => $invoices,
        ]);
    }

    public function salesProposals(Request $request)
    {
        $wsId = $request->header('X-Workspace-ID') ?: session('active_workspace_id');
        $proposals = SalesProposal::with('items')
            ->when($wsId, fn ($q) => $q->where('workspace_id', $wsId))
            ->latest()
            ->paginate($request->input('per_page', 15));

        return response()->json([
            'success' => true,
            'data' => $proposals,
        ]);
    }
}

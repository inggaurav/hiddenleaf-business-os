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
        $wsId = $request->attributes->get('workspace')->id;
        $warehouses = Warehouse::where('workspace_id', $wsId)->orderBy('name')->paginate($this->perPage($request));

        return response()->json([
            'success' => true,
            'warehouses' => $warehouses,
        ]);
    }

    public function purchaseInvoices(Request $request)
    {
        $wsId = $request->attributes->get('workspace')->id;
        $invoices = PurchaseInvoice::with('items')
            ->when($wsId, fn ($q) => $q->where('workspace_id', $wsId))
            ->latest()
            ->paginate($this->perPage($request));

        return response()->json([
            'success' => true,
            'data' => $invoices,
        ]);
    }

    public function salesInvoices(Request $request)
    {
        $wsId = $request->attributes->get('workspace')->id;
        $invoices = SalesInvoice::with('items')
            ->when($wsId, fn ($q) => $q->where('workspace_id', $wsId))
            ->latest()
            ->paginate($this->perPage($request));

        return response()->json([
            'success' => true,
            'data' => $invoices,
        ]);
    }

    public function salesProposals(Request $request)
    {
        $wsId = $request->attributes->get('workspace')->id;
        $proposals = SalesProposal::with('items')
            ->when($wsId, fn ($q) => $q->where('workspace_id', $wsId))
            ->latest()
            ->paginate($this->perPage($request));

        return response()->json([
            'success' => true,
            'data' => $proposals,
        ]);
    }

    private function perPage(Request $request): int
    {
        return max(1, min((int) $request->input('per_page', 20), 100));
    }
}

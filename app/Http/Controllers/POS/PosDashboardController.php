<?php

namespace App\Http\Controllers\POS;

use App\Domain\POS\PosDashboardService;
use App\Http\Controllers\Controller;
use App\Models\Workspace;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PosDashboardController extends Controller
{
    public function index(Request $request, PosDashboardService $dashboardService): Response
    {
        $wsId = $request->session()->get('active_workspace_id');
        $workspace = Workspace::findOrFail($wsId);

        $data = $dashboardService->getMetrics($workspace);

        return Inertia::render('POS/Dashboard', array_merge($data, [
            'metrics' => $data['stats'],
        ]));
    }
}

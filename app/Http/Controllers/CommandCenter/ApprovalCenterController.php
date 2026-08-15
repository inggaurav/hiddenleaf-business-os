<?php

namespace App\Http\Controllers\CommandCenter;

use App\Domain\MrFox\Approvals\ActionApprovalService;
use App\Domain\MrFox\Context\BusinessContextService;
use App\Http\Controllers\Controller;
use App\Models\MrFoxActionProposal;
use App\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ApprovalCenterController extends Controller
{
    public function __construct(
        private ActionApprovalService $approvalService,
        private BusinessContextService $contextService
    ) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        $wsId = (int) $request->session()->get('active_workspace_id');
        $workspace = Workspace::findOrFail($wsId);

        $proposals = MrFoxActionProposal::where('workspace_id', $workspace->id)
            ->with(['user', 'approver'])
            ->latest('requested_at')
            ->paginate(20);

        return Inertia::render('CommandCenter/Approvals', [
            'proposals' => $proposals,
        ]);
    }

    public function approve(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $wsId = (int) $request->session()->get('active_workspace_id');
        $workspace = Workspace::findOrFail($wsId);

        $context = $this->contextService->createToolContext($user, $workspace);
        $result = $this->approvalService->approveAndExecute($id, $user, $context);

        return response()->json([
            'success' => $result->success,
            'summary' => $result->summary,
            'error' => $result->error,
            'data' => $result->data,
        ]);
    }

    public function reject(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $wsId = (int) $request->session()->get('active_workspace_id');

        $rejected = $this->approvalService->reject($id, $user, $wsId);

        return response()->json(['success' => $rejected]);
    }
}

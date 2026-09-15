<?php

namespace HiddenLeaf\Hrm\Http\Controllers\HRM;

use App\Models\HrLeaveRequest;
use App\Models\HrLeaveType;
use HiddenLeaf\Hrm\Models\HrEmployee;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class HrmLeaveController extends HrmBaseController
{
    public function index(Request $request): Response
    {
        $workspace = $this->workspace($request, 'hrm.view');

        $query = HrLeaveRequest::where('workspace_id', $workspace->id)->with(['type', 'employee']);
        if (! $this->isWorkspaceManager($request, $workspace)) {
            $emp = HrEmployee::forWorkspace($workspace->organization_id, $workspace->id)->where('user_id', $request->user()->id)->first();
            $query->where('employee_id', $emp?->id ?? 0);
        }

        return Inertia::render('HRM/Leave/Index', [
            'leaves' => $query->latest()->paginate(25),
            'leaveTypes' => HrLeaveType::forWorkspace($workspace->organization_id, $workspace->id)->get(),
            'employees' => HrEmployee::forWorkspace($workspace->organization_id, $workspace->id)->get(['id', 'name']),
            'isManager' => $this->isWorkspaceManager($request, $workspace),
        ]);
    }

    public function storeType(Request $request): RedirectResponse
    {
        $workspace = $this->workspace($request, 'hrm.manage');
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'days' => 'required|integer|min:1',
        ]);

        HrLeaveType::create([
            'organization_id' => $workspace->organization_id,
            'workspace_id' => $workspace->id,
            'name' => $validated['name'],
            'days' => $validated['days'],
        ]);

        return back()->with('success', 'Leave type created.');
    }

    public function request(Request $request): RedirectResponse
    {
        $workspace = $this->workspace($request, 'hrm.view');
        $validated = $request->validate([
            'leave_type_id' => 'required|integer',
            'starts_on' => 'required|date',
            'ends_on' => 'required|date|after_or_equal:starts_on',
            'reason' => 'nullable|string',
            'employee_id' => 'nullable|integer',
        ]);

        $employeeId = $validated['employee_id'] ?? null;
        if (! $employeeId || ! $this->isWorkspaceManager($request, $workspace)) {
            $emp = HrEmployee::forWorkspace($workspace->organization_id, $workspace->id)
                ->where('user_id', $request->user()->id)
                ->firstOrFail();
            $employeeId = $emp->id;
        }

        HrLeaveRequest::create([
            'organization_id' => $workspace->organization_id,
            'workspace_id' => $workspace->id,
            'employee_id' => $employeeId,
            'leave_type_id' => $validated['leave_type_id'],
            'starts_on' => $validated['starts_on'],
            'ends_on' => $validated['ends_on'],
            'reason' => $validated['reason'] ?? null,
            'status' => 'pending',
        ]);

        return back()->with('success', 'Leave requested.');
    }

    public function review(Request $request, $leave): RedirectResponse
    {
        $workspace = $this->workspace($request, 'hrm.manage');

        $validated = $request->validate([
            'status' => 'required|in:approved,rejected',
        ]);

        $leaveReq = HrLeaveRequest::where('workspace_id', $workspace->id)->findOrFail($leave);
        $leaveReq->update(['status' => $validated['status']]);

        return back()->with('success', "Leave {$validated['status']}.");
    }

    public function balances(Request $request): Response
    {
        $workspace = $this->workspace($request, 'hrm.view');
        $employees = HrEmployee::forWorkspace($workspace->organization_id, $workspace->id)->get();
        $types = HrLeaveType::forWorkspace($workspace->organization_id, $workspace->id)->get();

        return Inertia::render('HRM/LeaveBalances', [
            'employees' => $employees,
            'leaveTypes' => $types,
        ]);
    }
}

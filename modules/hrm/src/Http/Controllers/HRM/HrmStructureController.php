<?php

namespace HiddenLeaf\Hrm\Http\Controllers\HRM;

use App\Models\HrBranch;
use App\Models\HrDepartment;
use App\Models\HrDesignation;
use App\Models\HrHoliday;
use App\Models\HrShift;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class HrmStructureController extends HrmBaseController
{
    public function storeBranch(Request $request): RedirectResponse
    {
        $workspace = $this->workspace($request, 'hrm.manage');
        $validated = $request->validate(['name' => 'required|string|max:255']);

        HrBranch::create([
            'organization_id' => $workspace->organization_id,
            'workspace_id' => $workspace->id,
            'name' => $validated['name'],
        ]);

        return back()->with('success', 'Branch created.');
    }

    public function storeDepartment(Request $request): RedirectResponse
    {
        $workspace = $this->workspace($request, 'hrm.manage');
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'branch_id' => 'nullable|integer',
        ]);

        HrDepartment::create([
            'organization_id' => $workspace->organization_id,
            'workspace_id' => $workspace->id,
            'name' => $validated['name'],
            'branch_id' => $validated['branch_id'] ?? null,
        ]);

        return back()->with('success', 'Department created.');
    }

    public function storeDesignation(Request $request): RedirectResponse
    {
        $workspace = $this->workspace($request, 'hrm.manage');
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'department_id' => 'nullable|integer',
        ]);

        HrDesignation::create([
            'organization_id' => $workspace->organization_id,
            'workspace_id' => $workspace->id,
            'name' => $validated['name'],
            'department_id' => $validated['department_id'] ?? null,
        ]);

        return back()->with('success', 'Designation created.');
    }

    public function storeShift(Request $request): RedirectResponse
    {
        $workspace = $this->workspace($request, 'hrm.manage');
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'start_time' => 'required',
            'end_time' => 'required',
        ]);

        HrShift::create([
            'organization_id' => $workspace->organization_id,
            'workspace_id' => $workspace->id,
            'name' => $validated['name'],
            'start_time' => $validated['start_time'],
            'end_time' => $validated['end_time'],
        ]);

        return back()->with('success', 'Shift created.');
    }

    public function storeHoliday(Request $request): RedirectResponse
    {
        $workspace = $this->workspace($request, 'hrm.manage');
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'date' => 'required|date',
        ]);

        HrHoliday::create([
            'organization_id' => $workspace->organization_id,
            'workspace_id' => $workspace->id,
            'name' => $validated['name'],
            'date' => $validated['date'],
        ]);

        return back()->with('success', 'Holiday created.');
    }
}

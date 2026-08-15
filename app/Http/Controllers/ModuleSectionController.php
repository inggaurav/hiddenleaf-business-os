<?php

namespace App\Http\Controllers;

use App\Models\CrmDeal;
use App\Models\CrmLead;
use App\Models\CrmPipeline;
use App\Models\CrmWebform;
use App\Models\HrAttendance;
use App\Models\HrEmployee;
use App\Models\HrLeaveRequest;
use App\Models\HrLeaveType;
use App\Models\HrPayslip;
use App\Models\HrSalaryComponent;
use App\Models\TasklyProject;
use App\Models\TasklyTask;
use App\Models\TasklyTimesheet;
use App\Models\Workspace;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class ModuleSectionController extends Controller
{
    public function crm(Request $request, string $section)
    {
        $workspace = $this->workspace($request, 'crm.view');
        $org = $workspace->organization_id;
        $ws = $workspace->id;

        [$title, $description, $columns, $records] = match ($section) {
            'leads' => ['CRM Leads', 'Lead qualification, assignment and pipeline status.', ['id', 'name', 'company', 'email', 'phone', 'estimated_value', 'status'], CrmLead::forWorkspace($org, $ws)->with(['pipeline:id,name', 'stage:id,name'])->latest()->paginate(30)],
            'deals' => ['CRM Deals', 'Open, won and lost opportunities with pipeline values.', ['id', 'name', 'value', 'status', 'expected_close_on', 'closed_at'], CrmDeal::forWorkspace($org, $ws)->with(['pipeline:id,name', 'stage:id,name'])->latest()->paginate(30)],
            'pipelines' => ['CRM Pipelines', 'Pipelines and their ordered deal stages.', ['id', 'name', 'is_default', 'created_at'], CrmPipeline::where('organization_id', $org)->where('workspace_id', $ws)->with('stages')->latest()->paginate(30)],
            'webforms' => ['CRM Web Forms', 'Public lead capture forms connected to CRM pipelines.', ['id', 'name', 'token', 'pipeline_id', 'stage_id', 'is_active', 'created_at'], CrmWebform::where('organization_id', $org)->where('workspace_id', $ws)->latest()->paginate(30)],
            'activities' => ['CRM Activities', 'Calls, meetings, emails and follow-up tasks across leads and deals.', ['id', 'type', 'title', 'due_at', 'assigned_to', 'subject_type', 'subject_id', 'created_at'], $this->paginateQuery(DB::table('crm_activities')->where('organization_id', $org)->where('workspace_id', $ws)->latest())],
            'notes' => ['CRM Notes', 'Workspace-scoped notes attached to CRM records.', ['id', 'body', 'subject_type', 'subject_id', 'created_by', 'created_at'], $this->paginateQuery(DB::table('crm_notes')->where('organization_id', $org)->where('workspace_id', $ws)->latest())],
            default => abort(404),
        };

        $pipelines = CrmPipeline::where('organization_id', $org)->where('workspace_id', $ws)->with('stages:id,pipeline_id,name,position,is_closed,outcome')->orderBy('name')->get(['id', 'name', 'is_default']);

        return $this->render($request, 'CRM', $section, $title, $description, $columns, $records, '/crm/dashboard', 'crm.manage', [
            'pipelines' => $pipelines,
            'leads' => CrmLead::forWorkspace($org, $ws)->latest()->limit(200)->get(['id', 'name', 'status', 'pipeline_id', 'stage_id']),
            'deals' => CrmDeal::forWorkspace($org, $ws)->latest()->limit(200)->get(['id', 'name', 'status', 'pipeline_id', 'stage_id']),
            'members' => $this->members($workspace),
        ]);
    }

    public function hrm(Request $request, string $section)
    {
        $workspace = $this->workspace($request, 'hrm.view');
        $org = $workspace->organization_id;
        $ws = $workspace->id;

        [$title, $description, $columns, $records] = match ($section) {
            'employees' => ['Employees', 'Employee directory and employment status.', ['id', 'employee_number', 'name', 'email', 'joined_at', 'basic_salary', 'status'], HrEmployee::forWorkspace($org, $ws)->latest()->paginate(30)],
            'branches' => ['Branches', 'Business branches used by the HR structure.', ['id', 'name', 'created_at'], $this->paginateQuery(DB::table('hr_branches')->where('organization_id', $org)->where('workspace_id', $ws)->latest())],
            'departments' => ['Departments', 'Departments and branch relationships.', ['id', 'name', 'branch_id', 'created_at'], $this->paginateQuery(DB::table('hr_departments')->where('organization_id', $org)->where('workspace_id', $ws)->latest())],
            'designations' => ['Designations', 'Job designations grouped by department.', ['id', 'name', 'department_id', 'created_at'], $this->paginateQuery(DB::table('hr_designations')->where('organization_id', $org)->where('workspace_id', $ws)->latest())],
            'shifts' => ['Shifts', 'Work shifts, schedules and grace periods.', ['id', 'name', 'starts_at', 'ends_at', 'grace_minutes', 'created_at'], $this->paginateQuery(DB::table('hr_shifts')->where('organization_id', $org)->where('workspace_id', $ws)->latest())],
            'attendance' => ['Attendance', 'Daily employee attendance and clock records.', ['id', 'employee_id', 'attendance_date', 'clock_in', 'clock_out', 'status'], HrAttendance::where('organization_id', $org)->where('workspace_id', $ws)->latest('attendance_date')->paginate(30)],
            'leave-requests' => ['Leave Requests', 'Employee leave requests and review status.', ['id', 'employee_id', 'leave_type_id', 'starts_on', 'ends_on', 'days', 'status'], HrLeaveRequest::where('organization_id', $org)->where('workspace_id', $ws)->latest()->paginate(30)],
            'leave-types' => ['Leave Types', 'Leave policies, allowance and paid/unpaid rules.', ['id', 'name', 'annual_allowance', 'is_paid'], HrLeaveType::where('organization_id', $org)->where('workspace_id', $ws)->latest()->paginate(30)],
            'payroll' => ['Payroll & Payslips', 'Generated payroll records and payment status.', ['id', 'employee_id', 'period_start', 'period_end', 'gross_pay', 'net_pay', 'status'], HrPayslip::where('organization_id', $org)->where('workspace_id', $ws)->latest()->paginate(30)],
            'salary-components' => ['Salary Components', 'Earnings and deductions used by payroll.', ['id', 'name', 'type', 'calculation', 'value', 'is_taxable'], HrSalaryComponent::where('organization_id', $org)->where('workspace_id', $ws)->latest()->paginate(30)],
            'appraisals' => ['Appraisals', 'Employee performance review history.', ['id', 'employee_id', 'review_date', 'rating', 'feedback', 'reviewer_id'], $this->paginateQuery(DB::table('hr_appraisals')->where('organization_id', $org)->where('workspace_id', $ws)->latest('review_date'))],
            'documents' => ['Employee Documents', 'Employee files, metadata and expiry dates.', ['id', 'employee_id', 'name', 'mime_type', 'expires_on', 'created_at'], $this->paginateQuery(DB::table('hr_documents')->where('organization_id', $org)->where('workspace_id', $ws)->latest())],
            'holidays' => ['Holidays', 'Workspace holiday calendar and optional holidays.', ['id', 'name', 'holiday_date', 'is_optional'], $this->paginateQuery(DB::table('hr_holidays')->where('organization_id', $org)->where('workspace_id', $ws)->orderBy('holiday_date'))],
            default => abort(404),
        };

        return $this->render($request, 'HRM', $section, $title, $description, $columns, $records, '/hrm/dashboard', 'hrm.manage', [
            'employees' => HrEmployee::forWorkspace($org, $ws)->orderBy('name')->get(['id', 'name', 'employee_number']),
            'leaveTypes' => HrLeaveType::where('organization_id', $org)->where('workspace_id', $ws)->orderBy('name')->get(['id', 'name']),
            'pendingLeaves' => HrLeaveRequest::where('organization_id', $org)->where('workspace_id', $ws)->where('status', 'pending')->latest()->get(['id', 'employee_id', 'starts_on', 'ends_on']),
            'unpaidPayslips' => HrPayslip::where('organization_id', $org)->where('workspace_id', $ws)->where('status', '!=', 'paid')->latest()->get(['id', 'employee_id', 'period_start', 'period_end', 'net_pay', 'status']),
            'branches' => DB::table('hr_branches')->where('organization_id', $org)->where('workspace_id', $ws)->orderBy('name')->get(['id', 'name']),
            'departments' => DB::table('hr_departments')->where('organization_id', $org)->where('workspace_id', $ws)->orderBy('name')->get(['id', 'name']),
            'designations' => DB::table('hr_designations')->where('organization_id', $org)->where('workspace_id', $ws)->orderBy('name')->get(['id', 'name']),
            'shifts' => DB::table('hr_shifts')->where('organization_id', $org)->where('workspace_id', $ws)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function taskly(Request $request, string $section)
    {
        $workspace = $this->workspace($request, 'taskly.view');
        $org = $workspace->organization_id;
        $ws = $workspace->id;

        [$title, $description, $columns, $records] = match ($section) {
            'projects' => ['Projects', 'Projects, owners, budgets and lifecycle status.', ['id', 'name', 'starts_on', 'due_on', 'budget', 'manager_id', 'status'], TasklyProject::forWorkspace($org, $ws)->latest()->paginate(30)],
            'tasks' => ['Tasks', 'Task assignments, priority, stages and completion.', ['id', 'title', 'project_id', 'assigned_to', 'priority', 'due_on', 'completed_at'], TasklyTask::forWorkspace($org, $ws)->latest()->paginate(30)],
            'milestones' => ['Milestones', 'Project milestones and due dates.', ['id', 'project_id', 'name', 'due_on', 'status', 'created_at'], $this->paginateQuery(DB::table('taskly_milestones')->whereIn('project_id', TasklyProject::forWorkspace($org, $ws)->select('id'))->latest())],
            'timesheets' => ['Timesheets', 'Submitted and approved project time.', ['id', 'project_id', 'task_id', 'user_id', 'work_date', 'hours', 'status'], TasklyTimesheet::where('organization_id', $org)->where('workspace_id', $ws)->latest('work_date')->paginate(30)],
            'issues' => ['Issues', 'Project issues, severity, assignee and status.', ['id', 'project_id', 'task_id', 'title', 'severity', 'assigned_to', 'status', 'created_at'], $this->paginateQuery(DB::table('taskly_issues')->where('organization_id', $org)->where('workspace_id', $ws)->latest())],
            default => abort(404),
        };

        $projects = TasklyProject::forWorkspace($org, $ws)
            ->with(['stages:id,project_id,name,is_complete,position', 'members:id,name'])
            ->orderBy('name')
            ->get(['id', 'name', 'manager_id']);

        return $this->render($request, 'Projects & Tasks', $section, $title, $description, $columns, $records, '/taskly/dashboard', 'taskly.manage', [
            'projects' => $projects,
            'tasks' => TasklyTask::forWorkspace($org, $ws)->orderBy('title')->limit(300)->get(['id', 'project_id', 'stage_id', 'title']),
            'members' => $this->members($workspace),
            'submittedTimesheets' => TasklyTimesheet::where('organization_id', $org)->where('workspace_id', $ws)->where('status', 'submitted')->latest('work_date')->get(['id', 'project_id', 'user_id', 'work_date', 'hours']),
        ]);
    }

    private function render(Request $request, string $module, string $section, string $title, string $description, array $columns, $records, string $moduleHref, string $managePermission, array $lookups = [])
    {
        $workspace = Workspace::with('organization')->findOrFail($request->session()->get('active_workspace_id'));
        return Inertia::render('ModuleSection', [
            'module' => $module,
            'section' => $section,
            'title' => $title,
            'description' => $description,
            'columns' => $columns,
            'records' => $records,
            'canManage' => $request->user()->canInWorkspace($managePermission, $workspace),
            'lookups' => $lookups,
            'breadcrumbs' => [['label' => $module, 'href' => $moduleHref], ['label' => $title]],
        ]);
    }

    private function workspace(Request $request, string $permission): Workspace
    {
        $workspace = Workspace::with('organization')->find($request->session()->get('active_workspace_id'));
        abort_unless($workspace && $request->user()->canInWorkspace($permission, $workspace), 403);
        return $workspace;
    }

    private function members(Workspace $workspace): array
    {
        $members = $workspace->members()->select('users.id', 'users.name')->orderBy('users.name')->get()->map(fn ($user) => ['id' => $user->id, 'name' => $user->name])->all();
        $owner = $workspace->organization?->owner;
        if ($owner && ! collect($members)->contains(fn ($member) => (int) $member['id'] === (int) $owner->id)) {
            array_unshift($members, ['id' => $owner->id, 'name' => $owner->name]);
        }
        return $members;
    }

    private function paginateQuery(Builder $query, int $perPage = 30): LengthAwarePaginator
    {
        return $query->paginate($perPage)->withQueryString();
    }
}

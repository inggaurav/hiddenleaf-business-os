<?php

namespace App\Http\Controllers;

use App\Domain\CRM\CrmDashboardService;
use App\Models\AccountCustomer;
use App\Models\CrmDeal;
use App\Models\CrmLead;
use App\Models\CrmPipeline;
use App\Models\CrmStage;
use App\Models\CrmWebform;
use App\Models\Workspace;
use HiddenLeaf\Kernel\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class CrmController extends Controller
{
    public function dashboard(Request $request, CrmDashboardService $dashboardService)
    {
        $workspace = $this->workspace($request, 'crm.view');
        $pipelineId = $request->has('pipeline_id') ? (int) $request->get('pipeline_id') : null;
        $data = $dashboardService->getMetrics($workspace, $pipelineId, $this->isWorkspaceManager($request, $workspace) ? null : $request->user()->id);

        return Inertia::render('CRM/Dashboard', ['metrics' => $data['stats']] + $data);
    }

    public function index(Request $request)
    {
        $workspace = $this->workspace($request, 'crm.view');
        $leads = CrmLead::forWorkspace($workspace->organization_id, $workspace->id);
        $deals = CrmDeal::forWorkspace($workspace->organization_id, $workspace->id);
        if (! $this->isWorkspaceManager($request, $workspace)) {
            $leads->where('assigned_to', $request->user()->id);
            $deals->where('assigned_to', $request->user()->id);
        }

        $leadCount = (clone $leads)->count();
        $converted = (clone $leads)->whereNotNull('converted_at')->count();

        $loadedLeads = (clone $leads)->with(['pipeline:id,name', 'stage:id,name', 'assignedUser:id,name,email'])->withCount('notes');

        return Inertia::render('CRM/Index', [
            'pipelines' => CrmPipeline::where('workspace_id', $workspace->id)->with('stages')->get(),
            'leads' => (clone $loadedLeads)->latest()->paginate(30),
            'allLeads' => (clone $loadedLeads)->latest()->get(),
            'deals' => (clone $deals)->with(['pipeline:id,name', 'stage:id,name', 'assignedUser:id,name,email'])->latest()->paginate(30),
            'metrics' => [
                'leads' => $leadCount,
                'open_leads' => (clone $leads)->where('status', 'open')->count(),
                'deals' => (clone $deals)->count(),
                'pipeline_value' => (float) (clone $deals)->where('status', 'open')->sum('value'),
                'won_value' => (float) (clone $deals)->where('status', 'won')->sum('value'),
                'conversion_rate' => $leadCount > 0 ? round(($converted / $leadCount) * 100, 2) : 0,
                'stage_distribution' => (clone $deals)->select('stage_id', DB::raw('COUNT(*) as aggregate'))->groupBy('stage_id')->pluck('aggregate', 'stage_id'),
            ],
        ]);
    }

    public function storePipeline(Request $request)
    {
        $workspace = $this->workspace($request, 'crm.manage');
        $data = $request->validate(['name' => ['required', 'string', 'max:100'], 'is_default' => ['boolean'], 'stages' => ['required', 'array', 'min:1'], 'stages.*.name' => ['required', 'string'], 'stages.*.probability' => ['nullable', 'numeric', 'between:0,100'], 'stages.*.is_closed' => ['boolean'], 'stages.*.outcome' => ['nullable', Rule::in(['won', 'lost'])]]);
        DB::transaction(function () use ($data, $workspace) {
            if ($data['is_default'] ?? false) {
                CrmPipeline::where('workspace_id', $workspace->id)->update(['is_default' => false]);
            }$pipeline = CrmPipeline::create(['organization_id' => $workspace->organization_id, 'workspace_id' => $workspace->id, 'name' => $data['name'], 'is_default' => $data['is_default'] ?? false]);
            foreach ($data['stages'] as $position => $stage) {
                $pipeline->stages()->create($stage + ['position' => $position]);
            }
        });

        return back()->with('success', 'Pipeline created.');
    }

    public function storeLead(Request $request)
    {
        $workspace = $this->workspace($request, 'crm.manage');
        $data = $request->validate(['pipeline_id' => ['required', 'integer'], 'stage_id' => ['required', 'integer'], 'source_id' => ['nullable', 'integer'], 'assigned_to' => ['nullable', 'integer'], 'name' => ['required', 'string', 'max:255'], 'email' => ['nullable', 'email'], 'phone' => ['nullable', 'string'], 'company' => ['nullable', 'string'], 'estimated_value' => ['nullable', 'numeric', 'min:0']]);
        $this->pipelineStage($workspace, $data['pipeline_id'], $data['stage_id']);
        $this->assignee($workspace, $data['assigned_to'] ?? null);
        CrmLead::create($data + ['organization_id' => $workspace->organization_id, 'workspace_id' => $workspace->id, 'status' => 'open', 'created_by' => $request->user()->id]);

        return back()->with('success', 'Lead created.');
    }

    public function moveLead(Request $request, int $leadId, AuditLogger $audit)
    {
        $workspace = $this->workspace($request, 'crm.manage');
        $lead = CrmLead::findOrFail($leadId);
        $this->tenant($lead, $workspace);
        $this->assertAssignedRecord($request, $workspace, $lead);
        $data = $request->validate(['stage_id' => ['required', 'integer']]);
        $stage = $this->pipelineStage($workspace, $lead->pipeline_id, $data['stage_id']);

        DB::transaction(function () use ($lead, $stage, $request, $workspace, $audit) {
            $lead->update(['stage_id' => $stage->id]);
            $audit->log($request->user()->id, $workspace->organization_id, $workspace->id, 'lead.moved', 'crm_lead', (string) $lead->id, [
                'stage_id' => $stage->id,
                'stage_name' => $stage->name,
            ]);
        });

        return back()->with('success', 'Lead stage updated.');
    }

    public function convertLead(Request $request, int $leadId, AuditLogger $audit)
    {
        $workspace = $this->workspace($request, 'crm.manage');
        $lead = CrmLead::findOrFail($leadId);
        $this->tenant($lead, $workspace);
        $this->assertAssignedRecord($request, $workspace, $lead);
        abort_unless($lead->status === 'open', 422, 'Lead already converted or closed.');
        $data = $request->validate([
            'name' => ['nullable', 'string'],
            'value' => ['nullable', 'numeric', 'min:0'],
            'expected_close_on' => ['nullable', 'date'],
            'create_customer' => ['nullable', 'boolean'],
        ]);

        DB::transaction(function () use ($lead, $data, $request, $workspace, $audit) {
            $customer = null;
            if (! empty($data['create_customer'])) {
                $customer = AccountCustomer::forWorkspace($workspace->organization_id, $workspace->id)
                    ->where('email', $lead->email)
                    ->whereNotNull('email')
                    ->first();

                if (! $customer) {
                    $customer = AccountCustomer::create([
                        'organization_id' => $workspace->organization_id,
                        'workspace_id' => $workspace->id,
                        'customer_id' => strtoupper(substr(uniqid('CUST-'), -8)),
                        'name' => $lead->name,
                        'email' => $lead->email,
                        'contact' => $lead->phone,
                        'billing_name' => $lead->name,
                        'is_active' => true,
                        'created_by' => $request->user()->id,
                    ]);
                }
            }

            $deal = CrmDeal::create([
                'organization_id' => $workspace->organization_id,
                'workspace_id' => $workspace->id,
                'lead_id' => $lead->id,
                'pipeline_id' => $lead->pipeline_id,
                'stage_id' => $lead->stage_id,
                'assigned_to' => $lead->assigned_to,
                'name' => $data['name'] ?? $lead->name,
                'value' => $data['value'] ?? $lead->estimated_value,
                'expected_close_on' => $data['expected_close_on'] ?? null,
                'status' => 'open',
            ]);

            $lead->update(['status' => 'converted', 'converted_at' => now()]);
            $audit->log($request->user()->id, $workspace->organization_id, $workspace->id, 'lead.converted', 'crm_deal', (string) $deal->id, [
                'lead_id' => $lead->id,
                'customer_id' => $customer?->id,
            ], critical: true);
        });

        return back()->with('success', 'Lead converted successfully.');
    }

    public function storeWebform(Request $request)
    {
        $workspace = $this->workspace($request, 'crm.manage');
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'pipeline_id' => ['required', 'integer'],
            'stage_id' => ['required', 'integer'],
            'source_id' => ['nullable', 'integer'],
            'assigned_to' => ['nullable', 'integer'],
            'fields' => ['nullable', 'array'],
        ]);

        $this->pipelineStage($workspace, $data['pipeline_id'], $data['stage_id']);
        $this->assignee($workspace, $data['assigned_to'] ?? null);

        $webform = CrmWebform::create([
            'organization_id' => $workspace->organization_id,
            'workspace_id' => $workspace->id,
            'name' => $data['name'],
            'token' => Str::random(40),
            'pipeline_id' => $data['pipeline_id'],
            'stage_id' => $data['stage_id'],
            'source_id' => $data['source_id'] ?? null,
            'assigned_to' => $data['assigned_to'] ?? null,
            'is_active' => true,
            'fields' => $data['fields'] ?? ['name', 'email', 'phone', 'company', 'message'],
            'created_by' => $request->user()->id,
        ]);

        return back()->with('success', 'CRM Webform created.');
    }

    public function publicWebformSubmit(Request $request, string $token)
    {
        $webform = CrmWebform::where('token', $token)->where('is_active', true)->firstOrFail();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'company' => ['nullable', 'string', 'max:255'],
            'message' => ['nullable', 'string', 'max:2000'],
            'estimated_value' => ['nullable', 'numeric', 'min:0'],
            'hp_fax' => ['nullable', 'string', 'max:0'], // honeypot
        ]);

        $lead = CrmLead::create([
            'organization_id' => $webform->organization_id,
            'workspace_id' => $webform->workspace_id,
            'pipeline_id' => $webform->pipeline_id,
            'stage_id' => $webform->stage_id,
            'source_id' => $webform->source_id,
            'assigned_to' => $webform->assigned_to,
            'name' => $data['name'],
            'email' => $data['email'] ?? null,
            'phone' => $data['phone'] ?? null,
            'company' => $data['company'] ?? null,
            'estimated_value' => $data['estimated_value'] ?? 0,
            'status' => 'open',
            'created_by' => null,
        ]);

        if (! empty($data['message'])) {
            DB::table('crm_notes')->insert([
                'organization_id' => $webform->organization_id,
                'workspace_id' => $webform->workspace_id,
                'subject_type' => $lead->getMorphClass(),
                'subject_id' => $lead->id,
                'body' => $data['message'],
                'created_by' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return response()->json(['status' => 'success', 'message' => 'Thank you for your submission. Our team will contact you soon.']);
    }

    public function storeDeal(Request $request)
    {
        $workspace = $this->workspace($request, 'crm.manage');
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'value' => ['required', 'numeric', 'min:0'],
            'pipeline_id' => ['nullable', 'integer', 'exists:crm_pipelines,id'],
            'stage_id' => ['nullable', 'integer', 'exists:crm_stages,id'],
            'lead_id' => ['nullable', 'integer', 'exists:crm_leads,id'],
            'assigned_to' => ['nullable', 'integer', 'exists:users,id'],
            'expected_close_date' => ['nullable', 'date'],
            'expected_close_on' => ['nullable', 'date'],
            'probability' => ['nullable', 'integer', 'min:0', 'max:100'],
            'source' => ['nullable', 'string', 'max:100'],
        ]);

        $dealService = app(\HiddenLeaf\CrmDealsKanban\Domain\Services\DealService::class);
        $deal = $dealService->createDeal($workspace, $data, $request->user());

        return back()->with('success', "Deal [{$deal->name}] created.");
    }

    public function moveDeal(Request $request, int $dealId, AuditLogger $audit)
    {
        $workspace = $this->workspace($request, 'crm.manage');
        $deal = CrmDeal::findOrFail($dealId);
        $this->tenant($deal, $workspace);
        $this->assertAssignedRecord($request, $workspace, $deal);
        abort_unless($deal->status === 'open', 422, 'Closed deals cannot move.');
        $data = $request->validate(['stage_id' => ['required', 'integer'], 'loss_reason' => ['nullable', 'string']]);
        $stage = $this->pipelineStage($workspace, $deal->pipeline_id, $data['stage_id']);
        $status = $stage->is_closed ? ($stage->outcome ?? (strtolower($stage->name) === 'won' ? 'won' : 'lost')) : 'open';
        DB::transaction(function () use ($deal, $data, $status, $stage, $request, $workspace, $audit) {
            $deal->update([
                'stage_id' => $stage->id,
                'status' => $status,
                'probability' => (int) ($stage->probability ?? $stage->default_probability ?? $deal->probability),
                'actual_close_date' => $status === 'open' ? null : today()->toDateString(),
                'closed_at' => $status === 'open' ? null : now(),
                'loss_reason' => $status === 'lost' ? ($data['loss_reason'] ?? null) : null,
            ]);
            if ($status !== 'open') {
                $audit->log($request->user()->id, $workspace->organization_id, $workspace->id, 'deal.'.$status, 'crm_deal', (string) $deal->id, ['value' => $deal->value], critical: true);
            }
        });

        return back()->with('success', 'Deal stage updated.');
    }

    public function addNote(Request $request, string $type, int $id)
    {
        $workspace = $this->workspace($request, 'crm.manage');
        $subject = $this->subject($type, $id, $workspace);
        $this->assertAssignedRecord($request, $workspace, $subject);
        $data = $request->validate(['body' => ['required', 'string']]);
        DB::table('crm_notes')->insert(['organization_id' => $workspace->organization_id, 'workspace_id' => $workspace->id, 'subject_type' => $subject->getMorphClass(), 'subject_id' => $subject->id, 'body' => $data['body'], 'created_by' => $request->user()->id, 'created_at' => now(), 'updated_at' => now()]);

        return back()->with('success', 'Note added.');
    }

    public function addActivity(Request $request, string $type, int $id)
    {
        $workspace = $this->workspace($request, 'crm.manage');
        $subject = $this->subject($type, $id, $workspace);
        $this->assertAssignedRecord($request, $workspace, $subject);
        $data = $request->validate(['type' => ['required', Rule::in(['call', 'meeting', 'email', 'task'])], 'title' => ['required', 'string'], 'due_at' => ['nullable', 'date'], 'assigned_to' => ['nullable', 'integer']]);
        $this->assignee($workspace, $data['assigned_to'] ?? null);
        DB::table('crm_activities')->insert($data + ['organization_id' => $workspace->organization_id, 'workspace_id' => $workspace->id, 'subject_type' => $subject->getMorphClass(), 'subject_id' => $subject->id, 'created_by' => $request->user()->id, 'created_at' => now(), 'updated_at' => now()]);

        return back()->with('success', 'Activity added.');
    }

    public function storeTask(Request $request)
    {
        $workspace = $this->workspace($request, 'crm.manage');
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::in(['call', 'meeting', 'email', 'task'])],
            'due_at' => ['required', 'date'],
            'assigned_to' => ['nullable', 'integer'],
            'subject_type' => ['nullable', 'string'],
            'subject_id' => ['nullable', 'integer'],
        ]);

        if (! empty($data['assigned_to'])) {
            $this->assignee($workspace, $data['assigned_to']);
        }

        DB::table('crm_activities')->insert([
            'organization_id' => $workspace->organization_id,
            'workspace_id' => $workspace->id,
            'subject_type' => $data['subject_type'] ?? null,
            'subject_id' => $data['subject_id'] ?? null,
            'type' => $data['type'],
            'title' => $data['title'],
            'due_at' => $data['due_at'],
            'assigned_to' => $data['assigned_to'] ?? $request->user()->id,
            'created_by' => $request->user()->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return back()->with('success', 'CRM Task scheduled.');
    }

    public function toggleActivity(Request $request, int $id)
    {
        $workspace = $this->workspace($request, 'crm.manage');
        $activity = DB::table('crm_activities')
            ->where('organization_id', $workspace->organization_id)
            ->where('workspace_id', $workspace->id)
            ->where('id', $id)
            ->firstOrFail();

        $completedAt = $activity->completed_at ? null : now();
        DB::table('crm_activities')->where('id', $id)->update(['completed_at' => $completedAt, 'updated_at' => now()]);

        return back()->with('success', $completedAt ? 'Task marked completed.' : 'Task reopened.');
    }

    public function showLead(Request $request, int $leadId): \Illuminate\Http\JsonResponse
    {
        $workspace = $this->workspace($request, 'crm.view');
        $lead = CrmLead::with([
            'pipeline:id,name',
            'stage:id,name',
            'assignedUser:id,name,email',
            'notes.creator:id,name',
            'activities.creator:id,name',
            'files.uploader:id,name',
        ])->findOrFail($leadId);
        $this->tenant($lead, $workspace);

        return response()->json([
            'lead'       => $lead,
            'pipelines'  => CrmPipeline::where('workspace_id', $workspace->id)->with('stages')->get(),
            'teamMembers'=> $workspace->members()->get(['users.id', 'users.name', 'users.email']),
            'canManage'  => $this->isWorkspaceManager($request, $workspace)
                            || $request->user()->canInWorkspace('crm.manage', $workspace),
        ]);
    }

    public function uploadFile(Request $request, string $type, int $id)
    {
        $workspace = $this->workspace($request, 'crm.manage');
        $subject = $this->subject($type, $id, $workspace);
        $this->assertAssignedRecord($request, $workspace, $subject);

        $request->validate([
            'file' => ['required', 'file', 'max:20480'],
        ]);

        $file = $request->file('file');
        $storedPath = $file->store("{$type}s/{$subject->id}", 'public');

        \HiddenLeaf\CrmDealsKanban\Models\CrmDealFile::create([
            'workspace_id' => $workspace->id,
            'deal_id'      => $type === 'deal' ? $subject->id : null,
            'lead_id'      => $type === 'lead' ? $subject->id : null,
            'user_id'      => $request->user()->id,
            'file_path'    => $storedPath,
            'file_name'    => $file->getClientOriginalName(),
            'file_size'    => $file->getSize(),
            'path'         => $storedPath,
            'name'         => $file->getClientOriginalName(),
            'size'         => $file->getSize(),
            'mime_type'    => $file->getClientMimeType(),
        ]);

        return back()->with('success', 'File attached.');
    }

    private function workspace(Request $request, string $permission): Workspace
    {
        $workspace = Workspace::with('organization')->find($request->session()->get('active_workspace_id'));
        abort_unless($workspace && $request->user()->canInWorkspace($permission, $workspace), 403);

        return $workspace;
    }

    private function isWorkspaceManager(Request $request, Workspace $workspace): bool
    {
        return $request->user()->isSuperAdmin()
            || in_array($request->user()->role, ['company', 'company_admin'], true)
            || (int) $workspace->organization->owner_id === (int) $request->user()->id;
    }

    private function assertAssignedRecord(Request $request, Workspace $workspace, $record): void
    {
        if (! $this->isWorkspaceManager($request, $workspace)) {
            abort_unless((int) $record->assigned_to === (int) $request->user()->id, 403);
        }
    }

    private function pipelineStage(Workspace $workspace, int $pipelineId, int $stageId): CrmStage
    {
        $pipeline = CrmPipeline::where('organization_id', $workspace->organization_id)->where('workspace_id', $workspace->id)->findOrFail($pipelineId);

        return CrmStage::where('pipeline_id', $pipeline->id)->findOrFail($stageId);
    }

    private function assignee(Workspace $workspace, ?int $userId): void
    {
        if ($userId) {
            abort_unless($workspace->members()->where('users.id', $userId)->exists() || (int) $workspace->organization->owner_id === $userId, 422, 'Assignee is not a workspace member.');
        }
    }

    private function tenant($model, Workspace $workspace): void
    {
        abort_unless((int) $model->organization_id === (int) $workspace->organization_id && (int) $model->workspace_id === (int) $workspace->id, 404);
    }

    private function subject(string $type, int $id, Workspace $workspace)
    {
        $model = match ($type) {
            'lead' => CrmLead::class,'deal' => CrmDeal::class,default => abort(404)
        };
        $subject = $model::findOrFail($id);
        $this->tenant($subject, $workspace);

        return $subject;
    }
}

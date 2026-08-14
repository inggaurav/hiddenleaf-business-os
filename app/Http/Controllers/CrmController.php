<?php

namespace App\Http\Controllers;

use App\Models\CrmDeal;
use App\Models\CrmLead;
use App\Models\CrmPipeline;
use App\Models\CrmStage;
use App\Models\Workspace;
use HiddenLeaf\Kernel\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class CrmController extends Controller
{
    public function index(Request $request)
    {
        $workspace = $this->workspace($request, 'crm.view');
        $leads = CrmLead::forWorkspace($workspace->organization_id, $workspace->id);
        $deals = CrmDeal::forWorkspace($workspace->organization_id, $workspace->id);

        $leadCount = (clone $leads)->count();
        $converted = (clone $leads)->whereNotNull('converted_at')->count();

        return Inertia::render('CRM/Index', ['pipelines' => CrmPipeline::where('workspace_id', $workspace->id)->with('stages')->get(), 'leads' => $leads->latest()->paginate(30), 'deals' => $deals->latest()->paginate(30), 'metrics' => ['leads' => $leadCount, 'open_leads' => (clone $leads)->where('status', 'open')->count(), 'deals' => (clone $deals)->count(), 'pipeline_value' => (float) (clone $deals)->where('status', 'open')->sum('value'), 'won_value' => (float) (clone $deals)->where('status', 'won')->sum('value'), 'conversion_rate' => $leadCount > 0 ? round(($converted / $leadCount) * 100, 2) : 0, 'stage_distribution' => (clone $deals)->select('stage_id', DB::raw('COUNT(*) as aggregate'))->groupBy('stage_id')->pluck('aggregate', 'stage_id')]]);
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

    public function moveLead(Request $request, CrmLead $lead)
    {
        $workspace = $this->workspace($request, 'crm.manage');
        $this->tenant($lead, $workspace);
        $data = $request->validate(['stage_id' => ['required', 'integer']]);
        $this->pipelineStage($workspace, $lead->pipeline_id, $data['stage_id']);
        $lead->update($data);

        return back()->with('success', 'Lead stage updated.');
    }

    public function convertLead(Request $request, CrmLead $lead, AuditLogger $audit)
    {
        $workspace = $this->workspace($request, 'crm.manage');
        $this->tenant($lead, $workspace);
        abort_unless($lead->status === 'open', 422, 'Lead already converted or closed.');
        $data = $request->validate(['name' => ['nullable', 'string'], 'value' => ['nullable', 'numeric', 'min:0'], 'expected_close_on' => ['nullable', 'date']]);
        DB::transaction(function () use ($lead, $data, $request, $workspace, $audit) {
            $deal = CrmDeal::create(['organization_id' => $workspace->organization_id, 'workspace_id' => $workspace->id, 'lead_id' => $lead->id, 'pipeline_id' => $lead->pipeline_id, 'stage_id' => $lead->stage_id, 'assigned_to' => $lead->assigned_to, 'name' => $data['name'] ?? $lead->name, 'value' => $data['value'] ?? $lead->estimated_value, 'expected_close_on' => $data['expected_close_on'] ?? null, 'status' => 'open']);
            $lead->update(['status' => 'converted', 'converted_at' => now()]);
            $audit->log($request->user()->id, $workspace->organization_id, $workspace->id, 'lead.converted', 'crm_deal', (string) $deal->id, ['lead_id' => $lead->id], critical: true);
        });

        return back()->with('success', 'Lead converted to deal.');
    }

    public function moveDeal(Request $request, CrmDeal $deal, AuditLogger $audit)
    {
        $workspace = $this->workspace($request, 'crm.manage');
        $this->tenant($deal, $workspace);
        abort_unless($deal->status === 'open', 422, 'Closed deals cannot move.');
        $data = $request->validate(['stage_id' => ['required', 'integer'], 'loss_reason' => ['nullable', 'string']]);
        $stage = $this->pipelineStage($workspace, $deal->pipeline_id, $data['stage_id']);
        $status = $stage->is_closed ? ($stage->outcome ?? 'lost') : 'open';
        DB::transaction(function () use ($deal, $data, $status, $stage, $request, $workspace, $audit) {
            $deal->update(['stage_id' => $stage->id, 'status' => $status, 'closed_at' => $status === 'open' ? null : now(), 'loss_reason' => $status === 'lost' ? ($data['loss_reason'] ?? null) : null]);
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
        $data = $request->validate(['body' => ['required', 'string']]);
        DB::table('crm_notes')->insert(['organization_id' => $workspace->organization_id, 'workspace_id' => $workspace->id, 'subject_type' => $subject->getMorphClass(), 'subject_id' => $subject->id, 'body' => $data['body'], 'created_by' => $request->user()->id, 'created_at' => now(), 'updated_at' => now()]);

        return back()->with('success', 'Note added.');
    }

    public function addActivity(Request $request, string $type, int $id)
    {
        $workspace = $this->workspace($request, 'crm.manage');
        $subject = $this->subject($type, $id, $workspace);
        $data = $request->validate(['type' => ['required', Rule::in(['call', 'meeting', 'email', 'task'])], 'title' => ['required', 'string'], 'due_at' => ['nullable', 'date'], 'assigned_to' => ['nullable', 'integer']]);
        $this->assignee($workspace, $data['assigned_to'] ?? null);
        DB::table('crm_activities')->insert($data + ['organization_id' => $workspace->organization_id, 'workspace_id' => $workspace->id, 'subject_type' => $subject->getMorphClass(), 'subject_id' => $subject->id, 'created_by' => $request->user()->id, 'created_at' => now(), 'updated_at' => now()]);

        return back()->with('success', 'Activity added.');
    }

    private function workspace(Request $request, string $permission): Workspace
    {
        $workspace = Workspace::with('organization')->find($request->session()->get('active_workspace_id'));
        abort_unless($workspace && $request->user()->canInWorkspace($permission, $workspace), 403);

        return $workspace;
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

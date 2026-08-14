<?php

namespace App\Domain\CRM;

use App\Models\CrmDeal;
use App\Models\CrmLead;
use App\Models\CrmPipeline;
use App\Models\CrmStage;
use App\Models\Workspace;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class CrmDashboardService
{
    public function getMetrics(Workspace $workspace, ?int $pipelineId = null): array
    {
        $orgId = $workspace->organization_id;
        $wsId = $workspace->id;

        $leadsQuery = CrmLead::where('organization_id', $orgId)->where('workspace_id', $wsId);
        $dealsQuery = CrmDeal::where('organization_id', $orgId)->where('workspace_id', $wsId);

        if ($pipelineId) {
            $leadsQuery->where('pipeline_id', $pipelineId);
            $dealsQuery->where('pipeline_id', $pipelineId);
        }

        $totalLeads = (clone $leadsQuery)->count();
        $openLeads = (clone $leadsQuery)->where('status', 'open')->count();
        $convertedLeads = (clone $leadsQuery)->where('status', 'converted')->count();

        $totalDeals = (clone $dealsQuery)->count();
        $openDeals = (clone $dealsQuery)->where('status', 'open')->count();
        $wonDeals = (clone $dealsQuery)->where('status', 'won')->count();
        $lostDeals = (clone $dealsQuery)->where('status', 'lost')->count();

        $pipelineValue = (float) (clone $dealsQuery)->where('status', 'open')->sum('value');
        $wonValue = (float) (clone $dealsQuery)->where('status', 'won')->sum('value');

        $conversionRate = $totalLeads > 0 ? round(($convertedLeads / $totalLeads) * 100, 1) : 0;

        // Pipelines list for selector
        $pipelines = CrmPipeline::where('organization_id', $orgId)
            ->where('workspace_id', $wsId)
            ->with('stages')
            ->get();

        // Selected pipeline stages distribution
        $activePipeline = $pipelineId
            ? $pipelines->firstWhere('id', $pipelineId)
            : ($pipelines->firstWhere('is_default', true) ?? $pipelines->first());

        $stageDistribution = [];
        if ($activePipeline) {
            foreach ($activePipeline->stages as $stage) {
                $dealsInStage = CrmDeal::where('organization_id', $orgId)
                    ->where('workspace_id', $wsId)
                    ->where('stage_id', $stage->id)
                    ->count();

                $valueInStage = (float) CrmDeal::where('organization_id', $orgId)
                    ->where('workspace_id', $wsId)
                    ->where('stage_id', $stage->id)
                    ->sum('value');

                $stageDistribution[] = [
                    'stage_id' => $stage->id,
                    'name' => $stage->name,
                    'deals' => $dealsInStage,
                    'value' => $valueInStage,
                    'is_closed' => (bool) $stage->is_closed,
                    'outcome' => $stage->outcome,
                ];
            }
        }

        // Recent Leads
        $recentLeads = (clone $leadsQuery)->latest()->limit(5)->get()->map(fn ($l) => [
            'id' => $l->id,
            'name' => $l->name,
            'email' => $l->email,
            'company' => $l->company ?? '—',
            'estimated_value' => (float) $l->estimated_value,
            'status' => $l->status,
            'created_at' => $l->created_at->format('M d, Y'),
        ]);

        // Recent Deals
        $recentDeals = (clone $dealsQuery)->with('stage')->latest()->limit(5)->get()->map(fn ($d) => [
            'id' => $d->id,
            'name' => $d->name,
            'value' => (float) $d->value,
            'stage_name' => $d->stage->name ?? 'Stage',
            'status' => $d->status,
            'expected_close_on' => $d->expected_close_on,
            'created_at' => $d->created_at->format('M d, Y'),
        ]);

        // Recent CRM Activities
        $recentActivities = DB::table('crm_activities')
            ->where('organization_id', $orgId)
            ->where('workspace_id', $wsId)
            ->latest('created_at')
            ->limit(5)
            ->get()
            ->map(fn ($act) => [
                'id' => $act->id,
                'title' => $act->title,
                'type' => $act->type,
                'due_at' => $act->due_at,
                'created_at' => Carbon::parse($act->created_at)->format('M d, Y'),
            ]);

        return [
            'stats' => [
                'total_leads' => $totalLeads,
                'open_leads' => $openLeads,
                'converted_leads' => $convertedLeads,
                'total_deals' => $totalDeals,
                'open_deals' => $openDeals,
                'won_deals' => $wonDeals,
                'lost_deals' => $lostDeals,
                'pipeline_value' => $pipelineValue,
                'won_value' => $wonValue,
                'conversion_rate' => $conversionRate,
            ],
            'pipelines' => $pipelines,
            'activePipelineId' => $activePipeline?->id,
            'stageDistribution' => $stageDistribution,
            'recentLeads' => $recentLeads,
            'recentDeals' => $recentDeals,
            'recentActivities' => $recentActivities,
        ];
    }
}

<?php

namespace App\Providers;

use App\Domain\Automation\Actions\ActionRegistry;
use App\Domain\Automation\Conditions\ConditionEvaluator;
use App\Domain\Automation\Execution\AutomationEngine;
use App\Domain\Automation\Missions\MissionExecutor;
use App\Domain\Automation\Missions\MissionPlanner;
use App\Domain\Automation\Missions\MissionStateMachine;
use App\Domain\Automation\Triggers\TriggerRegistry;
use App\Domain\Communications\Actions\CommunicationReplyGenerator;
use App\Domain\Communications\Actions\CommunicationSendService;
use App\Domain\Communications\Matching\AttentionPriorityCalculator;
use App\Domain\Communications\Matching\IdentityMatcher;
use App\Domain\Communications\Matching\InteractionClassificationEngine;
use App\Domain\Communications\Providers\CommunicationProviderRegistry;
use App\Domain\Communications\Sync\CommunicationSyncService;
use App\Domain\Communications\Webhooks\CommunicationWebhookService;
use App\Domain\MrFox\Agent\MrFoxAgent;
use App\Domain\MrFox\Approvals\ActionApprovalService;
use App\Domain\MrFox\Context\BusinessContextService;
use App\Domain\MrFox\Insights\BusinessInsightService;
use App\Domain\MrFox\Knowledge\KnowledgeSearchService;
use App\Domain\MrFox\Observability\MrFoxAuditService;
use App\Domain\MrFox\Observability\MrFoxUsageService;
use App\Domain\MrFox\Providers\ProviderRouter;
use App\Domain\MrFox\Review\ContentReviewEngine;
use App\Domain\MrFox\Skills\SkillRegistry;
use App\Domain\MrFox\Tools\AccountingCashPositionTool;
use App\Domain\MrFox\Tools\AccountingPnlTool;
use App\Domain\MrFox\Tools\BrandProfileGetTool;
use App\Domain\MrFox\Tools\BusinessAlertsTool;
use App\Domain\MrFox\Tools\BusinessDashboardSummaryTool;
use App\Domain\MrFox\Tools\CommunicationsDraftReplyTool;
use App\Domain\MrFox\Tools\CommunicationsGetTool;
use App\Domain\MrFox\Tools\CommunicationsSearchTool;
use App\Domain\MrFox\Tools\CommunicationsSendReplyTool;
use App\Domain\MrFox\Tools\CommunicationsSummarizeTool;
use App\Domain\MrFox\Tools\CommunicationsUnreadSummaryTool;
use App\Domain\MrFox\Tools\CommunicationsUrgentSummaryTool;
use App\Domain\MrFox\Tools\CrmAddNoteTool;
use App\Domain\MrFox\Tools\CrmCreateLeadTool;
use App\Domain\MrFox\Tools\CrmGetLeadTool;
use App\Domain\MrFox\Tools\CrmPipelineSummaryTool;
use App\Domain\MrFox\Tools\CrmSearchLeadsTool;
use App\Domain\MrFox\Tools\HrAttendanceSummaryTool;
use App\Domain\MrFox\Tools\HrEmployeeSummaryTool;
use App\Domain\MrFox\Tools\HrPendingLeaveTool;
use App\Domain\MrFox\Tools\InventoryLowStockTool;
use App\Domain\MrFox\Tools\InventoryStockSummaryTool;
use App\Domain\MrFox\Tools\KnowledgeAskTool;
use App\Domain\MrFox\Tools\KnowledgeGetDocumentTool;
use App\Domain\MrFox\Tools\KnowledgeSearchTool;
use App\Domain\MrFox\Tools\MissionsControlTool;
use App\Domain\MrFox\Tools\MissionsCreateTool;
use App\Domain\MrFox\Tools\MissionsGetTool;
use App\Domain\MrFox\Tools\MissionsListTool;
use App\Domain\MrFox\Tools\MrFoxToolRegistry;
use App\Domain\MrFox\Tools\PurchaseBillSearchTool;
use App\Domain\MrFox\Tools\PurchasePayablesSummaryTool;
use App\Domain\MrFox\Tools\SalesInvoiceSearchTool;
use App\Domain\MrFox\Tools\SalesOutstandingSummaryTool;
use App\Domain\MrFox\Tools\SkillExecuteTool;
use App\Domain\MrFox\Tools\SkillListTool;
use App\Domain\MrFox\Tools\TasklyCreateTaskTool;
use App\Domain\MrFox\Tools\TasklyOverdueTasksTool;
use App\Domain\MrFox\Tools\TasklySearchProjectsTool;
use App\Domain\MrFox\Validation\ToolInputValidator;
use App\Domain\Settings\SettingsManager;
use Illuminate\Support\ServiceProvider;

class MrFoxServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(KnowledgeSearchService::class);
        $this->app->singleton(SkillRegistry::class);
        $this->app->singleton(ContentReviewEngine::class);

        // Communications Domain Singletons
        $this->app->singleton(CommunicationProviderRegistry::class);
        $this->app->singleton(InteractionClassificationEngine::class);
        $this->app->singleton(AttentionPriorityCalculator::class);
        $this->app->singleton(IdentityMatcher::class);
        $this->app->singleton(CommunicationSyncService::class);
        $this->app->singleton(CommunicationWebhookService::class);
        $this->app->singleton(CommunicationSendService::class);
        $this->app->singleton(CommunicationReplyGenerator::class);

        // Automation & Missions Domain Singletons
        $this->app->singleton(TriggerRegistry::class);
        $this->app->singleton(ConditionEvaluator::class);
        $this->app->singleton(ActionRegistry::class);
        $this->app->singleton(AutomationEngine::class);
        $this->app->singleton(MissionStateMachine::class);
        $this->app->singleton(MissionPlanner::class);
        $this->app->singleton(MissionExecutor::class);

        $this->app->singleton(MrFoxToolRegistry::class, function ($app) {
            $registry = new MrFoxToolRegistry();

            // Core Dashboard & Alerts
            $registry->register(new BusinessDashboardSummaryTool());
            $registry->register(new BusinessAlertsTool($app->make(BusinessInsightService::class)));

            // CRM
            $registry->register(new CrmSearchLeadsTool());
            $registry->register(new CrmGetLeadTool());
            $registry->register(new CrmPipelineSummaryTool());
            $registry->register(new CrmCreateLeadTool());
            $registry->register(new CrmAddNoteTool());

            // Sales & Procurement
            $registry->register(new SalesInvoiceSearchTool());
            $registry->register(new SalesOutstandingSummaryTool());
            $registry->register(new PurchaseBillSearchTool());
            $registry->register(new PurchasePayablesSummaryTool());

            // Accounting & Inventory
            $registry->register(new AccountingPnlTool());
            $registry->register(new AccountingCashPositionTool());
            $registry->register(new InventoryStockSummaryTool());
            $registry->register(new InventoryLowStockTool());

            // Taskly & HRM
            $registry->register(new TasklySearchProjectsTool());
            $registry->register(new TasklyOverdueTasksTool());
            $registry->register(new TasklyCreateTaskTool());
            $registry->register(new HrEmployeeSummaryTool());
            $registry->register(new HrAttendanceSummaryTool());
            $registry->register(new HrPendingLeaveTool());

            // Governed Knowledge & RAG
            $registry->register(new KnowledgeSearchTool($app->make(KnowledgeSearchService::class)));
            $registry->register(new KnowledgeGetDocumentTool());
            $registry->register(new KnowledgeAskTool($app->make(KnowledgeSearchService::class)));

            // Brand Profiles & Skills
            $registry->register(new BrandProfileGetTool());
            $registry->register(new SkillListTool($app->make(SkillRegistry::class)));
            $registry->register(new SkillExecuteTool(
                $app->make(SkillRegistry::class),
                $app->make(ProviderRouter::class),
                $app->make(ContentReviewEngine::class)
            ));

            // Unified Communications Tools
            $registry->register(new CommunicationsSearchTool());
            $registry->register(new CommunicationsGetTool());
            $registry->register(new CommunicationsUnreadSummaryTool());
            $registry->register(new CommunicationsUrgentSummaryTool());
            $registry->register(new CommunicationsSummarizeTool($app->make(ProviderRouter::class)));
            $registry->register(new CommunicationsDraftReplyTool($app->make(CommunicationReplyGenerator::class)));
            $registry->register(new CommunicationsSendReplyTool($app->make(CommunicationSendService::class)));

            // Missions Tools
            $registry->register(new MissionsListTool());
            $registry->register(new MissionsGetTool());
            $registry->register(new MissionsCreateTool($app->make(MissionPlanner::class)));
            $registry->register(new MissionsControlTool($app->make(MissionStateMachine::class)));

            return $registry;
        });

        $this->app->singleton(ProviderRouter::class, function ($app) {
            return new ProviderRouter($app->make(SettingsManager::class));
        });

        $this->app->singleton(ToolInputValidator::class);
        $this->app->singleton(BusinessContextService::class);
        $this->app->singleton(BusinessInsightService::class);
        $this->app->singleton(ActionApprovalService::class);
        $this->app->singleton(MrFoxAuditService::class);
        $this->app->singleton(MrFoxUsageService::class);
        $this->app->singleton(MrFoxAgent::class);
    }

    public function boot(): void {}
}

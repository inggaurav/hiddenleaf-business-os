<?php

namespace App\Providers;

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

<?php

use App\Providers\AppServiceProvider;
use App\Providers\ModuleLoaderServiceProvider;
use App\Providers\MrFoxServiceProvider;
use App\Providers\SettingsServiceProvider;
use NunoMaduro\Collision\Adapters\Laravel\CollisionServiceProvider;

return [
    AppServiceProvider::class,
    ModuleLoaderServiceProvider::class,
    SettingsServiceProvider::class,
    MrFoxServiceProvider::class,
    CollisionServiceProvider::class,
    HiddenLeaf\Hrm\Providers\HrmServiceProvider::class,
    HiddenLeaf\SmsNotifications\Providers\SmsServiceProvider::class,
    HiddenLeaf\CrmDealsKanban\Providers\DealsKanbanServiceProvider::class,
    HiddenLeaf\NoticeBoard\Providers\NoticeBoardServiceProvider::class,
    HiddenLeaf\SuggestionBox\Providers\SuggestionBoxServiceProvider::class,
    HiddenLeaf\SmartAnalytics\Providers\SmartAnalyticsServiceProvider::class,
    HiddenLeaf\AIAdvisor\Providers\AIAdvisorServiceProvider::class,
];

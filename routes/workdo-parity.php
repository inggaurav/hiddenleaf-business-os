<?php

use App\Http\Controllers\ModuleSectionController;
use App\Http\Controllers\SuperAdmin\CompanyController;
use App\Http\Middleware\SuperAdminMiddleware;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth'])->group(function () {
    Route::get('/crm/{section}', [ModuleSectionController::class, 'crm'])
        ->where('section', 'leads|deals|pipelines|webforms|activities|notes')
        ->middleware('module.status:lead')
        ->name('crm.section');

    Route::get('/hrm/{section}', [ModuleSectionController::class, 'hrm'])
        ->where('section', 'employees|branches|departments|designations|shifts|attendance|leave-requests|leave-types|payroll|salary-components|appraisals|documents|holidays')
        ->middleware('module.status:hrm')
        ->name('hrm.section');

    Route::get('/taskly/{section}', [ModuleSectionController::class, 'taskly'])
        ->where('section', 'projects|tasks|milestones|timesheets|issues')
        ->middleware('module.status:taskly')
        ->name('taskly.section');

    Route::middleware(SuperAdminMiddleware::class)->prefix('super-admin')->name('super-admin.')->group(function () {
        Route::get('/companies', [CompanyController::class, 'index'])->name('companies.index');
        Route::patch('/companies/{organization}', [CompanyController::class, 'update'])->name('companies.update');
    });
});

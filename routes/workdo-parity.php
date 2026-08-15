<?php

use App\Http\Controllers\ModuleSectionController;
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
});

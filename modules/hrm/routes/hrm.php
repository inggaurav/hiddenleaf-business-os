<?php

use HiddenLeaf\Hrm\Http\Controllers\HRM\HrmDashboardController;
use HiddenLeaf\Hrm\Http\Controllers\HRM\HrmEmployeeController;
use HiddenLeaf\Hrm\Http\Controllers\HRM\HrmAttendanceController;
use HiddenLeaf\Hrm\Http\Controllers\HRM\HrmLeaveController;
use HiddenLeaf\Hrm\Http\Controllers\HRM\HrmPayrollController;
use HiddenLeaf\Hrm\Http\Controllers\HRM\HrmRecruitmentController;
use HiddenLeaf\Hrm\Http\Controllers\HRM\HrmOnboardingController;
use HiddenLeaf\Hrm\Http\Controllers\HRM\HrmTrainingController;
use HiddenLeaf\Hrm\Http\Controllers\HRM\HrmTimesheetController;
use HiddenLeaf\Hrm\Http\Controllers\HRM\HrmDisciplinaryController;
use HiddenLeaf\Hrm\Http\Controllers\HRM\HrmExitController;
use HiddenLeaf\Hrm\Http\Controllers\HRM\HrmStructureController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'module.status:hrm'])->prefix('hrm')->name('hrm.')->group(function () {

    // ── Dashboard ─────────────────────────────────────────────────────────
    Route::get('/', [HrmDashboardController::class, 'index'])->name('dashboard');

    // ── Employees ─────────────────────────────────────────────────────────
    Route::prefix('employees')->name('employees.')->group(function () {
        Route::get('/', [HrmEmployeeController::class, 'index'])->name('index');
        Route::post('/', [HrmEmployeeController::class, 'store'])->name('store');
        Route::get('/{employee}', [HrmEmployeeController::class, 'show'])->name('show');
        Route::put('/{employee}', [HrmEmployeeController::class, 'update'])->name('update');
        Route::put('/{employee}/salary', [HrmEmployeeController::class, 'setSalary'])->name('salary');
        Route::post('/{employee}/avatar', [HrmEmployeeController::class, 'uploadAvatar'])->name('avatar');
        Route::post('/{employee}/documents', [HrmEmployeeController::class, 'uploadDocument'])->name('document.store');
        Route::get('/{employee}/documents/{document}/download', [HrmEmployeeController::class, 'downloadDocument'])->name('document.download');
        Route::post('/{employee}/terminate', [HrmEmployeeController::class, 'terminate'])->name('terminate');
    });

    // ── Org Structure ─────────────────────────────────────────────────────
    Route::prefix('structure')->name('structure.')->group(function () {
        Route::post('/branches', [HrmStructureController::class, 'storeBranch'])->name('branch.store');
        Route::post('/departments', [HrmStructureController::class, 'storeDepartment'])->name('department.store');
        Route::post('/designations', [HrmStructureController::class, 'storeDesignation'])->name('designation.store');
        Route::post('/shifts', [HrmStructureController::class, 'storeShift'])->name('shift.store');
        Route::post('/holidays', [HrmStructureController::class, 'storeHoliday'])->name('holiday.store');
    });

    // ── Attendance ────────────────────────────────────────────────────────
    Route::prefix('attendance')->name('attendance.')->group(function () {
        Route::get('/', [HrmAttendanceController::class, 'index'])->name('index');
        Route::post('/', [HrmAttendanceController::class, 'store'])->name('store');
        Route::post('/self', [HrmAttendanceController::class, 'selfMark'])->name('self');
        Route::get('/report', [HrmAttendanceController::class, 'report'])->name('report');
    });

    // ── Leave ─────────────────────────────────────────────────────────────
    Route::prefix('leave')->name('leave.')->group(function () {
        Route::get('/', [HrmLeaveController::class, 'index'])->name('index');
        Route::post('/types', [HrmLeaveController::class, 'storeType'])->name('type.store');
        Route::post('/request', [HrmLeaveController::class, 'request'])->name('request');
        Route::post('/{leave}/review', [HrmLeaveController::class, 'review'])->name('review');
        Route::get('/balances', [HrmLeaveController::class, 'balances'])->name('balances');
    });

    // ── Payroll ───────────────────────────────────────────────────────────
    Route::prefix('payroll')->name('payroll.')->group(function () {
        Route::get('/', [HrmPayrollController::class, 'index'])->name('index');
        Route::post('/components', [HrmPayrollController::class, 'storeComponent'])->name('component.store');
        Route::post('/components/assign', [HrmPayrollController::class, 'assignComponent'])->name('component.assign');
        Route::get('/salaries', [HrmPayrollController::class, 'salaries'])->name('salaries');
        // Payroll runs
        Route::post('/runs', [HrmPayrollController::class, 'createRun'])->name('run.store');
        Route::get('/runs/{run}', [HrmPayrollController::class, 'showRun'])->name('run.show');
        Route::post('/runs/{run}/add-employee', [HrmPayrollController::class, 'addEmployee'])->name('run.add-employee');
        Route::post('/runs/{run}/add-all', [HrmPayrollController::class, 'addAllActive'])->name('run.add-all');
        Route::post('/runs/{run}/approve', [HrmPayrollController::class, 'approveRun'])->name('run.approve');
        Route::post('/runs/{run}/pay', [HrmPayrollController::class, 'payRun'])->name('run.pay');
        // Individual payslips
        Route::post('/payslips', [HrmPayrollController::class, 'generatePayslip'])->name('payslip.store');
        Route::post('/payslips/{payslip}/pay', [HrmPayrollController::class, 'payPayslip'])->name('payslip.pay');
    });

    // ── Recruitment ───────────────────────────────────────────────────────
    Route::prefix('recruitment')->name('recruitment.')->group(function () {
        Route::get('/', [HrmRecruitmentController::class, 'index'])->name('index');
        Route::post('/positions', [HrmRecruitmentController::class, 'storePosition'])->name('position.store');
        Route::post('/positions/{position}/close', [HrmRecruitmentController::class, 'closePosition'])->name('position.close');
        Route::post('/candidates', [HrmRecruitmentController::class, 'storeCandidate'])->name('candidate.store');
        Route::get('/candidates/{candidate}', [HrmRecruitmentController::class, 'showCandidate'])->name('candidate.show');
        Route::post('/candidates/{candidate}/stage', [HrmRecruitmentController::class, 'moveStage'])->name('candidate.stage');
        Route::post('/candidates/{candidate}/reject', [HrmRecruitmentController::class, 'rejectCandidate'])->name('candidate.reject');
        Route::post('/candidates/{candidate}/convert', [HrmRecruitmentController::class, 'convertToEmployee'])->name('candidate.convert');
        Route::post('/interviews', [HrmRecruitmentController::class, 'scheduleInterview'])->name('interview.store');
        Route::post('/interviews/{interview}/outcome', [HrmRecruitmentController::class, 'recordOutcome'])->name('interview.outcome');
    });

    // ── Onboarding ────────────────────────────────────────────────────────
    Route::prefix('onboarding')->name('onboarding.')->group(function () {
        Route::get('/', [HrmOnboardingController::class, 'index'])->name('index');
        Route::post('/templates', [HrmOnboardingController::class, 'storeTemplate'])->name('template.store');
        Route::post('/templates/{template}/tasks', [HrmOnboardingController::class, 'storeTask'])->name('template.task');
        Route::post('/start', [HrmOnboardingController::class, 'startOnboarding'])->name('start');
        Route::get('/{onboarding}', [HrmOnboardingController::class, 'show'])->name('show');
        Route::post('/{onboarding}/tasks/{task}/complete', [HrmOnboardingController::class, 'completeTask'])->name('task.complete');
    });

    // ── Training ──────────────────────────────────────────────────────────
    Route::prefix('training')->name('training.')->group(function () {
        Route::get('/', [HrmTrainingController::class, 'index'])->name('index');
        Route::post('/', [HrmTrainingController::class, 'store'])->name('store');
        Route::get('/{program}', [HrmTrainingController::class, 'show'])->name('show');
        Route::post('/{program}/enroll', [HrmTrainingController::class, 'enroll'])->name('enroll');
        Route::post('/{program}/enrollments/{enrollment}/outcome', [HrmTrainingController::class, 'recordOutcome'])->name('outcome');
    });

    // ── Timesheets ────────────────────────────────────────────────────────
    Route::prefix('timesheets')->name('timesheets.')->group(function () {
        Route::get('/', [HrmTimesheetController::class, 'index'])->name('index');
        Route::post('/', [HrmTimesheetController::class, 'store'])->name('store');
        Route::get('/team', [HrmTimesheetController::class, 'teamIndex'])->name('team');
        Route::post('/{timesheet}/approve', [HrmTimesheetController::class, 'approve'])->name('approve');
        Route::post('/{timesheet}/reject', [HrmTimesheetController::class, 'reject'])->name('reject');
    });

    // ── Appraisals ────────────────────────────────────────────────────────
    Route::prefix('appraisals')->name('appraisals.')->group(function () {
        Route::get('/', [HrmEmployeeController::class, 'appraisals'])->name('index');
        Route::post('/', [HrmEmployeeController::class, 'storeAppraisal'])->name('store');
    });

    // ── Disciplinary ──────────────────────────────────────────────────────
    Route::prefix('disciplinary')->name('disciplinary.')->group(function () {
        Route::get('/', [HrmDisciplinaryController::class, 'index'])->name('index');
        Route::post('/', [HrmDisciplinaryController::class, 'store'])->name('store');
        Route::get('/{case}', [HrmDisciplinaryController::class, 'show'])->name('show');
        Route::post('/{case}/action', [HrmDisciplinaryController::class, 'recordAction'])->name('action');
        Route::post('/{case}/close', [HrmDisciplinaryController::class, 'close'])->name('close');
        Route::post('/{case}/reopen', [HrmDisciplinaryController::class, 'reopen'])->name('reopen');
    });

    // ── Exit Management ───────────────────────────────────────────────────
    Route::prefix('exit')->name('exit.')->group(function () {
        Route::get('/', [HrmExitController::class, 'index'])->name('index');
        Route::post('/initiate', [HrmExitController::class, 'initiate'])->name('initiate');
        Route::post('/{employee}/interview', [HrmExitController::class, 'conductInterview'])->name('interview');
        Route::get('/{employee}/clearance', [HrmExitController::class, 'clearanceIndex'])->name('clearance.index');
        Route::post('/{employee}/clearance', [HrmExitController::class, 'addClearanceItems'])->name('clearance.store');
        Route::post('/{employee}/clearance/{clearance}/clear', [HrmExitController::class, 'clearItem'])->name('clearance.clear');
        Route::post('/{employee}/complete', [HrmExitController::class, 'completeExit'])->name('complete');
    });

    // ── Lifecycle Events (from existing HrmController pattern) ────────────
    Route::get('/lifecycle/{kind}', [\App\Http\Controllers\HrmController::class, 'lifecycle'])->name('lifecycle');
    Route::post('/lifecycle/{kind}/types', [\App\Http\Controllers\HrmController::class, 'storeEventType'])->name('lifecycle.type.store');
    Route::post('/lifecycle/{kind}/events', [\App\Http\Controllers\HrmController::class, 'storeEvent'])->name('lifecycle.event.store');
    Route::post('/lifecycle/events/{event}/review', [\App\Http\Controllers\HrmController::class, 'reviewEvent'])->name('lifecycle.event.review');

    // ── Communications (from existing HrmController pattern) ──────────────
    Route::get('/communications', [\App\Http\Controllers\HrmController::class, 'communications'])->name('communications');
    Route::post('/announcements', [\App\Http\Controllers\HrmController::class, 'storeAnnouncement'])->name('announcement.store');
    Route::post('/policies', [\App\Http\Controllers\HrmController::class, 'storePolicy'])->name('policy.store');
    Route::post('/policies/{policy}/acknowledge', [\App\Http\Controllers\HrmController::class, 'acknowledgePolicy'])->name('policy.acknowledge');
});

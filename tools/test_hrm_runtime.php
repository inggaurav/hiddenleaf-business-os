<?php

require_once __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Organization;
use App\Models\User;
use App\Models\Workspace;
use Carbon\Carbon;
use HiddenLeaf\Hrm\Domain\HRM\DisciplinaryService;
use HiddenLeaf\Hrm\Domain\HRM\ExitService;
use HiddenLeaf\Hrm\Domain\HRM\HrmDashboardService;
use HiddenLeaf\Hrm\Domain\HRM\OnboardingService;
use HiddenLeaf\Hrm\Domain\HRM\PayrollRunService;
use HiddenLeaf\Hrm\Domain\HRM\RecruitmentService;
use HiddenLeaf\Hrm\Domain\HRM\TimesheetService;
use HiddenLeaf\Hrm\Domain\HRM\TrainingService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

echo "=== Running HRM Domain Services & Database Integration Test ===\n";

$org = Organization::first();
$ws = Workspace::where('organization_id', $org->id)->first();
$user = User::where('workspace_id', $ws->id)->first() ?? User::first();

if (!$org || !$ws || !$user) {
    echo "ERROR: Missing test organization/workspace/user.\n";
    exit(1);
}

Auth::login($user);
echo "Operating as User {$user->id} ({$user->email}) in Workspace {$ws->id} (Org {$org->id})\n";

DB::beginTransaction();

try {
    // 1. Test RecruitmentService
    echo "\n1. Testing RecruitmentService...\n";
    $recruitment = app(RecruitmentService::class);
    $pos = $recruitment->createPosition($ws, [
        'title' => 'Senior Systems Architect',
        'headcount' => 2,
        'employment_type' => 'full_time',
        'description' => 'Architecting high-scale enterprise systems',
    ], $user);
    echo "   Created position #{$pos->id}: {$pos->title}\n";

    $candidate = $recruitment->addCandidate($ws, [
        'job_position_id' => $pos->id,
        'name' => 'Jane Doe',
        'email' => 'jane.doe.' . uniqid() . '@example.com',
        'phone' => '+15550199283',
        'source' => 'linkedin',
        'expected_salary' => 120000,
    ], null, $user);
    echo "   Created candidate #{$candidate->id}: {$candidate->name} (stage: {$candidate->stage})\n";

    $recruitment->moveStage($candidate, 'screening', 'Initial screening passed', $user);
    $recruitment->moveStage($candidate, 'interview', 'Scheduled for interview', $user);
    echo "   Candidate moved to stage: {$candidate->fresh()->stage}\n";

    $interview = $recruitment->scheduleInterview($candidate, [
        'round_name' => 'Technical Deep-Dive',
        'interview_type' => 'online',
        'scheduled_at' => now()->addDays(2),
        'duration_minutes' => 60,
    ], $user);
    echo "   Scheduled interview #{$interview->id} ({$interview->round_name})\n";

    $recruitment->recordInterviewOutcome($interview, 5, 'proceed', 'Exceptional technical depth and architecture skills.', $user);
    echo "   Recorded interview outcome: decision = {$interview->fresh()->decision}\n";

    $recruitment->moveStage($candidate, 'assessment', 'Passed coding assessment', $user);
    $recruitment->moveStage($candidate, 'offer', 'Offer extended', $user);
    $recruitment->moveStage($candidate, 'hired', 'Offer accepted', $user);
    echo "   Candidate moved to: {$candidate->fresh()->stage}\n";

    $employee = $recruitment->convertToEmployee($candidate->fresh(), [
        'employee_number' => 'EMP-' . strtoupper(bin2hex(random_bytes(3))),
        'joined_at' => Carbon::today(),
        'basic_salary' => 120000,
    ], $user);
    echo "   Successfully converted candidate to Employee #{$employee->id} ({$employee->employee_number})\n";

    // 2. Test OnboardingService
    echo "\n2. Testing OnboardingService...\n";
    $onboardingService = app(OnboardingService::class);
    $template = $onboardingService->createTemplate($ws, [
        'title' => 'Engineering Onboarding Template',
        'description' => 'Default engineering onboarding checklist',
    ], $user);
    $task1 = $onboardingService->addTaskToTemplate($template, [
        'title' => 'Setup development environment',
        'category' => 'technical',
        'due_offset_days' => 2,
    ]);
    $task2 = $onboardingService->addTaskToTemplate($template, [
        'title' => 'Sign NDA and Security Policy',
        'category' => 'compliance',
        'due_offset_days' => 1,
    ]);
    echo "   Created onboarding template #{$template->id} with 2 tasks\n";

    $empOnboarding = $onboardingService->startOnboarding($employee, $template->id, $user);
    echo "   Started onboarding #{$empOnboarding->id} for employee (tasks: " . $empOnboarding->tasks()->count() . ")\n";

    $firstTask = $empOnboarding->tasks()->first();
    $onboardingService->completeTask($firstTask, $user, 'Done successfully.');
    echo "   Completed first onboarding task #{$firstTask->id}\n";

    // 3. Test TrainingService
    echo "\n3. Testing TrainingService...\n";
    $trainingService = app(TrainingService::class);
    $program = $trainingService->createProgram($ws, [
        'title' => 'Cloud Security & Compliance Masterclass',
        'category' => 'technical',
        'mode' => 'online',
        'starts_on' => now()->addDays(5)->toDateString(),
        'ends_on' => now()->addDays(6)->toDateString(),
        'duration_hours' => 16,
        'cost_per_head' => 500,
        'max_participants' => 10,
    ], $user);
    echo "   Created training program #{$program->id}: {$program->title}\n";

    $enrollment = $trainingService->enroll($program, $employee, $user);
    echo "   Enrolled employee in program (enrollment #{$enrollment->id})\n";

    $trainingService->recordOutcome($enrollment, 95, true, null, $user);
    echo "   Recorded outcome: score 95, passed = true\n";

    // 4. Test TimesheetService
    echo "\n4. Testing TimesheetService...\n";
    $timesheetService = app(TimesheetService::class);
    $timesheet = $timesheetService->submit($employee, [
        'work_date' => now()->toDateString(),
        'hours' => 8,
        'project_code' => 'CORE-2026',
        'description' => 'Architecture refactoring and module plugin system integration',
    ], $user);
    echo "   Submitted timesheet #{$timesheet->id} for {$timesheet->hours} hrs (status: {$timesheet->status})\n";

    $timesheetService->approve($timesheet, $user);
    echo "   Approved timesheet #{$timesheet->id} (status: {$timesheet->fresh()->status})\n";

    // 5. Test DisciplinaryService
    echo "\n5. Testing DisciplinaryService...\n";
    $discService = app(DisciplinaryService::class);
    $discCase = $discService->openCase($employee, [
        'type' => 'policy_violation',
        'severity' => 'minor',
        'incident_date' => now()->subDay()->toDateString(),
        'incident_description' => 'Late submission of security attestation',
        'handled_by' => $user->id,
    ], $user);
    echo "   Created disciplinary case #{$discCase->id} (case number: {$discCase->case_number})\n";

    $discService->recordAction($discCase, 'written_warning', 'Attestation was resubmitted within 24h. Closed with verbal reminder.', $user);
    $discService->closeCase($discCase, $user);
    echo "   Recorded action and closed case (status: {$discCase->fresh()->status})\n";

    // 6. Test PayrollRunService
    echo "\n6. Testing PayrollRunService...\n";
    $payrollRunService = app(PayrollRunService::class);
    $periodStart = now()->startOfMonth()->toDateString();
    $periodEnd = now()->endOfMonth()->toDateString();
    $run = $payrollRunService->createRun($ws, $periodStart, $periodEnd, $user);
    echo "   Created payroll run #{$run->id} ({$run->run_number})\n";

    $payslip = $payrollRunService->addEmployee($run, $employee, $user);
    echo "   Added employee to payroll run. Run totals gross: {$run->fresh()->total_gross}, net: {$run->fresh()->total_net}\n";

    $payrollRunService->approve($run, $user);
    echo "   Approved payroll run (status: {$run->fresh()->status})\n";

    // 7. Test ExitService
    echo "\n7. Testing ExitService...\n";
    $exitService = app(ExitService::class);
    $exitService->initiateExit($employee, 'career_growth', now()->addDays(30), $user);
    echo "   Initiated exit for employee\n";

    $interview = $exitService->conductExitInterview($employee, [
        'interview_date' => now()->toDateString(),
        'exit_reason' => 'Relocating to another city',
        'would_return' => 'yes',
        'overall_rating' => 5,
    ], $user);
    echo "   Conducted exit interview #{$interview->id}\n";

    $items = $exitService->addClearanceItems($employee, [
        ['department' => 'IT', 'clearance_item' => 'Laptop & Hardware Return'],
        ['department' => 'Finance', 'clearance_item' => 'Corporate Credit Card Settlement'],
    ], $user);
    echo "   Added " . count($items) . " clearance items\n";

    foreach ($items as $item) {
        $exitService->clearItem($item, $user, 'Verified and returned.');
    }
    echo "   Cleared all clearance items. isFullyCleared = " . ($exitService->isFullyCleared($employee) ? 'YES' : 'NO') . "\n";

    $exitService->completeExit($employee, $user);
    echo "   Completed exit! Employee status: {$employee->fresh()->status}\n";

    // 8. Test HrmDashboardService
    echo "\n8. Testing HrmDashboardService metrics...\n";
    $dashService = app(HrmDashboardService::class);
    $metrics = $dashService->getExtendedMetrics($ws);
    echo "   Dashboard metrics retrieved successfully:\n";
    echo "     - Total employees: " . ($metrics['total_employees'] ?? 'N/A') . "\n";
    echo "     - Active candidates: " . ($metrics['hrm_extended']['candidates_active'] ?? 'N/A') . "\n";
    echo "     - Open disciplinary: " . ($metrics['hrm_extended']['disciplinary_open'] ?? 'N/A') . "\n";

    DB::rollBack(); // Keep database clean after verification
    echo "\n=== ALL 8 HRM DOMAIN SERVICES VERIFIED SUCCESSFULLY! ===\n";

} catch (\Throwable $e) {
    DB::rollBack();
    echo "\nFAILURE IN HRM TEST: " . $e->getMessage() . "\n";
    echo $e->getFile() . ":" . $e->getLine() . "\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}

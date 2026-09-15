<?php

require_once __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Domain\MrFox\Tools\MrFoxToolRegistry;

$registry = $app->make(MrFoxToolRegistry::class);

$registered = $registry->all();
echo "Total tools registered: " . count($registered) . "\n";
echo "Registered tool names:\n";
$expected = [
    'hr.attendance.summary',
    'hr.employee.summary',
    'hr.leave.pending',
    'hr_recruitment_pipeline',
    'hr_timesheet_summary',
    'hr_training_summary',
    'hr_exit_status',
    'hr_payroll_run_summary',
];

$allPass = true;
foreach ($expected as $exp) {
    if (!isset($registered[$exp])) {
        echo "Missing tool: {$exp}\n";
        $allPass = false;
    }
}

if ($allPass) {
    echo "\nSUCCESS: All 8 HRM MrFox tools verified in registry!\n";
} else {
    echo "\nFAILURE: Some tools are missing!\n";
    exit(1);
}

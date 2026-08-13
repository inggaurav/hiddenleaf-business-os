<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$records = require $root.'/docs/reference/workdo-parity-source.php';
$routeSource = file_get_contents($root.'/routes/web.php').file_get_contents($root.'/routes/api.php');
$migrationSource = implode("\n", array_map('file_get_contents', glob($root.'/database/migrations/*.php')));

foreach ($records as &$record) {
    $checks = [
        is_file($root.'/'.$record['hiddenleaf_file']),
        str_contains($routeSource, $record['hiddenleaf_route']),
        is_file($root.'/'.$record['hiddenleaf_tests'][0]),
    ];

    if ($record['hiddenleaf_screen']) {
        $checks[] = is_file($root.'/'.$record['hiddenleaf_screen']);
    }

    foreach ($record['hiddenleaf_models'] as $model) {
        $checks[] = is_file($root.'/app/Models/'.$model.'.php')
            || str_contains($record['hiddenleaf_file'], $model)
            || str_contains($migrationSource, $model);
    }
    foreach ($record['hiddenleaf_tables'] as $table) {
        $checks[] = str_contains($migrationSource, "'{$table}'") || str_contains($migrationSource, '"'.$table.'"');
    }

    $record['status'] = in_array(false, $checks, true) ? 'IMPLEMENTED_UNVERIFIED' : 'VERIFIED';
}
unset($record);

$statuses = ['MISSING', 'PARTIAL', 'IMPLEMENTED_UNVERIFIED', 'VERIFIED', 'DEFERRED_SEPARATELY_SOLD_ADDON'];
$totals = array_fill_keys($statuses, 0);
foreach ($records as $record) {
    $totals[$record['status']]++;
}

$registry = [
    'schema_version' => 1,
    'reference_repository' => 'https://github.com/inggaurav/workdo-dash-reference',
    'reference_sha' => '0b996a0050abcdffa2770a82fbb9261eeb2805bd',
    'generated_at' => gmdate(DATE_ATOM),
    'totals' => ['all' => count($records), ...$totals],
    'capabilities' => $records,
];

$json = json_encode($registry, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR).PHP_EOL;
$target = $root.'/docs/reference/workdo-parity-registry.json';

if (in_array('--check', $argv, true)) {
    if (! is_file($target)) {
        fwrite(STDERR, "Parity registry is missing.\n");
        exit(1);
    }
    $existing = json_decode(file_get_contents($target), true, flags: JSON_THROW_ON_ERROR);
    unset($registry['generated_at'], $existing['generated_at']);
    if ($existing !== $registry) {
        fwrite(STDERR, "Parity registry is stale or its evidence does not validate.\n");
        exit(1);
    }
    if (($totals['MISSING'] + $totals['PARTIAL'] + $totals['IMPLEMENTED_UNVERIFIED']) > 0) {
        fwrite(STDERR, "Parity registry contains unresolved core capabilities.\n");
        exit(1);
    }
    fwrite(STDOUT, 'Parity registry verified: '.count($records)." capabilities.\n");
    exit(0);
}

file_put_contents($target, $json);
fwrite(STDOUT, "Wrote {$target}\n");

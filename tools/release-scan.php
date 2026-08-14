<?php

declare(strict_types=1);

use Symfony\Component\Process\Process;

require dirname(__DIR__).'/vendor/autoload.php';

$root = dirname(__DIR__);
$process = new Process(['git', 'ls-files', '-z'], $root);
$process->mustRun();
$files = array_filter(explode("\0", $process->getOutput()));
$findings = [];

foreach ($files as $relative) {
    if ($relative === 'tools/release-scan.php') {
        continue;
    }

    $path = $root.'/'.$relative;
    if (! is_file($path)) {
        continue;
    }
    $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    if (! in_array($extension, ['php', 'js', 'jsx', 'ts', 'tsx', 'env'], true) && basename($path) !== '.env.example') {
        continue;
    }
    $source = file_get_contents($path);
    $patterns = [];
    if ($extension === 'php') {
        $patterns += [
            'unfinished marker' => '/\b(?:TODO|FIXME|DUMMY)\b|not implemented/',
            'debug function' => '/(?<![A-Za-z_])(?:dd|dump|var_dump)\s*\(/',
        ];
    }
    if (in_array($extension, ['js', 'jsx', 'ts', 'tsx'], true)) {
        $patterns += [
            'console debug' => '/\bconsole\.log\s*\(/',
            'dummy credential' => '/DUMMY[-_ ](?:LICENSE|TOKEN|KEY)/i',
        ];
    }
    if (str_starts_with($relative, 'tests/')) {
        $patterns['middleware bypass'] = '/withoutMiddleware\s*\(/';
    }
    if (! str_starts_with($relative, 'tests/Fixtures/')) {
        $patterns['private key material'] = '/-----BEGIN (?:RSA |EC |OPENSSH )?PRIVATE KEY-----/';
    }
    foreach ($patterns as $label => $pattern) {
        if (preg_match_all($pattern, $source, $matches, PREG_OFFSET_CAPTURE)) {
            foreach ($matches[0] as [$match, $offset]) {
                $line = substr_count(substr($source, 0, $offset), "\n") + 1;
                $findings[] = "{$relative}:{$line} {$label} ({$match})";
            }
        }
    }
}

if ($findings !== []) {
    fwrite(STDERR, "Release scan failed:\n- ".implode("\n- ", $findings)."\n");
    exit(1);
}

echo 'Release scan passed: no production stubs, debug calls, middleware bypasses, dummy credentials, or shipped private keys.'.PHP_EOL;

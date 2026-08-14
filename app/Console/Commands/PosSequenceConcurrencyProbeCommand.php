<?php

namespace App\Console\Commands;

use App\Domain\POS\PosNumberService;
use App\Domain\POS\ReturnNumberService;
use Illuminate\Console\Command;
use Throwable;

class PosSequenceConcurrencyProbeCommand extends Command
{
    protected $signature = 'pos:sequence-concurrency-probe {kind} {workspace} {readyFile} {startFile} {resultFile}';
    protected $description = 'Internal worker for real POS/return sequence concurrency tests';

    public function handle(PosNumberService $posNumbers, ReturnNumberService $returnNumbers): int
    {
        $ready = (string) $this->argument('readyFile');
        $start = (string) $this->argument('startFile');
        $result = (string) $this->argument('resultFile');
        file_put_contents($ready, 'ready');

        $deadline = microtime(true) + 15;
        while (! file_exists($start) && microtime(true) < $deadline) usleep(10_000);
        if (! file_exists($start)) {
            file_put_contents($result, json_encode(['ok' => false, 'error' => 'barrier_timeout']));
            return self::FAILURE;
        }

        try {
            $kind = (string) $this->argument('kind');
            $workspace = (int) $this->argument('workspace');
            $number = match ($kind) {
                'pos' => $posNumbers->next($workspace),
                'return' => $returnNumbers->next($workspace),
                default => throw new \InvalidArgumentException('kind must be pos or return'),
            };
            file_put_contents($result, json_encode(['ok' => true, 'number' => $number]));
            return self::SUCCESS;
        } catch (Throwable $e) {
            file_put_contents($result, json_encode(['ok' => false, 'error' => $e->getMessage(), 'class' => $e::class]));
            return self::FAILURE;
        }
    }
}

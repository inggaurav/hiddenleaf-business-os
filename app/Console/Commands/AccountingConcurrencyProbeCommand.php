<?php

namespace App\Console\Commands;

use App\Domain\Accounting\BankTransferNumberService;
use App\Domain\Accounting\JournalNumberService;
use App\Domain\Accounting\LedgerService;
use App\Models\User;
use Illuminate\Console\Command;
use Throwable;

class AccountingConcurrencyProbeCommand extends Command
{
    protected $signature = 'accounting:concurrency-probe
        {probeType}
        {organization}
        {workspace}
        {user}
        {accountFrom}
        {accountTo}
        {amount}
        {readyFile}
        {startFile}
        {resultFile}';

    protected $description = 'Internal worker for real PostgreSQL accounting concurrency regression tests';

    public function handle(
        LedgerService $ledger,
        JournalNumberService $journalNumbers,
        BankTransferNumberService $transferNumbers
    ): int {
        $ready = (string) $this->argument('readyFile');
        $start = (string) $this->argument('startFile');
        $result = (string) $this->argument('resultFile');
        file_put_contents($ready, 'ready');

        $deadline = microtime(true) + 15;
        while (! file_exists($start) && microtime(true) < $deadline) {
            usleep(10_000);
        }
        if (! file_exists($start)) {
            file_put_contents($result, json_encode(['ok' => false, 'error' => 'barrier_timeout']));

            return self::FAILURE;
        }

        try {
            $probeType = (string) $this->argument('probeType');
            $orgId = (int) $this->argument('organization');
            $wsId = (int) $this->argument('workspace');
            $userId = (int) $this->argument('user');
            $actor = User::findOrFail($userId);
            $fromAcc = (int) $this->argument('accountFrom');
            $toAcc = (int) $this->argument('accountTo');
            $amount = (string) $this->argument('amount');

            if ($probeType === 'journal') {
                $entry = $ledger->createEntry($orgId, $wsId, [
                    'entry_date' => now()->toDateString(),
                    'description' => 'Concurrent journal probe',
                    'lines' => [
                        ['account_id' => $fromAcc, 'debit' => $amount, 'credit' => 0],
                        ['account_id' => $toAcc, 'debit' => 0, 'credit' => $amount],
                    ],
                ], $actor);

                file_put_contents($result, json_encode([
                    'ok' => true,
                    'entry_id' => $entry->id,
                    'entry_number' => $entry->entry_number,
                ]));

                return self::SUCCESS;
            }

            if ($probeType === 'transfer_sequence') {
                $number = $transferNumbers->next($wsId);

                file_put_contents($result, json_encode([
                    'ok' => true,
                    'transfer_number' => $number,
                ]));

                return self::SUCCESS;
            }

            file_put_contents($result, json_encode(['ok' => false, 'error' => 'unknown_probe_type']));

            return self::FAILURE;
        } catch (Throwable $e) {
            file_put_contents($result, json_encode([
                'ok' => false,
                'error' => $e->getMessage(),
                'class' => get_class($e),
            ]));

            return self::FAILURE;
        }
    }
}

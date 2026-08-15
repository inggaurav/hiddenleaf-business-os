<?php

namespace App\Domain\Accounting;

use App\Models\AccountCreditNote;
use App\Models\AccountDebitNote;
use App\Models\JournalEntry;
use App\Models\LedgerAccount;
use App\Models\User;
use App\Models\Workspace;
use HiddenLeaf\Kernel\Services\AuditLogger;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class LedgerService
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly ?JournalNumberService $journalNumberService = null
    ) {}

    public function createEntry(int $organizationId, int $workspaceId, array $data, User $actor): JournalEntry
    {
        $this->assertBalanced($data['lines']);
        $accountIds = collect($data['lines'])->pluck('account_id')->unique();
        $accounts = LedgerAccount::forWorkspace($organizationId, $workspaceId)->whereIn('id', $accountIds)->count();
        if ($accounts !== $accountIds->count()) {
            throw new RuntimeException('Every journal line account must belong to the active workspace.');
        }

        $numberService = $this->journalNumberService ?? app(JournalNumberService::class);

        return DB::transaction(function () use ($organizationId, $workspaceId, $data, $actor, $numberService) {
            Workspace::whereKey($workspaceId)->lockForUpdate()->firstOrFail();
            $number = $numberService->next($workspaceId);
            $entry = JournalEntry::create([
                'organization_id' => $organizationId,
                'workspace_id' => $workspaceId,
                'entry_number' => $number,
                'entry_date' => $data['entry_date'],
                'reference' => $data['reference'] ?? null,
                'description' => $data['description'] ?? null,
                'status' => 'draft',
                'created_by' => $actor->id,
            ]);
            foreach ($data['lines'] as $line) {
                $entry->lines()->create([
                    'ledger_account_id' => $line['account_id'],
                    'description' => $line['description'] ?? null,
                    'debit' => $line['debit'] ?? 0,
                    'credit' => $line['credit'] ?? 0,
                ]);
            }

            return $entry->load('lines.account');
        });
    }

    public function post(JournalEntry $entry, User $actor): JournalEntry
    {
        return DB::transaction(function () use ($entry, $actor) {
            $locked = JournalEntry::whereKey($entry->id)->lockForUpdate()->firstOrFail();
            if ($locked->status !== 'draft') {
                throw new RuntimeException('Only draft journal entries can be posted.');
            }
            $this->assertBalanced($locked->lines()->get()->map(fn ($line) => ['debit' => $line->debit, 'credit' => $line->credit])->all());
            $locked->update(['status' => 'posted', 'posted_at' => now(), 'posted_by' => $actor->id]);

            // Legacy direct note creation posts the journal after the note row is
            // created. Persist that relationship centrally so note history always
            // links back to its immutable ledger evidence.
            if (preg_match('/^CN-(\d+)$/', (string) $locked->reference, $match)) {
                AccountCreditNote::whereKey((int) $match[1])->whereNull('journal_entry_id')->update(['journal_entry_id' => $locked->id]);
            }
            if (preg_match('/^DN-(\d+)$/', (string) $locked->reference, $match)) {
                AccountDebitNote::whereKey((int) $match[1])->whereNull('journal_entry_id')->update(['journal_entry_id' => $locked->id]);
            }

            $this->audit->log($actor->id, $locked->organization_id, $locked->workspace_id, 'journal.posted', 'journal_entry', (string) $locked->id, ['entry_number' => $locked->entry_number], critical: true);

            return $locked->refresh();
        });
    }

    public function reverse(JournalEntry $entry, User $actor, ?string $reference = null, ?string $description = null): JournalEntry
    {
        return DB::transaction(function () use ($entry, $actor, $reference, $description) {
            $locked = JournalEntry::with('lines')->whereKey($entry->id)->lockForUpdate()->firstOrFail();
            if ($locked->status !== 'posted') {
                throw new RuntimeException('Only posted journal entries can be reversed.');
            }

            $reversal = $this->createEntry($locked->organization_id, $locked->workspace_id, [
                'entry_date' => now()->toDateString(),
                'reference' => $reference ?? 'REV-'.$locked->entry_number,
                'description' => $description ?? 'Reversal of '.$locked->entry_number,
                'lines' => $locked->lines->map(fn ($line) => [
                    'account_id' => $line->ledger_account_id,
                    'description' => 'Reversal: '.($line->description ?? $locked->description ?? ''),
                    'debit' => $line->credit,
                    'credit' => $line->debit,
                ])->all(),
            ], $actor);

            $this->post($reversal, $actor);
            $this->audit->log($actor->id, $locked->organization_id, $locked->workspace_id, 'journal.reversed', 'journal_entry', (string) $locked->id, ['original' => $locked->entry_number, 'reversal' => $reversal->entry_number], critical: true);

            return $reversal->refresh();
        });
    }

    public function balances(int $organizationId, int $workspaceId, ?string $from = null, ?string $to = null): Collection
    {
        return LedgerAccount::forWorkspace($organizationId, $workspaceId)
            ->with('type')
            ->withSum(['journalLines as debit_total' => fn ($query) => $query->whereHas('entry', fn ($entry) => $entry->where('status', 'posted')->when($from, fn ($q) => $q->whereDate('entry_date', '>=', $from))->when($to, fn ($q) => $q->whereDate('entry_date', '<=', $to)))], 'debit')
            ->withSum(['journalLines as credit_total' => fn ($query) => $query->whereHas('entry', fn ($entry) => $entry->where('status', 'posted')->when($from, fn ($q) => $q->whereDate('entry_date', '>=', $from))->when($to, fn ($q) => $q->whereDate('entry_date', '<=', $to)))], 'credit')
            ->orderBy('code')->get()->map(function ($account) {
                $debit = Money::of($account->debit_total ?? 0);
                $credit = Money::of($account->credit_total ?? 0);
                $balance = $account->type->normal_balance === 'credit' ? $credit->subtract($debit) : $debit->subtract($credit);
                $account->balance = $balance->toStorageString();

                return $account;
            });
    }

    private function assertBalanced(array $lines): void
    {
        if (count($lines) < 2) {
            throw new RuntimeException('A journal entry requires at least two lines.');
        }
        $debits = Money::zero();
        $credits = Money::zero();
        foreach ($lines as $line) {
            $debits = $debits->add(Money::of($line['debit'] ?? 0));
            $credits = $credits->add(Money::of($line['credit'] ?? 0));
            if (Money::of($line['debit'] ?? 0)->isPositive() && Money::of($line['credit'] ?? 0)->isPositive()) {
                throw new RuntimeException('A journal line cannot contain both a debit and a credit.');
            }
        }
        if ($debits->isZero() || ! $debits->equals($credits)) {
            throw new RuntimeException('Journal entry debits and credits must balance.');
        }
    }
}

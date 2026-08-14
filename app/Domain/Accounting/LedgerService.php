<?php

namespace App\Domain\Accounting;

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
    public function __construct(private readonly AuditLogger $audit) {}

    public function createEntry(int $organizationId, int $workspaceId, array $data, User $actor): JournalEntry
    {
        $this->assertBalanced($data['lines']);
        $accountIds = collect($data['lines'])->pluck('account_id')->unique();
        $accounts = LedgerAccount::forWorkspace($organizationId, $workspaceId)->whereIn('id', $accountIds)->count();
        if ($accounts !== $accountIds->count()) {
            throw new RuntimeException('Every journal line account must belong to the active workspace.');
        }

        return DB::transaction(function () use ($organizationId, $workspaceId, $data, $actor) {
            Workspace::whereKey($workspaceId)->lockForUpdate()->firstOrFail();
            $number = 'JE-'.now()->format('Ymd').'-'.str_pad((string) (JournalEntry::where('workspace_id', $workspaceId)->count() + 1), 5, '0', STR_PAD_LEFT);
            $entry = JournalEntry::create([
                'organization_id' => $organizationId, 'workspace_id' => $workspaceId, 'entry_number' => $number,
                'entry_date' => $data['entry_date'], 'reference' => $data['reference'] ?? null,
                'description' => $data['description'] ?? null, 'status' => 'draft', 'created_by' => $actor->id,
            ]);
            foreach ($data['lines'] as $line) {
                $entry->lines()->create([
                    'ledger_account_id' => $line['account_id'], 'description' => $line['description'] ?? null,
                    'debit' => $line['debit'] ?? 0, 'credit' => $line['credit'] ?? 0,
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
            $this->audit->log($actor->id, $locked->organization_id, $locked->workspace_id, 'journal.posted', 'journal_entry', (string) $locked->id, ['entry_number' => $locked->entry_number], critical: true);

            return $locked->refresh();
        });
    }

    public function balances(int $organizationId, int $workspaceId, ?string $from = null, ?string $to = null): Collection
    {
        return LedgerAccount::forWorkspace($organizationId, $workspaceId)
            ->with('type')
            ->withSum(['journalLines as debit_total' => fn ($query) => $query->whereHas('entry', fn ($entry) => $entry->where('status', 'posted')->when($from, fn ($q) => $q->whereDate('entry_date', '>=', $from))->when($to, fn ($q) => $q->whereDate('entry_date', '<=', $to)))], 'debit')
            ->withSum(['journalLines as credit_total' => fn ($query) => $query->whereHas('entry', fn ($entry) => $entry->where('status', 'posted')->when($from, fn ($q) => $q->whereDate('entry_date', '>=', $from))->when($to, fn ($q) => $q->whereDate('entry_date', '<=', $to)))], 'credit')
            ->orderBy('code')->get()->map(function ($account) {
                $debit = (float) ($account->debit_total ?? 0);
                $credit = (float) ($account->credit_total ?? 0);
                $account->balance = $account->type->normal_balance === 'credit' ? $credit - $debit : $debit - $credit;

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

<?php

namespace App\Domain\Accounting;

use App\Models\JournalEntry;
use App\Models\JournalNumber;
use Illuminate\Support\Facades\DB;

class JournalNumberService
{
    /**
     * Concurrency-safe workspace/day journal sequence.
     * The DB unique(workspace_id,date) constraint closes the first-row race.
     */
    public function next(int $workspaceId): string
    {
        return DB::transaction(function () use ($workspaceId) {
            $date = now()->format('Ymd');

            DB::table('journal_numbers')->insertOrIgnore([
                'workspace_id' => $workspaceId,
                'date' => $date,
                'last_number' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $record = JournalNumber::query()
                ->where('workspace_id', $workspaceId)
                ->where('date', $date)
                ->lockForUpdate()
                ->firstOrFail();

            do {
                $record->last_number = ((int) $record->last_number) + 1;
                $record->save();
                $number = sprintf('JE-%s-%05d', $date, $record->last_number);
            } while (JournalEntry::where('workspace_id', $workspaceId)->where('entry_number', $number)->exists());

            return $number;
        });
    }
}

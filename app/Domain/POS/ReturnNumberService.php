<?php

namespace App\Domain\POS;

use App\Models\POS\PosReturn;
use App\Models\POS\ReturnNumber;
use Illuminate\Support\Facades\DB;

class ReturnNumberService
{
    /**
     * Concurrency-safe workspace/day return sequence.
     * The DB unique(workspace_id,date) constraint closes the first-row race.
     */
    public function next(int $workspaceId): string
    {
        return DB::transaction(function () use ($workspaceId) {
            $date = now()->format('Ymd');

            DB::table('pos_return_numbers')->insertOrIgnore([
                'workspace_id' => $workspaceId,
                'date' => $date,
                'last_number' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $record = ReturnNumber::query()
                ->where('workspace_id', $workspaceId)
                ->where('date', $date)
                ->lockForUpdate()
                ->firstOrFail();

            do {
                $record->last_number = ((int) $record->last_number) + 1;
                $record->save();
                $number = sprintf('RET-%s-%05d', $date, $record->last_number);
            } while (PosReturn::where('workspace_id', $workspaceId)->where('return_number', $number)->exists());

            return $number;
        });
    }
}

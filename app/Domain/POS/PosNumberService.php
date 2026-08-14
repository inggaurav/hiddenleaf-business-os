<?php

namespace App\Domain\POS;

use App\Models\POS\PosNumber;
use App\Models\POS\PosSale;
use Illuminate\Support\Facades\DB;

class PosNumberService
{
    /**
     * Concurrency-safe workspace/day sequence.
     * The DB unique(workspace_id,date) constraint closes the first-row race.
     */
    public function next(int $workspaceId): string
    {
        return DB::transaction(function () use ($workspaceId) {
            $date = now()->format('Ymd');

            DB::table('pos_numbers')->insertOrIgnore([
                'workspace_id' => $workspaceId,
                'date' => $date,
                'last_number' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $record = PosNumber::query()
                ->where('workspace_id', $workspaceId)
                ->where('date', $date)
                ->lockForUpdate()
                ->firstOrFail();

            do {
                $record->last_number = ((int) $record->last_number) + 1;
                $record->save();
                $number = sprintf('POS-%s-%05d', $date, $record->last_number);
            } while (PosSale::where('workspace_id', $workspaceId)->where('sale_number', $number)->exists());

            return $number;
        });
    }
}

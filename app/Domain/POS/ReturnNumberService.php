<?php

namespace App\Domain\POS;

use App\Models\POS\ReturnNumber;
use Illuminate\Support\Facades\DB;

class ReturnNumberService
{
    /**
     * Generate a concurrency-safe workspace-scoped return number.
     * Format: RET-YYYYMMDD-00001
     */
    public function next(int $workspaceId): string
    {
        return DB::transaction(function () use ($workspaceId) {
            $date = now()->format('Ymd');

            $record = ReturnNumber::lockForUpdate()
                ->where('workspace_id', $workspaceId)
                ->where('date', $date)
                ->first();

            if ($record) {
                $record->increment('last_number');
                $seq = $record->last_number;
            } else {
                ReturnNumber::create([
                    'workspace_id' => $workspaceId,
                    'date' => $date,
                    'last_number' => 1,
                ]);
                $seq = 1;
            }

            return sprintf('RET-%s-%05d', $date, $seq);
        });
    }
}
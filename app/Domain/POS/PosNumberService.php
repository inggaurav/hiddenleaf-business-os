<?php

namespace App\Domain\POS;

use App\Models\POS\PosNumber;
use Illuminate\Support\Facades\DB;

class PosNumberService
{
    /**
     * Generate a concurrency-safe workspace-scoped POS sale number.
     * Format: POS-YYYYMMDD-00001
     */
    public function next(int $workspaceId): string
    {
        return DB::transaction(function () use ($workspaceId) {
            $date = now()->format('Ymd');

            $record = PosNumber::lockForUpdate()
                ->where('workspace_id', $workspaceId)
                ->where('date', $date)
                ->first();

            if ($record) {
                $record->increment('last_number');
                $seq = $record->last_number;
            } else {
                PosNumber::create([
                    'workspace_id' => $workspaceId,
                    'date' => $date,
                    'last_number' => 1,
                ]);
                $seq = 1;
            }

            return sprintf('POS-%s-%05d', $date, $seq);
        });
    }
}
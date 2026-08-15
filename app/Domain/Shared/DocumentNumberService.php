<?php

namespace App\Domain\Shared;

use App\Models\DocumentNumber;
use Illuminate\Support\Facades\DB;

class DocumentNumberService
{
    /**
     * Concurrency-safe workspace/type/day document sequence generation.
     * The DB unique(workspace_id,type,date) constraint eliminates race conditions.
     */
    public function next(int $workspaceId, string $type, string $prefix): string
    {
        return DB::transaction(function () use ($workspaceId, $type, $prefix) {
            $date = now()->format('Ymd');

            DB::table('document_numbers')->insertOrIgnore([
                'workspace_id' => $workspaceId,
                'type' => $type,
                'date' => $date,
                'last_number' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $record = DocumentNumber::query()
                ->where('workspace_id', $workspaceId)
                ->where('type', $type)
                ->where('date', $date)
                ->lockForUpdate()
                ->firstOrFail();

            $record->last_number = ((int) $record->last_number) + 1;
            $record->save();

            return sprintf('%s-%s-%05d', $prefix, $date, $record->last_number);
        });
    }
}

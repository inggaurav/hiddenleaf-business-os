<?php

namespace App\Domain\Accounting;

use App\Models\AccountBankTransfer;
use App\Models\BankTransferNumber;
use Illuminate\Support\Facades\DB;

class BankTransferNumberService
{
    /**
     * Concurrency-safe workspace/day bank transfer sequence.
     * The DB unique(workspace_id,date) constraint closes the first-row race.
     */
    public function next(int $workspaceId): string
    {
        return DB::transaction(function () use ($workspaceId) {
            $date = now()->format('Ymd');

            DB::table('bank_transfer_numbers')->insertOrIgnore([
                'workspace_id' => $workspaceId,
                'date' => $date,
                'last_number' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $record = BankTransferNumber::query()
                ->where('workspace_id', $workspaceId)
                ->where('date', $date)
                ->lockForUpdate()
                ->firstOrFail();

            do {
                $record->last_number = ((int) $record->last_number) + 1;
                $record->save();
                $number = sprintf('TRF-%s-%05d', $date, $record->last_number);
            } while (AccountBankTransfer::where('workspace_id', $workspaceId)->where('transfer_number', $number)->exists());

            return $number;
        });
    }
}

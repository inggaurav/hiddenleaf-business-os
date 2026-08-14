<?php

namespace App\Domain\POS;

use App\Domain\Accounting\CommercialAccountingService;
use App\Domain\Accounting\LedgerService;
use App\Domain\Accounting\Money;
use App\Models\AccountType;
use App\Models\JournalEntry;
use App\Models\LedgerAccount;
use App\Models\POS\PosReturn;
use App\Models\POS\PosSale;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Posts POS-native immediate settlement entries into the general ledger.
 *
 * POS is not converted into a SalesInvoice, so there is exactly one accounting
 * source for a POS sale. Inventory is posted separately by the canonical stock
 * movement engine inside the same outer transaction.
 */
class PosAccountingService
{
    public function __construct(
        private readonly LedgerService $ledger,
        private readonly CommercialAccountingService $commercialAccounts,
    ) {}

    public function postSale(PosSale $sale, User $actor): JournalEntry
    {
        return DB::transaction(function () use ($sale, $actor) {
            $locked = PosSale::query()->whereKey($sale->id)->lockForUpdate()->firstOrFail();
            if ($locked->journal_entry_id) {
                return JournalEntry::findOrFail($locked->journal_entry_id);
            }

            $reference = 'POS-'.$locked->sale_number;
            if ($existing = $this->postedEntry($locked->organization_id, $locked->workspace_id, $reference)) {
                $locked->update(['journal_entry_id' => $existing->id]);
                return $existing;
            }

            $accounts = $this->commercialAccounts->systemAccounts($locked->organization_id, $locked->workspace_id);
            $settlement = $this->settlementAccount($locked->organization_id, $locked->workspace_id, $locked->payment_method, $accounts['sales_revenue']->currency);
            $taxPayable = $this->taxPayableAccount($locked->organization_id, $locked->workspace_id, $accounts['sales_revenue']->currency);

            $total = Money::of((string) $locked->total, 4);
            $tax = Money::of((string) $locked->tax_amount, 4);
            $netRevenue = $total->subtract($tax);

            $lines = [
                ['account_id' => $settlement->id, 'debit' => $total->toStorageString(), 'credit' => '0.0000'],
                ['account_id' => $accounts['sales_revenue']->id, 'debit' => '0.0000', 'credit' => $netRevenue->toStorageString()],
            ];
            if ($tax->isPositive()) {
                $lines[] = ['account_id' => $taxPayable->id, 'debit' => '0.0000', 'credit' => $tax->toStorageString()];
            }

            $entry = $this->ledger->createEntry($locked->organization_id, $locked->workspace_id, [
                'entry_date' => $locked->created_at->toDateString(),
                'reference' => $reference,
                'description' => 'POS sale '.$locked->sale_number,
                'lines' => $lines,
            ], $actor);
            $this->ledger->post($entry, $actor);
            $locked->update(['journal_entry_id' => $entry->id]);

            return $entry->refresh();
        });
    }

    public function postReturn(PosReturn $return, User $actor): JournalEntry
    {
        return DB::transaction(function () use ($return, $actor) {
            $locked = PosReturn::with(['sale', 'items.saleItem'])->whereKey($return->id)->lockForUpdate()->firstOrFail();
            if ($locked->journal_entry_id) {
                return JournalEntry::findOrFail($locked->journal_entry_id);
            }

            $reference = 'POS-RETURN-'.$locked->return_number;
            if ($existing = $this->postedEntry($locked->organization_id, $locked->workspace_id, $reference)) {
                $locked->update(['journal_entry_id' => $existing->id]);
                return $existing;
            }

            $accounts = $this->commercialAccounts->systemAccounts($locked->organization_id, $locked->workspace_id);
            $method = $locked->refund_method ?: $locked->sale->payment_method;
            $settlement = $this->settlementAccount($locked->organization_id, $locked->workspace_id, $method, $accounts['sales_revenue']->currency);
            $taxPayable = $this->taxPayableAccount($locked->organization_id, $locked->workspace_id, $accounts['sales_revenue']->currency);

            $refund = Money::of((string) $locked->refund_amount, 4);
            $taxRefund = Money::zero(4);
            foreach ($locked->items as $returnItem) {
                $saleItem = $returnItem->saleItem;
                if (! $saleItem || bccomp((string) $saleItem->quantity, '0', 4) <= 0) {
                    continue;
                }
                $ratio = bcdiv((string) $returnItem->quantity, (string) $saleItem->quantity, 8);
                $taxRefund = $taxRefund->add(Money::of((string) $saleItem->tax_amount, 4)->multiplyByDecimal($ratio));
            }
            $taxRefund = $taxRefund->min($refund);
            $revenueRefund = $refund->subtract($taxRefund);

            $lines = [
                ['account_id' => $accounts['sales_revenue']->id, 'debit' => $revenueRefund->toStorageString(), 'credit' => '0.0000'],
                ['account_id' => $settlement->id, 'debit' => '0.0000', 'credit' => $refund->toStorageString()],
            ];
            if ($taxRefund->isPositive()) {
                $lines[] = ['account_id' => $taxPayable->id, 'debit' => $taxRefund->toStorageString(), 'credit' => '0.0000'];
            }

            $entry = $this->ledger->createEntry($locked->organization_id, $locked->workspace_id, [
                'entry_date' => now()->toDateString(),
                'reference' => $reference,
                'description' => 'POS return '.$locked->return_number,
                'lines' => $lines,
            ], $actor);
            $this->ledger->post($entry, $actor);
            $locked->update(['journal_entry_id' => $entry->id]);

            return $entry->refresh();
        });
    }

    private function settlementAccount(int $organizationId, int $workspaceId, string $method, ?string $currency): LedgerAccount
    {
        $asset = AccountType::firstOrCreate(
            ['workspace_id' => $workspaceId, 'name' => 'Assets'],
            ['organization_id' => $organizationId, 'classification' => 'asset', 'normal_balance' => 'debit']
        );

        [$code, $name] = match (strtolower($method)) {
            'cash' => ['POS-CASH', 'POS Cash'],
            'card' => ['POS-CARD', 'POS Card Clearing'],
            'bank' => ['POS-BANK', 'POS Bank Clearing'],
            default => ['POS-OTHER', 'POS Other Settlement'],
        };

        return LedgerAccount::firstOrCreate(
            ['workspace_id' => $workspaceId, 'code' => $code],
            [
                'organization_id' => $organizationId,
                'account_type_id' => $asset->id,
                'name' => $name,
                'currency' => $currency ?: 'USD',
                'is_bank' => true,
                'is_active' => true,
            ]
        );
    }

    private function taxPayableAccount(int $organizationId, int $workspaceId, ?string $currency): LedgerAccount
    {
        $liability = AccountType::firstOrCreate(
            ['workspace_id' => $workspaceId, 'name' => 'Liabilities'],
            ['organization_id' => $organizationId, 'classification' => 'liability', 'normal_balance' => 'credit']
        );

        return LedgerAccount::firstOrCreate(
            ['workspace_id' => $workspaceId, 'code' => 'POS-TAX'],
            [
                'organization_id' => $organizationId,
                'account_type_id' => $liability->id,
                'name' => 'POS Sales Tax Payable',
                'currency' => $currency ?: 'USD',
                'is_bank' => false,
                'is_active' => true,
            ]
        );
    }

    private function postedEntry(int $organizationId, int $workspaceId, string $reference): ?JournalEntry
    {
        return JournalEntry::where('organization_id', $organizationId)
            ->where('workspace_id', $workspaceId)
            ->where('reference', $reference)
            ->where('status', 'posted')
            ->first();
    }
}

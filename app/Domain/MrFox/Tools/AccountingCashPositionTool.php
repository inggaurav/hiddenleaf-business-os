<?php

namespace App\Domain\MrFox\Tools;

use App\Domain\Accounting\LedgerService;
use App\Domain\MrFox\Contracts\MrFoxToolContract;
use App\Domain\MrFox\DTO\ToolContext;
use App\Domain\MrFox\DTO\ToolResult;
use App\Domain\MrFox\RiskLevel;
use App\Models\LedgerAccount;

class AccountingCashPositionTool implements MrFoxToolContract
{
    public function name(): string
    {
        return 'accounting.cash_position';
    }

    public function description(): string
    {
        return 'Query all active bank accounts, opening balances, current cash balances, and total liquidity.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [],
        ];
    }

    public function requiredPermission(): ?string
    {
        return 'account.reports';
    }

    public function requiredModule(): ?string
    {
        return 'account';
    }

    public function riskLevel(): RiskLevel
    {
        return RiskLevel::READ;
    }

    public function execute(ToolContext $context, array $input): ToolResult
    {
        $orgId = $context->getOrganizationId();
        $wsId = $context->getWorkspaceId();

        $accounts = LedgerAccount::where('organization_id', $orgId)
            ->where('workspace_id', $wsId)
            ->where('is_bank', true)
            ->get();

        $ledgerService = app(LedgerService::class);
        $balances = $ledgerService->balances($orgId, $wsId)->keyBy('id');

        $safeAccounts = $accounts->map(function ($acc) use ($balances) {
            $ledgerBal = (float) ($balances->get($acc->id)?->balance ?? 0);
            $balance = $ledgerBal != 0.0 ? $ledgerBal : (float) ($acc->opening_balance ?? 0);

            return [
                'id' => $acc->id,
                'name' => $acc->name,
                'code' => $acc->code,
                'balance' => $balance,
            ];
        });

        $totalLiquidity = (float) $safeAccounts->sum('balance');

        $evidence = $safeAccounts->map(fn ($acc) => [
            'type' => 'bank_account',
            'id' => $acc['id'],
            'label' => "{$acc['name']} ({$acc['code']}) - $".number_format($acc['balance'], 2),
            'route' => '/accounting/bank-accounts',
        ])->all();

        $summary = sprintf('Total Cash Liquidity across %d bank account(s): $%s', $safeAccounts->count(), number_format($totalLiquidity, 2));

        return ToolResult::success($safeAccounts->toArray(), $summary, $evidence);
    }
}

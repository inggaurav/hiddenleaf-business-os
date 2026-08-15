<?php

namespace App\Domain\MrFox\Tools;

use App\Domain\MrFox\Contracts\MrFoxToolContract;
use App\Domain\MrFox\DTO\ToolContext;
use App\Domain\MrFox\DTO\ToolResult;
use App\Domain\MrFox\RiskLevel;
use App\Models\BankAccount;

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
        $wsId = $context->getWorkspaceId();

        $accounts = BankAccount::where('workspace_id', $wsId)->get();
        $totalLiquidity = (float) $accounts->sum('opening_balance');

        $evidence = $accounts->map(fn ($acc) => [
            'type' => 'bank_account',
            'id' => $acc->id,
            'label' => "{$acc->bank_name} ({$acc->holder_name}) - $" . number_format($acc->opening_balance, 2),
            'route' => '/accounting/bank-accounts',
        ])->all();

        $summary = sprintf('Total Cash Liquidity across %d bank account(s): $%s', $accounts->count(), number_format($totalLiquidity, 2));

        return ToolResult::success($accounts->toArray(), $summary, $evidence);
    }
}

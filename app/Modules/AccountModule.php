<?php

namespace App\Modules;

use App\Services\BaseModule;

class AccountModule extends BaseModule
{
    protected string $name = 'Account';

    protected string $alias = 'account';

    protected string $version = '1.0.0';

    protected string $description = 'Complete double-entry accounting, chart of accounts, banking, invoices, bills, payments, and financial reports.';

    protected array $permissions = [
        'manage_accounts',
        'view_ledger',
        'create_bill',
        'manage_bank_accounts',
    ];

    protected array $navigation = [
        'title' => 'Accounting',
        'icon' => 'banknotes',
        'route' => 'account.index',
    ];
}

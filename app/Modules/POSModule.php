<?php

namespace App\Modules;

use App\Services\BaseModule;

class POSModule extends BaseModule
{
    protected string $name = 'POS';

    protected string $alias = 'pos';

    protected string $version = '1.0.0';

    protected string $description = 'Point of Sale register, barcode scanning, instant receipt generation, customer checkout, and sales tracking.';

    protected array $permissions = [
        'manage_pos',
        'create_pos_order',
        'print_pos_receipt',
    ];

    protected array $navigation = [
        'title' => 'POS',
        'icon' => 'calculator',
        'route' => 'pos.index',
    ];
}

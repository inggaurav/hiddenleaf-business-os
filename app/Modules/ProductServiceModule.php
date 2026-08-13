<?php

namespace App\Modules;

use App\Services\BaseModule;

class ProductServiceModule extends BaseModule
{
    protected string $name = 'ProductService';

    protected string $alias = 'productservice';

    protected string $version = '1.0.0';

    protected string $description = 'Products and services inventory catalog, SKU tracking, units of measurement, tax rates, and category classification.';

    protected array $permissions = [
        'manage_products',
        'manage_services',
        'manage_categories',
        'manage_units',
        'manage_taxes',
    ];

    protected array $navigation = [
        'title' => 'Products & Services',
        'icon' => 'cube',
        'route' => 'product-service.index',
    ];
}

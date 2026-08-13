<?php

namespace App\Modules;

use App\Services\BaseModule;

class LandingPageModule extends BaseModule
{
    protected string $name = 'LandingPage';

    protected string $alias = 'landingpage';

    protected string $version = '1.0.0';

    protected string $description = 'Public SaaS landing page customizer, hero sections, features, pricing tables, testimonials, FAQs, custom pages, and SEO configuration.';

    protected array $permissions = [
        'manage_landing_page',
        'manage_custom_pages',
        'manage_seo_settings',
    ];

    protected array $navigation = [
        'title' => 'Landing Page',
        'icon' => 'globe',
        'route' => 'landing-page.index',
    ];
}

<?php

namespace Tests\Unit\Addons;

use App\Models\Addon;
use App\Services\ManifestAddonModule;
use PHPUnit\Framework\TestCase;

class ManifestAddonModuleTest extends TestCase
{
    public function test_it_exposes_workdo_compatible_metadata_and_hiddenleaf_extensions(): void
    {
        $addon = new Addon([
            'addon_id' => 'fleet',
            'alias' => 'Fleet',
            'name' => 'Fleet',
            'version' => '1.2.3',
            'minimum_core' => '1.0.0',
            'dependencies' => ['Assets', 'Account'],
            'status' => 'installed',
            'manifest' => [
                'permissions' => ['fleet.manage'],
                'navigation' => [['label' => 'Fleet']],
                'hiddenleaf' => [
                    'mrfox' => ['tools' => ['Vendor\\Fleet\\Tools\\FleetSearchTool']],
                    'automations' => [
                        'triggers' => ['Vendor\\Fleet\\Automation\\MaintenanceDue'],
                        'actions' => ['Vendor\\Fleet\\Automation\\CreateMaintenanceTask'],
                    ],
                ],
            ],
        ]);

        $module = new ManifestAddonModule($addon);

        self::assertSame('fleet', $module->getAlias());
        self::assertSame('1.2.3', $module->getVersion());
        self::assertSame(['assets', 'account'], $module->dependencies());
        self::assertSame(['fleet.manage'], $module->getPermissions());
        self::assertSame(['Vendor\\Fleet\\Tools\\FleetSearchTool'], $module->mrFoxTools());
        self::assertSame(['Vendor\\Fleet\\Automation\\MaintenanceDue'], $module->automationTriggers());
        self::assertSame(['Vendor\\Fleet\\Automation\\CreateMaintenanceTask'], $module->automationActions());
    }
}

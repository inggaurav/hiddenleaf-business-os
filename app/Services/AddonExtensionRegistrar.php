<?php

namespace App\Services;

use App\Domain\Automation\Actions\ActionRegistry;
use App\Domain\Automation\Contracts\AutomationActionContract;
use App\Domain\Automation\Contracts\AutomationTriggerContract;
use App\Domain\Automation\Triggers\TriggerRegistry;
use App\Domain\MrFox\Contracts\MrFoxToolContract;
use App\Domain\MrFox\Tools\MrFoxToolRegistry;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class AddonExtensionRegistrar
{
    public function __construct(
        private readonly AddonManager $addons,
        private readonly Container $container,
    ) {}

    public function register(): void
    {
        try {
            if (! Schema::hasTable('addons')) {
                return;
            }
        } catch (\Throwable $e) {
            return;
        }

        $toolRegistry = $this->container->make(MrFoxToolRegistry::class);
        $triggerRegistry = $this->container->make(TriggerRegistry::class);
        $actionRegistry = $this->container->make(ActionRegistry::class);

        foreach ($this->addons->installed() as $addon) {
            $module = new ManifestAddonModule($addon);
            $alias = $module->getAlias();

            $this->registerClasses($module->mrFoxTools(), MrFoxToolContract::class, function (object $tool) use ($toolRegistry): void {
                if (! $toolRegistry->has($tool->name())) {
                    $toolRegistry->register($tool);
                }
            }, $alias);

            $this->registerClasses($module->automationTriggers(), AutomationTriggerContract::class, fn (object $trigger) => $triggerRegistry->register($trigger, $alias), $alias);
            $this->registerClasses($module->automationActions(), AutomationActionContract::class, fn (object $action) => $actionRegistry->register($action, $alias), $alias);
        }
    }

    /**
     * @param  array<int, class-string>  $classes
     * @param  class-string  $contract
     */
    private function registerClasses(array $classes, string $contract, callable $register, string $alias): void
    {
        foreach ($classes as $class) {
            if (! class_exists($class) || ! is_subclass_of($class, $contract)) {
                Log::warning('Installed add-on declares an unavailable extension class.', [
                    'addon' => $alias,
                    'class' => $class,
                    'contract' => $contract,
                ]);

                continue;
            }
            $register($this->container->make($class));
        }
    }
}

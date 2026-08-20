<?php

$vendorDir = __DIR__ . '/../vendor';
if (!is_dir($vendorDir . '/composer')) {
    mkdir($vendorDir . '/composer', 0755, true);
}

$psr4Map = [
    'App\\' => [__DIR__ . '/../app'],
    'HiddenLeaf\\' => [__DIR__ . '/../app'],
    'Database\\Seeders\\' => [__DIR__ . '/../database/seeders'],
    'Database\\Factories\\' => [__DIR__ . '/../database/factories'],
    'Illuminate\\Support\\' => [$vendorDir . '/laravel/framework/src/Illuminate/Support'],
    'Illuminate\\' => [$vendorDir . '/laravel/framework/src/Illuminate'],
    'Inertia\\' => [$vendorDir . '/inertiajs/inertia-laravel/src'],
    'Nwidart\\Modules\\' => [$vendorDir . '/nwidart/laravel-modules/src'],
    'Laravel\\Sanctum\\' => [$vendorDir . '/laravel/sanctum/src'],
    'Laravel\\Tinker\\' => [$vendorDir . '/laravel/tinker/src'],
    'Laravel\\Prompts\\' => [$vendorDir . '/laravel/prompts/src'],
    'Laravel\\Pint\\' => [$vendorDir . '/laravel/pint/src'],
    'Symfony\\Component\\Console\\' => [$vendorDir . '/symfony/console'],
    'Symfony\\Component\\CssSelector\\' => [$vendorDir . '/symfony/css-selector'],
    'Symfony\\Component\\ErrorHandler\\' => [$vendorDir . '/symfony/error-handler'],
    'Symfony\\Component\\EventDispatcher\\' => [$vendorDir . '/symfony/event-dispatcher'],
    'Symfony\\Component\\Filesystem\\' => [$vendorDir . '/symfony/filesystem'],
    'Symfony\\Component\\Finder\\' => [$vendorDir . '/symfony/finder'],
    'Symfony\\Component\\HttpFoundation\\' => [$vendorDir . '/symfony/http-foundation'],
    'Symfony\\Component\\HttpKernel\\' => [$vendorDir . '/symfony/http-kernel'],
    'Symfony\\Component\\Mailer\\' => [$vendorDir . '/symfony/mailer'],
    'Symfony\\Component\\Mime\\' => [$vendorDir . '/symfony/mime'],
    'Symfony\\Component\\Process\\' => [$vendorDir . '/symfony/process'],
    'Symfony\\Component\\Routing\\' => [$vendorDir . '/symfony/routing'],
    'Symfony\\Component\\String\\' => [$vendorDir . '/symfony/string'],
    'Symfony\\Component\\Translation\\' => [$vendorDir . '/symfony/translation'],
    'Symfony\\Component\\Uid\\' => [$vendorDir . '/symfony/uid'],
    'Symfony\\Component\\VarDumper\\' => [$vendorDir . '/symfony/var-dumper'],
    'Symfony\\Contracts\\EventDispatcher\\' => [$vendorDir . '/symfony/event-dispatcher-contracts'],
    'Symfony\\Contracts\\Service\\' => [$vendorDir . '/symfony/service-contracts'],
    'Symfony\\Contracts\\Translation\\' => [$vendorDir . '/symfony/translation-contracts'],
    'Psr\\Clock\\' => [$vendorDir . '/psr/clock/src'],
    'Psr\\Container\\' => [$vendorDir . '/psr/container/src'],
    'Psr\\EventDispatcher\\' => [$vendorDir . '/psr/event-dispatcher/src'],
    'Psr\\Http\\Client\\' => [$vendorDir . '/psr/http-client/src'],
    'Psr\\Http\\Message\\' => [$vendorDir . '/psr/http-message/src'],
    'Psr\\Log\\' => [$vendorDir . '/psr/log/src'],
    'Psr\\SimpleCache\\' => [$vendorDir . '/psr/simple-cache/src'],
    'Carbon\\' => [$vendorDir . '/nesbot/carbon/src/Carbon'],
    'Monolog\\' => [$vendorDir . '/monolog/monolog/src/Monolog'],
    'Ramsey\\Uuid\\' => [$vendorDir . '/ramsey/uuid/src'],
    'Ramsey\\Collection\\' => [$vendorDir . '/ramsey/collection/src'],
    'Brick\\Math\\' => [$vendorDir . '/brick/math/src'],
    'Vlucas\\PhpDotenv\\' => [$vendorDir . '/vlucas/phpdotenv/src'],
    'Egulias\\EmailValidator\\' => [$vendorDir . '/egulias/email-validator/src'],
    'GuzzleHttp\\' => [$vendorDir . '/guzzlehttp/guzzle/src'],
    'GuzzleHttp\\Psr7\\' => [$vendorDir . '/guzzlehttp/psr7/src'],
    'GuzzleHttp\\Promise\\' => [$vendorDir . '/guzzlehttp/promises/src'],
    'League\\CommonMark\\' => [$vendorDir . '/league/commonmark/src'],
    'League\\Config\\' => [$vendorDir . '/league/config/src'],
    'League\\Flysystem\\' => [$vendorDir . '/league/flysystem/src'],
    'League\\Flysystem\\Local\\' => [$vendorDir . '/league/flysystem-local'],
    'League\\Flysystem\\AwsS3V3\\' => [$vendorDir . '/league/flysystem-aws-s3-v3'],
    'League\\MimeTypeDetection\\' => [$vendorDir . '/league/mime-type-detection/src'],
    'Nette\\' => [$vendorDir . '/nette/utils/src'],
    'Nette\\Schema\\' => [$vendorDir . '/nette/schema/src'],
    'Dflydev\\DotAccessData\\' => [$vendorDir . '/dflydev/dot-access-data/src'],
    'Mockery\\' => [$vendorDir . '/mockery/mockery/library/Mockery'],
    'Whoops\\' => [$vendorDir . '/filp/whoops/src/Whoops'],
    'NunoMaduro\\Collision\\' => [$vendorDir . '/nunomaduro/collision/src'],
    'PHPUnit\\' => [$vendorDir . '/phpunit/phpunit/src'],
];

// Write autoload.php
$autoloadContent = "<?php\n\n// Custom generated autoloader for Windows compatibility\n\nspl_autoload_register(function (\$class) {\n    \$map = " . var_export($psr4Map, true) . ";\n    foreach (\$map as \$prefix => \$dirs) {\n        \$len = strlen(\$prefix);\n        if (strncmp(\$prefix, \$class, \$len) !== 0) {\n            continue;\n        }\n        \$relativeClass = substr(\$class, \$len);\n        foreach (\$dirs as \$dir) {\n            \$file = \$dir . '/' . str_replace('\\\\', '/', \$relativeClass) . '.php';\n            if (file_exists(\$file)) {\n                require_once \$file;\n                return true;\n            }\n        }\n    }\n    return false;\n});\n\n// Load global functions / files\n\$files = [\n    __DIR__ . '/../app/helpers.php',\n    __DIR__ . '/laravel/framework/src/Illuminate/Collections/functions.php',\n    __DIR__ . '/laravel/framework/src/Illuminate/Foundation/helpers.php',\n    __DIR__ . '/laravel/framework/src/Illuminate/Support/helpers.php',\n    __DIR__ . '/symfony/deprecation-contracts/function.php',\n    __DIR__ . '/symfony/string/Resources/functions.php',\n];\nforeach (\$files as \$f) {\n    if (file_exists(\$f)) {\n        require_once \$f;\n    }\n}\n";

file_put_contents($vendorDir . '/autoload.php', $autoloadContent);
echo "vendor/autoload.php updated successfully!\n";

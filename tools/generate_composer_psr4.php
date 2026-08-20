<?php

$vendorDir = realpath(__DIR__ . '/../vendor');
$appDir = realpath(__DIR__ . '/../app');
$rootJson = json_decode(file_get_contents(__DIR__ . '/../composer.json'), true);

$psr4Map = [];
$autoloadFiles = [
    realpath(__DIR__ . '/../app/helpers.php'),
    realpath(__DIR__ . '/../vendor/symfony/polyfill-php86/Resources/stubs/SortDirection.php'),
];

$scanAutoload = function ($json, $pkgDir) use (&$psr4Map, &$autoloadFiles) {
    foreach (['autoload', 'autoload-dev'] as $section) {
        if (!isset($json[$section])) continue;
        
        // Scan psr-4
        if (isset($json[$section]['psr-4'])) {
            foreach ($json[$section]['psr-4'] as $prefix => $paths) {
                if (!is_array($paths)) $paths = [$paths];
                foreach ($paths as $p) {
                    $fullPath = realpath($pkgDir . '/' . $p);
                    if ($fullPath) $psr4Map[$prefix][] = $fullPath;
                }
            }
        }

        // Scan files
        if (isset($json[$section]['files'])) {
            foreach ($json[$section]['files'] as $f) {
                $fullPath = realpath($pkgDir . '/' . $f);
                if ($fullPath) $autoloadFiles[] = $fullPath;
            }
        }
    }
};

// Scan root composer.json
$scanAutoload($rootJson, __DIR__ . '/..');

// Scan vendor directories for composer.json
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($vendorDir, RecursiveDirectoryIterator::SKIP_DOTS),
    RecursiveIteratorIterator::SELF_FIRST
);

foreach ($iterator as $item) {
    if ($item->isFile() && $item->getFilename() === 'composer.json') {
        $json = json_decode(file_get_contents($item->getPathname()), true);
        $scanAutoload($json, $item->getPath());
    }
}

// Auto-map all vendor/sebastian packages
foreach (glob($vendorDir . '/sebastian/*') as $sebDir) {
    if (is_dir($sebDir . '/src')) {
        $name = basename($sebDir);
        $studly = str_replace(' ', '', ucwords(str_replace('-', ' ', $name)));
        $prefix = "SebastianBergmann\\" . $studly . "\\";
        $psr4Map[$prefix][] = realpath($sebDir . '/src');
    }
}

// Ensure critical dev tool mappings
$psr4Map['PHPUnit\\Framework\\'][] = realpath($vendorDir . '/phpunit/phpunit/src/Framework');
$psr4Map['PHPUnit\\Event\\'][] = realpath($vendorDir . '/phpunit/phpunit/src/Event/Events');
$psr4Map['PHPUnit\\'][] = realpath($vendorDir . '/phpunit/phpunit/src');
$psr4Map['PharIo\\Version\\'][] = realpath($vendorDir . '/phar-io/version/src');
$psr4Map['PharIo\\Manifest\\'][] = realpath($vendorDir . '/phar-io/manifest/src');
$psr4Map['DeepCopy\\'][] = realpath($vendorDir . '/myclabs/deep-copy/src/DeepCopy');
$psr4Map['TheSeer\\Tokenizer\\'][] = realpath($vendorDir . '/theseer/tokenizer/src');

// Find all helper and function files in illuminate framework
$illuminateDir = $vendorDir . '/laravel/framework/src/Illuminate';
$illuminateIterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($illuminateDir, RecursiveDirectoryIterator::SKIP_DOTS),
    RecursiveIteratorIterator::SELF_FIRST
);

foreach ($illuminateIterator as $item) {
    if ($item->isFile() && ($item->getFilename() === 'helpers.php' || $item->getFilename() === 'functions.php')) {
        $autoloadFiles[] = realpath($item->getPathname());
    }
}

$autoloadFiles = array_values(array_filter(array_unique($autoloadFiles)));

$autoloadContent = "<?php\n\n// Dynamically generated complete PSR-4 autoloader\n\n// PHP 8.4 Pdo\\Mysql polyfill for PHP 8.2 compatibility\nif (!class_exists('Pdo\\\\Mysql', false)) {\n    class Pdo_Mysql_Polyfill {\n        public const ATTR_SSL_CA = 1012;\n    }\n    class_alias('Pdo_Mysql_Polyfill', 'Pdo\\\\Mysql');\n}\n\nspl_autoload_register(function (\$class) {\n    \$map = " . var_export($psr4Map, true) . ";\n    foreach (\$map as \$prefix => \$dirs) {\n        \$trimmedPrefix = rtrim(\$prefix, '\\\\');\n        if (\$class === \$trimmedPrefix) {\n            foreach (\$dirs as \$dir) {\n                \$file = \$dir . '/' . basename(str_replace('\\\\', '/', \$class)) . '.php';\n                if (file_exists(\$file)) {\n                    require_once \$file;\n                    return true;\n                }\n            }\n        }\n        \$len = strlen(\$prefix);\n        if (strncmp(\$prefix, \$class, \$len) !== 0) {\n            continue;\n        }\n        \$relativeClass = substr(\$class, \$len);\n        foreach (\$dirs as \$dir) {\n            \$file = \$dir . '/' . str_replace('\\\\', '/', \$relativeClass) . '.php';\n            if (file_exists(\$file)) {\n                require_once \$file;\n                return true;\n            }\n        }\n    }\n    return false;\n});\n\n// Load global helper files\n\$files = " . var_export($autoloadFiles, true) . ";\nforeach (\$files as \$f) {\n    if (file_exists(\$f)) {\n        require_once \$f;\n    }\n}\n";

file_put_contents($vendorDir . '/autoload.php', $autoloadContent);
echo "Full dynamic PSR-4 autoloader generated with PHPUnit\\Event mapping!\n";

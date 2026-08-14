<?php

declare(strict_types=1);

use Symfony\Component\Process\Process;

require dirname(__DIR__).'/vendor/autoload.php';

const ROOT = __DIR__.'/..';
const REFERENCE_SHA = '0b996a0050abcdffa2770a82fbb9261eeb2805bd';
const CORE_MODULES = ['CoreApp', 'Account', 'Hrm', 'Lead', 'Taskly', 'Pos', 'ProductService', 'LandingPage'];

$check = in_array('--check', $argv, true);
$referenceFile = argumentValue('--reference-routes') ?? ROOT.'/storage/app/workdo-routes.json';

if ($check) {
    $routes = currentRoutes();
    $contracts = pageContracts($routes);
    $generated = [
        ROOT.'/docs/ui/backend-page-contracts.json' => jsonDocument(['schema_version' => 1, 'contracts' => $contracts]),
        ROOT.'/docs/qa/ROUTE_AUDIT.md' => renderRouteAudit(routeAudit($routes), count($routes)),
    ];
    $errors = [];
    foreach ($generated as $path => $content) {
        if (! is_file($path)) {
            $errors[] = relative($path).' is missing';
        } elseif (normalize(file_get_contents($path)) !== normalize($content)) {
            $errors[] = relative($path).' is stale';
        }
    }
    foreach ([
        'docs/reference/workdo-route-registry.json' => ['routes'],
        'docs/reference/workdo-screen-registry.json' => ['screens'],
        'docs/reference/workdo-controller-action-registry.json' => ['actions'],
        'docs/reference/workdo-permission-registry.json' => ['permissions'],
        'docs/reference/workdo-core-parity-v2.json' => ['records', 'totals'],
    ] as $file => $requiredKeys) {
        $path = ROOT.'/'.$file;
        if (! is_file($path)) {
            $errors[] = $file.' is missing';

            continue;
        }
        try {
            $document = json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
            foreach ($requiredKeys as $key) {
                if (! isset($document[$key]) || ! is_array($document[$key])) {
                    $errors[] = $file.' has no '.$key.' collection';
                }
            }
        } catch (Throwable $exception) {
            $errors[] = $file.' is invalid JSON: '.$exception->getMessage();
        }
    }
    if ($errors !== []) {
        fwrite(STDERR, "Forensic audit failed:\n- ".implode("\n- ", $errors)."\n");
        exit(1);
    }
    echo 'Forensic audit verified: '.count(currentRoutes()).' routes and '.count(pageContracts())." Inertia contracts.\n";
    exit(0);
}

$generated = generateArtifacts($referenceFile);
foreach ($generated as $path => $content) {
    $directory = dirname($path);
    if (! is_dir($directory)) {
        mkdir($directory, 0777, true);
    }
    file_put_contents($path, $content);
    echo 'Generated '.relative($path)."\n";
}

/** @return array<string, string> */
function generateArtifacts(string $referenceFile): array
{
    $current = currentRoutes();
    $reference = readJson($referenceFile);
    $contracts = pageContracts($current);
    $tests = testEvidence();

    $referenceRoutes = array_values(array_map(fn (array $route): array => enrichReferenceRoute($route), array_filter($reference, 'relevantReferenceRoute')));
    $screens = referenceScreens();
    $actions = controllerActions($referenceRoutes);
    $permissions = permissionRegistry();
    $parity = parityV2($referenceRoutes, $current, $contracts, $tests);
    $routeAudit = routeAudit($current);

    return [
        ROOT.'/docs/reference/workdo-route-registry.json' => jsonDocument([
            'schema_version' => 2,
            'reference_sha' => REFERENCE_SHA,
            'routes' => $referenceRoutes,
        ]),
        ROOT.'/docs/reference/workdo-screen-registry.json' => jsonDocument([
            'schema_version' => 2,
            'reference_sha' => REFERENCE_SHA,
            'screens' => $screens,
        ]),
        ROOT.'/docs/reference/workdo-controller-action-registry.json' => jsonDocument([
            'schema_version' => 2,
            'reference_sha' => REFERENCE_SHA,
            'actions' => $actions,
        ]),
        ROOT.'/docs/reference/workdo-permission-registry.json' => jsonDocument([
            'schema_version' => 2,
            'reference_sha' => REFERENCE_SHA,
            'permissions' => $permissions,
        ]),
        ROOT.'/docs/reference/workdo-core-parity-v2.json' => jsonDocument($parity),
        ROOT.'/docs/ui/backend-page-contracts.json' => jsonDocument([
            'schema_version' => 1,
            'contracts' => $contracts,
        ]),
        ROOT.'/docs/qa/ROUTE_AUDIT.md' => renderRouteAudit($routeAudit, count($current)),
    ];
}

/** @return list<array<string, mixed>> */
function currentRoutes(): array
{
    static $routes;
    if (isset($routes)) {
        return $routes;
    }
    $process = new Process([PHP_BINARY, 'artisan', 'route:list', '--except-vendor', '--json'], ROOT, [
        'APP_ENV' => 'testing',
        'APP_KEY' => 'base64:r03k9ThY08D30r38b97J1K1m0N3P5r7t9V1x3Y5z7A0=',
        'DB_CONNECTION' => 'sqlite',
        'DB_DATABASE' => ':memory:',
        'CACHE_STORE' => 'array',
        'SESSION_DRIVER' => 'array',
        'QUEUE_CONNECTION' => 'sync',
        'LICENSE_SERVER_ENABLED' => 'true',
    ]);
    $process->setTimeout(120)->mustRun();
    $routes = json_decode($process->getOutput(), true, 512, JSON_THROW_ON_ERROR);
    usort($routes, fn (array $a, array $b): int => [$a['uri'], $a['method'], $a['name'] ?? ''] <=> [$b['uri'], $b['method'], $b['name'] ?? '']);

    return $routes;
}

/** @return list<array<string, mixed>> */
function readJson(string $file): array
{
    if (! is_file($file)) {
        throw new RuntimeException("Reference route input is missing: {$file}");
    }
    $contents = preg_replace('/^\xEF\xBB\xBF/', '', file_get_contents($file));

    return json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
}

function relevantReferenceRoute(array $route): bool
{
    $uri = ltrim((string) ($route['uri'] ?? ''), '/');

    return ! preg_match('#^(?:_debugbar|_ignition|sanctum|storage|up|broadcasting/auth)#', $uri)
        && ($route['action'] ?? '') !== 'Closure';
}

/** @return array<string, mixed> */
function enrichReferenceRoute(array $route): array
{
    [$controller, $action] = splitAction((string) ($route['action'] ?? ''));
    $module = referenceModule((string) ($route['action'] ?? ''));

    return [
        'method' => $route['method'] ?? null,
        'uri' => $route['uri'] ?? null,
        'name' => $route['name'] ?? null,
        'middleware' => array_values($route['middleware'] ?? []),
        'controller' => $controller,
        'action' => $action,
        'module' => $module,
        'classification' => in_array($module, CORE_MODULES, true) ? 'CORE_OR_BUNDLED' : 'DEFERRED_ADDON',
        'permission' => permissionFromMiddleware($route['middleware'] ?? []),
        'models_tables' => [],
        'rendered_screen' => null,
    ];
}

function referenceModule(string $action): string
{
    if (preg_match('/^Workdo\\\\([^\\\\]+)/', $action, $match)) {
        return $match[1];
    }

    return str_starts_with($action, 'App\\') ? 'CoreApp' : 'Vendor';
}

/** @return array{0: ?string, 1: ?string} */
function splitAction(string $action): array
{
    if (! str_contains($action, '@')) {
        return [$action !== '' ? $action : null, null];
    }

    return explode('@', $action, 2);
}

function permissionFromMiddleware(array $middleware): ?string
{
    foreach ($middleware as $guard) {
        if (preg_match('/(?:permission|can):(.+)$/i', (string) $guard, $match)) {
            return $match[1];
        }
    }

    return null;
}

/** @return list<array<string, mixed>> */
function referenceScreens(): array
{
    $root = getenv('WORKDO_REFERENCE_ROOT') ?: 'C:/Users/manag/Documents/Mr. Fox/codecanyon-45919116-workdo-dash-saas-open-source-erp-with-multiworkspace/main-file';
    if (! is_dir($root)) {
        return [];
    }
    $screens = [];
    foreach (phpFiles([$root.'/app', $root.'/packages/workdo']) as $file) {
        $source = file_get_contents($file);
        preg_match_all('/(?:view|Inertia::render)\(\s*[\'\"]([^\'\"]+)[\'\"]/', $source, $matches, PREG_OFFSET_CAPTURE);
        foreach ($matches[1] as [$screen, $offset]) {
            $method = containingMethod($source, $offset);
            $screens[$screen.'|'.$file.'|'.$method] = [
                'screen' => $screen,
                'controller_file' => forwardSlashes(substr($file, strlen($root) + 1)),
                'action' => $method,
                'module' => moduleFromPath($file),
            ];
        }
    }
    ksort($screens);

    return array_values($screens);
}

/** @return list<array<string, mixed>> */
function controllerActions(array $routes): array
{
    $actions = [];
    foreach ($routes as $route) {
        $key = ($route['controller'] ?? '').'@'.($route['action'] ?? '');
        if (! isset($actions[$key])) {
            $actions[$key] = [
                'controller' => $route['controller'],
                'action' => $route['action'],
                'module' => $route['module'],
                'routes' => [],
            ];
        }
        $actions[$key]['routes'][] = array_filter([
            'method' => $route['method'],
            'uri' => $route['uri'],
            'name' => $route['name'],
        ], fn ($value): bool => $value !== null);
    }
    ksort($actions);

    return array_values($actions);
}

/** @return list<array<string, mixed>> */
function permissionRegistry(): array
{
    $referenceRoot = getenv('WORKDO_REFERENCE_ROOT') ?: 'C:/Users/manag/Documents/Mr. Fox/codecanyon-45919116-workdo-dash-saas-open-source-erp-with-multiworkspace/main-file';
    $reference = extractPermissions(is_dir($referenceRoot) ? phpFiles([$referenceRoot.'/app', $referenceRoot.'/packages/workdo', $referenceRoot.'/database']) : []);
    $hiddenleaf = extractPermissions(phpFiles([ROOT.'/app', ROOT.'/routes', ROOT.'/database', ROOT.'/packages']));
    $all = array_unique(array_merge(array_keys($reference), array_keys($hiddenleaf)));
    sort($all);

    return array_map(fn (string $permission): array => [
        'permission' => $permission,
        'reference_evidence' => array_values($reference[$permission] ?? []),
        'hiddenleaf_evidence' => array_values($hiddenleaf[$permission] ?? []),
        'status' => isset($hiddenleaf[$permission]) ? 'IMPLEMENTED' : 'MISSING',
    ], $all);
}

/** @return array<string, array<string, true>> */
function extractPermissions(array $files): array
{
    $permissions = [];
    $pattern = '/(?:can|cannot|authorize|canInWorkspace|workspace)\(\s*(?:\$[A-Za-z_][A-Za-z0-9_]*\s*,\s*)?[\'\"]([a-z][a-z0-9_. -]{2,80})[\'\"]/i';
    foreach ($files as $file) {
        $source = file_get_contents($file);
        preg_match_all($pattern, $source, $matches);
        foreach ($matches[1] as $permission) {
            if (str_contains($permission, '.') || str_contains($permission, ' ')) {
                $permissions[$permission][relative($file)] = true;
            }
        }
    }

    return $permissions;
}

/** @return list<array<string, mixed>> */
function pageContracts(?array $routes = null): array
{
    $routes ??= currentRoutes();
    $routeByAction = [];
    foreach ($routes as $route) {
        $routeByAction[$route['action']][] = $route;
    }
    $contracts = [];
    foreach (phpFiles([ROOT.'/app/Http/Controllers']) as $file) {
        $source = file_get_contents($file);
        preg_match_all('/Inertia::render\(\s*[\'\"]([^\'\"]+)[\'\"]/', $source, $matches, PREG_OFFSET_CAPTURE);
        foreach ($matches[1] as [$page, $offset]) {
            $method = containingMethod($source, $offset);
            $class = controllerClass($file, $source);
            $actionKey = $class.'@'.$method;
            $methodBody = methodBody($source, $method);
            $props = renderProps($source, $offset);
            if (str_contains($methodBody, '$this->options(')) {
                $props = array_merge($props, returnArrayKeys(methodBody($source, 'options')));
            }
            $permissions = permissionsFromBody($methodBody);
            $component = ROOT.'/resources/js/Pages/'.$page.'.tsx';
            $route = $routeByAction[$actionKey][0] ?? null;
            $contracts[] = [
                'page' => $page,
                'component' => relative($component),
                'component_exists' => is_file($component),
                'controller' => $class,
                'action' => $method,
                'route' => $route ? array_filter(['method' => $route['method'], 'uri' => $route['uri'], 'name' => $route['name']]) : null,
                'props' => array_values(array_unique($props)),
                'permissions' => $permissions,
            ];
        }
    }
    usort($contracts, fn (array $a, array $b): int => [$a['page'], $a['controller'], $a['action']] <=> [$b['page'], $b['controller'], $b['action']]);

    return $contracts;
}

/** @return list<string> */
function renderProps(string $source, int $pageOffset): array
{
    $start = strpos($source, ',', $pageOffset);
    $end = strpos($source, ');', $pageOffset);
    if ($start === false || $end === false || $start > $end) {
        return [];
    }

    return returnArrayKeys(substr($source, $start + 1, $end - $start - 1));
}

/** @return list<string> */
function returnArrayKeys(string $expression): array
{
    $keys = [];
    $depth = 0;
    $length = strlen($expression);
    for ($index = 0; $index < $length; $index++) {
        $character = $expression[$index];
        if ($character === '[') {
            $depth++;

            continue;
        }
        if ($character === ']') {
            $depth--;

            continue;
        }
        if ($depth !== 1 || ($character !== "'" && $character !== '"')) {
            continue;
        }
        $quote = $character;
        $start = ++$index;
        while ($index < $length && ($expression[$index] !== $quote || $expression[$index - 1] === '\\')) {
            $index++;
        }
        $candidate = substr($expression, $start, $index - $start);
        $cursor = $index + 1;
        while ($cursor < $length && ctype_space($expression[$cursor])) {
            $cursor++;
        }
        if (substr($expression, $cursor, 2) === '=>' && preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $candidate)) {
            $keys[] = $candidate;
        }
    }

    return array_values(array_unique($keys));
}

/** @return list<string> */
function permissionsFromBody(string $body): array
{
    preg_match_all('/(?:workspace|canInWorkspace|can|authorize)\(\s*(?:\$[A-Za-z_][A-Za-z0-9_]*\s*,\s*)?[\'\"]([^\'\"]+)[\'\"]/', $body, $matches);

    return array_values(array_unique(array_filter($matches[1], fn (string $item): bool => str_contains($item, '.') || str_contains($item, ' '))));
}

/** @return array<string, list<string>> */
function testEvidence(): array
{
    $evidence = [];
    foreach (phpFiles([ROOT.'/tests']) as $file) {
        $source = file_get_contents($file);
        preg_match_all('/[\'\"](\/?(?:api\/v1\/)?[A-Za-z0-9_{}\/.:-]+)[\'\"]/', $source, $matches);
        foreach ($matches[1] as $route) {
            $evidence[trim($route, '/')][relative($file)] = true;
        }
    }

    return array_map('array_keys', $evidence);
}

/** @return array<string, mixed> */
function parityV2(array $reference, array $hiddenleaf, array $contracts, array $tests): array
{
    $byName = [];
    $bySignature = [];
    $moduleRoutes = [];
    foreach ($hiddenleaf as $route) {
        if ($route['name']) {
            $byName[$route['name']][] = $route;
        }
        foreach (explode('|', $route['method']) as $method) {
            $bySignature[$method.' '.normalizedUri($route['uri'])][] = $route;
        }
        $moduleRoutes[moduleFromUri($route['uri'])][] = $route;
    }
    $contractByAction = [];
    foreach ($contracts as $contract) {
        $contractByAction[$contract['controller'].'@'.$contract['action']] = $contract;
    }

    $records = [];
    $totals = array_fill_keys(['VERIFIED', 'IMPLEMENTED', 'PARTIAL', 'MISSING', 'INTENTIONALLY_DIFFERENT', 'DEFERRED_ADDON'], 0);
    foreach ($reference as $index => $route) {
        $equivalent = null;
        if ($route['name'] && isset($byName[$route['name']])) {
            $equivalent = $byName[$route['name']][0];
        }
        if (! $equivalent) {
            foreach (explode('|', (string) $route['method']) as $method) {
                $equivalent = $bySignature[$method.' '.normalizedUri((string) $route['uri'])][0] ?? null;
                if ($equivalent) {
                    break;
                }
            }
        }

        $status = 'MISSING';
        $evidence = [];
        if ($route['classification'] === 'DEFERRED_ADDON') {
            $status = 'DEFERRED_ADDON';
        } elseif ($equivalent) {
            $testFiles = $tests[trim((string) $equivalent['uri'], '/')] ?? [];
            $actionContract = $contractByAction[$equivalent['action']] ?? null;
            $evidence = array_values(array_unique(array_merge($testFiles, $actionContract ? [$actionContract['component']] : [])));
            $status = $testFiles !== [] ? 'VERIFIED' : 'IMPLEMENTED';
        } else {
            $module = moduleKey($route['module']);
            $available = $moduleRoutes[$module] ?? [];
            if ($available !== []) {
                $presentationAction = in_array(strtolower((string) $route['action']), ['create', 'edit', 'show'], true);
                $status = $presentationAction ? 'INTENTIONALLY_DIFFERENT' : 'PARTIAL';
                $equivalent = $available[0];
                $evidence[] = 'Module is present, but no exact route/action equivalence was proven.';
            }
        }
        $totals[$status]++;
        $records[] = [
            'id' => sprintf('route.%04d', $index + 1),
            'module' => $route['module'],
            'reference' => $route,
            'hiddenleaf' => $equivalent ? [
                'method' => $equivalent['method'],
                'uri' => $equivalent['uri'],
                'name' => $equivalent['name'],
                'middleware' => $equivalent['middleware'],
                'controller_action' => $equivalent['action'],
            ] : null,
            'evidence' => $evidence,
            'status' => $status,
        ];
    }

    return [
        'schema_version' => 2,
        'reference_sha' => REFERENCE_SHA,
        'methodology' => 'Exact route-name or normalized method/URI matches are implemented; VERIFIED additionally requires route-level test evidence. Consolidated presentation routes are intentionally different. Non-presentation module routes without an exact equivalent remain PARTIAL or MISSING.',
        'totals' => ['all' => count($records)] + $totals,
        'records' => $records,
    ];
}

/** @return array<string, mixed> */
function routeAudit(array $routes): array
{
    $names = [];
    $signatures = [];
    $dead = [];
    $unprotected = [];
    foreach ($routes as $route) {
        if ($route['name']) {
            $names[$route['name']][] = $route;
        }
        $signatures[$route['method'].' '.$route['uri']][] = $route;
        [$controller, $method] = splitAction((string) $route['action']);
        if ($controller && $method && str_starts_with($controller, 'App\\')) {
            if (! class_exists($controller) || ! method_exists($controller, $method)) {
                $dead[] = routeIdentity($route);
            }
        }
        if (isSensitiveMutation($route) && ! routeProtected($route)) {
            $unprotected[] = routeIdentity($route);
        }
    }
    $duplicateNames = array_filter($names, fn (array $items): bool => count($items) > 1);
    $duplicateSignatures = array_filter($signatures, fn (array $items): bool => count($items) > 1);
    $missingNames = array_values(array_map('routeIdentity', array_filter($routes, fn (array $route): bool => ! $route['name'] && $route['action'] !== 'Closure')));

    return [
        'critical' => array_values(array_unique($unprotected)),
        'high' => array_values(array_unique($dead)),
        'medium' => array_keys($duplicateNames),
        'low' => array_merge(array_keys($duplicateSignatures), $missingNames),
    ];
}

function renderRouteAudit(array $audit, int $count): string
{
    $lines = [
        '# Route audit', '',
        'Generated deterministically by `php tools/forensic-audit.php`. The audit covers '.$count.' non-vendor application routes.', '',
        '| Severity | Findings |', '|---|---:|',
        '| Critical | '.count($audit['critical']).' |',
        '| High | '.count($audit['high']).' |',
        '| Medium | '.count($audit['medium']).' |',
        '| Low | '.count($audit['low']).' |', '',
        'Critical means an apparently sensitive mutation has neither authentication middleware nor an explicit public-endpoint classification. High means a route points to a missing application controller/method. Medium covers duplicate route names. Low covers duplicate signatures and unnamed controller routes.', '',
    ];
    foreach (['critical', 'high', 'medium', 'low'] as $severity) {
        $lines[] = '## '.ucfirst($severity);
        $lines[] = '';
        if ($audit[$severity] === []) {
            $lines[] = 'No findings.';
        } else {
            foreach ($audit[$severity] as $finding) {
                $lines[] = '- `'.$finding.'`';
            }
        }
        $lines[] = '';
    }
    $lines[] = '## Guard interpretation';
    $lines[] = '';
    $lines[] = '- Installer endpoints are intentionally public before installation and are closed by `InstallController::abortWhenInstalled()` after the install lock exists.';
    $lines[] = '- Licensing activation/validation endpoints are intentionally public protocol endpoints; license lookup, domain binding, activation limits, signatures, and state checks provide their domain authorization.';
    $lines[] = '- Tenant-scoped module routes use authenticated sessions plus controller-level workspace permission resolution; `CheckModuleStatus` additionally enforces module activation.';
    $lines[] = '- Updater and module-management mutations are authenticated and perform explicit super-admin/workspace authorization in their controllers.';

    return implode("\n", $lines)."\n";
}

function isSensitiveMutation(array $route): bool
{
    $methods = array_diff(explode('|', (string) $route['method']), ['GET', 'HEAD', 'OPTIONS']);
    if ($methods === []) {
        return false;
    }
    $uri = trim((string) $route['uri'], '/');

    return ! preg_match('#^(?:login|register|forgot-password|reset-password|email/verification-notification|install(?:/|$)|api/v1/auth/login$|api/v1/licensing/(?:activate|validate|deactivate)$)#', $uri);
}

function routeProtected(array $route): bool
{
    return (bool) array_filter($route['middleware'] ?? [], fn ($middleware): bool => str_contains((string) $middleware, 'Authenticate') || $middleware === 'auth' || str_starts_with((string) $middleware, 'auth:'));
}

function routeIdentity(array $route): string
{
    return $route['method'].' '.$route['uri'].' → '.$route['action'];
}

function normalizedUri(string $uri): string
{
    $uri = trim($uri, '/');
    $uri = preg_replace('/\{[^}]+\}/', '{}', $uri);

    return strtolower($uri);
}

function moduleFromUri(string $uri): string
{
    $first = strtolower(explode('/', trim($uri, '/'))[0] ?? 'core');
    $map = ['accounting' => 'account', 'hrm' => 'hrm', 'crm' => 'lead', 'taskly' => 'taskly', 'pos' => 'pos', 'product-service' => 'productservice', '' => 'core'];

    return $map[$first] ?? $first;
}

function moduleKey(string $module): string
{
    return match (strtolower($module)) {
        'account' => 'account', 'hrm' => 'hrm', 'lead' => 'lead', 'taskly' => 'taskly', 'pos' => 'pos',
        'productservice' => 'productservice', 'landingpage' => 'landing', default => 'core',
    };
}

/** @return list<string> */
function phpFiles(array $directories): array
{
    $files = [];
    foreach ($directories as $directory) {
        if (! is_dir($directory)) {
            continue;
        }
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $files[] = $file->getPathname();
            }
        }
    }
    sort($files);

    return $files;
}

function controllerClass(string $file, string $source): string
{
    preg_match('/namespace\s+([^;]+);/', $source, $namespace);
    preg_match('/class\s+([A-Za-z_][A-Za-z0-9_]*)/', $source, $class);

    return ($namespace[1] ?? '').'\\'.($class[1] ?? basename($file, '.php'));
}

function containingMethod(string $source, int $offset): ?string
{
    $prefix = substr($source, 0, $offset);
    preg_match_all('/function\s+([A-Za-z_][A-Za-z0-9_]*)\s*\(/', $prefix, $matches);

    return $matches[1] ? end($matches[1]) : null;
}

function methodBody(string $source, ?string $method): string
{
    if (! $method || ! preg_match('/function\s+'.preg_quote($method, '/').'\s*\([^)]*\)[^{]*\{/', $source, $match, PREG_OFFSET_CAPTURE)) {
        return '';
    }
    $start = $match[0][1] + strlen($match[0][0]);
    $depth = 1;
    $length = strlen($source);
    for ($index = $start; $index < $length; $index++) {
        $depth += $source[$index] === '{' ? 1 : ($source[$index] === '}' ? -1 : 0);
        if ($depth === 0) {
            return substr($source, $start, $index - $start);
        }
    }

    return substr($source, $start);
}

function moduleFromPath(string $file): string
{
    return preg_match('#packages[\\/]workdo[\\/]([^\\/]+)#i', $file, $match) ? $match[1] : 'CoreApp';
}

function forwardSlashes(string $path): string
{
    return str_replace('\\', '/', $path);
}

function relative(string $path): string
{
    $root = forwardSlashes(realpath(ROOT) ?: ROOT);
    $normalized = forwardSlashes(realpath($path) ?: $path);

    return ltrim(str_starts_with($normalized, $root) ? substr($normalized, strlen($root)) : $normalized, '/');
}

function jsonDocument(array $document): string
{
    return json_encode($document, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)."\n";
}

function normalize(string $content): string
{
    return str_replace("\r\n", "\n", trim($content))."\n";
}

function argumentValue(string $name): ?string
{
    global $argv;
    foreach ($argv as $index => $argument) {
        if (str_starts_with($argument, $name.'=')) {
            return substr($argument, strlen($name) + 1);
        }
        if ($argument === $name && isset($argv[$index + 1])) {
            return $argv[$index + 1];
        }
    }

    return null;
}

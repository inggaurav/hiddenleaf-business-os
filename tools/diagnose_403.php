<?php
/**
 * Diagnose HiddenLeaf 403 errors.
 * Run:  php tools/diagnose_403.php
 *
 * Reports exactly which gate is blocking: workspace membership, role permission,
 * addon install status, workspace activation, or plan entitlement.
 */

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Addon;
use App\Models\Plan;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceAddon;
use Illuminate\Support\Facades\DB;

function line(string $s = ''): void { echo $s . PHP_EOL; }
function ok(string $s): void   { line("  [PASS] $s"); }
function bad(string $s): void  { line("  [FAIL] $s"); }
function info(string $s): void { line("  ----   $s"); }

$modules = ['hrm', 'crm', 'accounting', 'inventory', 'sales', 'pos'];

line('══════════════════════════════════════════════════════');
line(' HiddenLeaf 403 Diagnostic');
line('══════════════════════════════════════════════════════');

// ── Users ─────────────────────────────────────────────────────────────
line();
line('USERS');
$users = User::query()->limit(10)->get(['id', 'name', 'email', 'role']);
foreach ($users as $u) {
    line(sprintf('  #%-3d %-32s super_admin=%s', $u->id, $u->email, ($u->role === 'super_admin' ? 'YES' : 'no')));
}

// ── Workspaces ────────────────────────────────────────────────────────
line();
line('WORKSPACES');
$workspaces = Workspace::with('organization')->get();
foreach ($workspaces as $ws) {
    $org = $ws->organization;
    line(sprintf(
        '  #%-3d %-24s org=#%s owner_id=%s plan_id=%s',
        $ws->id,
        $ws->name,
        $org?->id ?? 'NULL',
        $org?->owner_id ?? 'NULL',
        $org?->plan_id ?? 'NULL'
    ));

    if (! $org) {
        bad('Workspace has NO organization — every permission check will fail.');
        continue;
    }
    if (! $org->plan_id) {
        bad('Organization has NO plan_id — isPlanEntitled() returns false for EVERY module.');
        info('This is the most common cause of blanket 403s.');
    }
}

// ── Plans ─────────────────────────────────────────────────────────────
line();
line('PLANS');
$plans = Plan::all();
if ($plans->isEmpty()) {
    bad('No plans exist at all. Every non-superadmin module check will 403.');
}
foreach ($plans as $p) {
    $mods = is_array($p->modules) ? $p->modules : (json_decode((string) $p->modules, true) ?: []);
    line(sprintf('  #%-3d %-20s modules=[%s]', $p->id, $p->name, implode(', ', $mods)));
    if (empty($mods)) {
        bad("Plan '{$p->name}' has an EMPTY modules array — nothing is entitled.");
    }
}

// ── Addons ────────────────────────────────────────────────────────────
line();
line('ADDONS (installed packages)');
$addons = Addon::all();
if ($addons->isEmpty()) {
    info('No addon records. Modules fall back to the user_active_modules table.');
}
foreach ($addons as $a) {
    $statusOk = in_array(strtolower((string) $a->status), ['installed', 'enabled', 'active'], true);
    line(sprintf('  #%-3d %-20s alias=%-12s status=%s %s',
        $a->id, $a->name, $a->alias, $a->status, $statusOk ? '' : '  <-- BLOCKS canUse()'));
}

// ── Workspace activations ─────────────────────────────────────────────
line();
line('WORKSPACE ADDON ACTIVATIONS');
$activations = WorkspaceAddon::all();
if ($activations->isEmpty()) {
    bad('No workspace_addons rows. isActiveForWorkspace() returns false for every addon module.');
}
foreach ($activations as $wa) {
    $addon = Addon::find($wa->addon_id);
    line(sprintf('  ws=#%-3d addon=%-14s active=%s',
        $wa->workspace_id, $addon?->alias ?? "#{$wa->addon_id}", $wa->is_active ? 'YES' : 'no'));
}

// ── Legacy active modules ─────────────────────────────────────────────
line();
line('USER_ACTIVE_MODULES (legacy fallback)');
try {
    $legacy = DB::table('user_active_modules')->get();
    if ($legacy->isEmpty()) {
        info('Empty.');
    }
    foreach ($legacy as $m) {
        line(sprintf('  ws=#%-3d module=%s', $m->workspace_id, $m->module_name));
    }
} catch (\Throwable $e) {
    info('Table not present: ' . $e->getMessage());
}

// ── Per user × workspace × module matrix ──────────────────────────────
line();
line('══════════════════════════════════════════════════════');
line(' GATE-BY-GATE RESULT');
line('══════════════════════════════════════════════════════');

$addonManager = app(\App\Services\AddonManager::class);
$permissions  = app(\App\Services\PermissionService::class);

foreach ($users as $user) {
    foreach ($workspaces as $ws) {
        // Is the user even a member?
        $member = $user->workspaces()->where('workspaces.id', $ws->id)->first();
        $isOwner = $ws->organization && (int) $ws->organization->owner_id === (int) $user->id;
        $isSuper = ($user->role === 'super_admin');

        if (! $member && ! $isSuper) {
            continue; // not relevant
        }

        line();
        line(sprintf('USER %s  ×  WORKSPACE #%d (%s)', $user->email, $ws->id, $ws->name));
        line(sprintf('  super_admin=%s  org_owner=%s  role_id=%s',
            $isSuper ? 'YES' : 'no',
            $isOwner ? 'YES' : 'no',
            $member?->pivot?->role_id ?? 'NULL'
        ));

        foreach ($modules as $mod) {
            $gates = [];

            // Gate 1 — permission
            $permView = $permissions->allows($user, $ws, "{$mod}.view");
            $gates[] = 'perm:' . ($permView ? 'ok' : 'FAIL');

            // Gate 2 — addon installed
            $addon = Addon::where('alias', $mod)->first();
            if ($addon) {
                $statusOk = in_array(strtolower((string) $addon->status), ['installed', 'enabled', 'active'], true);
                $gates[] = 'installed:' . ($statusOk ? 'ok' : 'FAIL');
            } else {
                $gates[] = 'installed:n/a';
            }

            // Gate 3 — activated on workspace
            $active = $addonManager->isActiveForWorkspace($ws, $mod);
            $gates[] = 'activated:' . ($active ? 'ok' : 'FAIL');

            // Gate 4 — plan entitlement
            $entitled = $addonManager->isPlanEntitled($ws, $mod);
            $gates[] = 'plan:' . ($entitled ? 'ok' : ($isSuper ? 'bypassed' : 'FAIL'));

            // Final
            $canUse = $addonManager->canUse($ws, $mod, $isSuper);
            $final  = ($canUse && $permView) ? 'ACCESSIBLE' : '403';

            line(sprintf('    %-12s %-10s  %s', $mod, $final, implode('  ', $gates)));
        }
    }
}

line();
line('══════════════════════════════════════════════════════');
line(' HOW TO READ THIS');
line('══════════════════════════════════════════════════════');
line(' perm:FAIL      → user has no role, or role lacks {module}.view permission');
line(' installed:FAIL → addons.status is not installed/enabled/active');
line(' activated:FAIL → no workspace_addons row with is_active=1');
line(' plan:FAIL      → organization.plan_id is null, or plan.modules omits this alias');
line();

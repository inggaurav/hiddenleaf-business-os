# Antigravity integration plan

Comparison basis: `integration/hiddenleaf-v1` versus `origin/ui/hiddenleaf-premium-design-system` at `f2b870f`. No merge has been performed.

## Ownership rules

- Backend routes, controller actions, authorization, validation, typed prop names, mutation payloads, and generated `backend-page-contracts.json` are **CORE CONTRACT** and must survive.
- Visual composition, tokens, animation, typography, responsive behavior, and reusable presentational primitives are **UI OWNED** when they preserve the contract.
- Dependency and entrypoint changes are **SHARED** and require a clean install, TypeScript, production build, Inertia contract validation, and browser smoke checks.

| Zone | Classification | Merge rule |
|---|---|---|
| `app/**`, `routes/**`, migrations and tests | CORE CONTRACT | Keep integration branch behavior and tests. UI branch should not replace these files. |
| `docs/ui/backend-page-contracts.json` | CORE CONTRACT | Regenerate after merge; every component and required top-level prop must remain valid. |
| `resources/js/Pages/**` | MANUAL MERGE REQUIRED | Start from premium implementation, then restore exact page paths, form payloads, methods, errors, and props from the contract. |
| `resources/js/Layouts/AppShell.tsx` | UI OWNED / SHARED DATA | Keep premium shell; preserve auth, workspace, notification, route, and flash props. |
| `resources/js/Components/**` | UI OWNED | Prefer premium primitives unless they remove mutation wiring or accessibility semantics. |
| `resources/js/app.tsx` | SHARED | Preserve Inertia resolver behavior and integration page paths; apply UI providers deliberately. |
| `resources/css/app.css` | UI OWNED | Premium tokens win after confirming Tailwind 4 compilation. |
| `package.json`, `package-lock.json` | MANUAL MERGE REQUIRED | Reconcile dependencies on the locked React 19/Inertia 3/Tailwind 4/Vite 8 stack; use `npm ci`, never force/legacy peer flags. |
| `vite.config.js` | SHARED | Retain Laravel/Inertia entrypoints and Tailwind plugin; accept compatible UI aliases/plugins. |
| Navigation registries | UI OWNED / CORE ROUTES | Premium navigation wins, but every target must exist in the generated route audit. |

## Known blockers before merge

The UI branch currently deletes required backend page contracts: `CRM/Index`, `HRM/Index`, `POS/Index`, `Taskly/Index`, all three `Landing/*` pages, and `Webhooks/Index`. It introduces `LandingPage/Index`, which does not match the frozen backend page names. These deletions must be reversed or their premium replacements must use the original paths.

The UI branch also contains a `MrFox` component tree and `AIAgent/Index`. Mr Fox is outside this v1 integration mission and must not be wired into the merge. The provider-neutral `AIAssistant/Index` contract remains authoritative.

Helpdesk introduces alternate flat page paths (`Helpdesk/Index`, `Create`, `Show`) while the backend uses `Helpdesk/Tickets/*` and `Helpdesk/Categories/*`. Premium code should be adapted to the existing backend paths rather than changing routes solely for visual reasons.

## Merge sequence when explicitly authorized

1. Tag/record both branch SHAs and create a temporary merge branch from `integration/hiddenleaf-v1`.
2. Merge without committing, resolve backend files in favor of integration, and resolve UI files using the table above.
3. Restore every page reported missing by `composer forensic:validate`.
4. Remove/defer Mr Fox-specific wiring and reconcile package locks without force flags.
5. Run SQLite and PostgreSQL suites, tenant attacks, route audit, parity validator, Pint, TypeScript, and Vite build.
6. Execute the browser smoke matrix and record only observed results.
7. Commit the merge only when critical/high route findings and missing page contracts are zero.

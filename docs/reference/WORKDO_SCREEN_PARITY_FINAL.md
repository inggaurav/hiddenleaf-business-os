# WorkDo Screen Parity — Evidence Gate

## Current status

**REFERENCE MAPPING REQUIRED — previous 48/48 screen claim invalidated.**

The prior screen inventory contained stale or nonexistent HiddenLeaf routes (for example `/productservice`, `/taskly/tasks`, `/helpdesk/tickets`) and therefore could not be used as release evidence. The JSON registry has been reset until screen evidence is regenerated from the live Laravel route/Inertia surface and then mapped to WorkDo reference screens.

## Current evidence process

1. Run `php artisan parity:validate --write` to generate the current HiddenLeaf route/controller snapshot.
2. Resolve the Inertia page rendered by each candidate screen.
3. Map each WorkDo reference screen to a current HiddenLeaf route/page.
4. Record permissions/module guards and the browser QA status separately.
5. Only mark a screen `VERIFIED` when its route/page exists and its WorkDo reference mapping is explicit.

## Browser evidence

Browser execution is tracked separately in `docs/qa/MODULE_BROWSER_VERIFICATION.md`. Automated tests do not automatically turn a screen into a browser `PASS`.

## Release rule

Do not report a 100% screen-parity percentage until the registry contains one evidence row per claimed WorkDo reference screen and all rows pass the route/page validator.

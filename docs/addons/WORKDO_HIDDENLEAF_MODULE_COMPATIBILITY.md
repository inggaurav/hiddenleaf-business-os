# WorkDo → HiddenLeaf Add-on Compatibility

## Decision

HiddenLeaf does **not** introduce a second module framework. It extends the WorkDo-style module runtime already present in the repository.

Existing foundations reused:

- `Addon` catalog model and `addons` table.
- `workspace_addons` for per-workspace activation/configuration.
- `UserActiveModule` for bundled/core module compatibility.
- SaaS `Plan.modules` as the server-side entitlement list.
- `ModuleContract`, `ModuleRegistry`, `BaseModule`, and `ModuleManager`.
- `SecureModuleInstaller` with signed `module.json`, checksum, core-version and safe-path validation.
- `module.status` middleware for route enforcement.
- `nwidart/laravel-modules` already present as a dependency.

## Lifecycle

Platform: `package → verify signature/checksum → install files → Addon catalog row`.

Workspace: `plan entitlement → dependency check → workspace_addons.is_active=true`.

Disable: `is_active=false`; business data is not deleted.

## Free/limited plans

There is no hard-coded unlimited fallback for add-ons. `Plan.modules` is authoritative. A free/trial plan exposes only module aliases explicitly included in that plan. This keeps limits configurable and requires no paid external entitlement service.

## HiddenLeaf extensions

A WorkDo-compatible `module.json` may optionally contain:

```json
{
  "hiddenleaf": {
    "mrfox": {"tools": ["Vendor\\Fleet\\Tools\\MaintenanceDueTool"]},
    "automations": {
      "triggers": ["Vendor\\Fleet\\Automation\\MaintenanceDueTrigger"],
      "actions": ["Vendor\\Fleet\\Automation\\CreateMaintenanceTask"]
    },
    "search": {"providers": ["Vendor\\Fleet\\Search\\VehicleSearchProvider"]},
    "command_center": {"signals": ["Vendor\\Fleet\\Signals\\MaintenanceOverdueSignal"]}
  }
}
```

The extension block is optional; an unchanged business add-on does not need Mr. Fox support to function.

## Security rule

Menu visibility is never the access boundary. Add-on routes and actions must pass workspace activation, plan entitlement and RBAC. Mr. Fox and Automation registries re-check module availability server-side.

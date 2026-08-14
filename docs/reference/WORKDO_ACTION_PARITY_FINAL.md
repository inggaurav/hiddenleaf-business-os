# WorkDo Granular Action Parity — Evidence Gate

## Current status

**REFERENCE MAPPING REQUIRED — do not treat the previous 128/128 claim as verified.**

The prior document reported 128/128 verified actions without storing 128 individual, resolvable evidence rows. That summary has been intentionally invalidated. HiddenLeaf now requires parity evidence to pass `php artisan parity:validate` before a VERIFIED count is accepted.

## What is currently evidence-backed

- The Account module has a live HiddenLeaf route/controller/test registry in `docs/reference/account-module-parity.json`.
- The registry is checked against Laravel's actual route collection, controller action names and test classes.
- `php artisan parity:validate --write` can generate the current HiddenLeaf route/controller evidence snapshot.
- WorkDo reference actions still need to be mapped row-by-row to those HiddenLeaf actions before the bundled-module parity total can be restored.

## Legacy reference counts awaiting evidence mapping

| Module | Legacy action count | Current parity status |
|---|---:|---|
| Account | 32 | HiddenLeaf evidence available; WorkDo reference mapping pending |
| ProductService / Inventory | 14 | Reference mapping pending |
| Sales | 15 | Reference mapping pending |
| Procurement | 13 | Reference mapping pending |
| HRM | 16 | Reference mapping pending |
| CRM / Lead | 12 | Reference mapping pending |
| Taskly | 10 | Reference mapping pending |
| POS | 8 | Reference mapping pending |
| Helpdesk | 4 | Reference mapping pending |
| Media | 2 | Reference mapping pending |
| Messenger | 2 | Reference mapping pending |
| **Legacy total** | **128** | **Not yet evidence-verified** |

## Release rule

A module may be reported as VERIFIED only when every claimed action has an individual record containing resolvable WorkDo reference evidence and live HiddenLeaf evidence (HTTP method, URI, controller method, permission/screen where applicable and test evidence). Summary percentages must be computed from those rows rather than typed manually.

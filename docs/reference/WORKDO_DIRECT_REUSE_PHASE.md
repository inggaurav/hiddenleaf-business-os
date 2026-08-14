# WorkDo Direct-Reuse Phase

Branch: `agent/workdo-direct-reuse`
Base: `integration/hiddenleaf-v1`

## Purpose

This branch stages only material that can be reused without coupling HiddenLeaf runtime code to WorkDo's tenancy, model, controller, middleware, frontend, or package-service-provider implementation.

## Imported now

- `config/workdo_direct_reuse.php`
  - 54 discovered module identities, aliases, package names, priorities, versions, and child-module relationships.
  - Default Staff, Client, and Vendor permission presets as functional contracts.
- `config/workdo_menu_finance.php`
  - Accounting, Budget Planner, Double Entry, and Goal menu/route/permission contracts.
- `config/workdo_menu_hr.php`
  - HRM, Performance, Recruitment, and Training menu/route/permission contracts.
- `config/workdo_menu_crm_projects.php`
  - CRM, Taskly/Projects, Timesheet, Calendar, and Form Builder menu/route/permission contracts.
- `config/workdo_menu_operations.php`
  - Contract, Quotation, POS, Product & Service, Support Ticket, Landing Page/CMS, and Zoom Meeting menu/route/permission contracts.
- `config/workdo_portable_dependencies.php`
  - Independently packaged PHP and JavaScript dependencies that can be obtained from their original package registries instead of copying `vendor/` or `node_modules/`.

## Deliberately not copied in this phase

The uploaded reference archive is named `erpgosaas-96nulled.rar`. Its top-level license file therefore is not treated as reliable evidence that commercial WorkDo application source may be redistributed. The following source remains reference-only until the adaptation phase:

- WorkDo controllers and services
- WorkDo Eloquent models
- WorkDo migrations/seeders that encode WorkDo tenancy assumptions
- WorkDo middleware and authorization implementation
- WorkDo package service providers
- WorkDo React/Blade pages and proprietary UI implementation
- WorkDo CSS, branding, icons, images, templates, and assets
- payment package implementation copied from the archive
- bundled `vendor/` or `node_modules/`

## Why this is runtime-safe

The files added in this phase are additive configuration/reference contracts and are not wired into HiddenLeaf's current NavigationRegistry, PermissionService, migrations, routes, or module runtime. Therefore they do not alter existing production behavior and do not bypass HiddenLeaf organization/workspace isolation.

## Next phase

Adapt these contracts into HiddenLeaf-native implementation in this order:

1. role/dashboard parity: Super Admin, Company/Admin, Staff/Team, Client, Vendor;
2. navigation and permission mapping;
3. Account/HRM/CRM/Taskly role-specific dashboards;
4. missing modules: Recruitment, Performance, Training, Contract, Budget Planner, Calendar, Form Builder, Goal, Quotation, Timesheet, Support Ticket/CMS;
5. integrations and payment gateways using original upstream SDKs/packages.

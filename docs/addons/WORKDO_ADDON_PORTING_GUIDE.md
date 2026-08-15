# WorkDo Add-on Porting Guide

The goal is mechanical reuse first, enhancement second.

## 1. Inventory source

Before editing code, record the source add-on's manifest/version/dependencies, models/tables/migrations, controllers/public methods, routes, permissions, menus, settings, views/assets, translations, jobs/events/schedules, reports and integrations.

No feature is considered intentionally absent until it is accounted for.

## 2. Preserve module identity

Keep the original module alias and permission vocabulary where safe. Preserve dependency relationships and business-data semantics. Do not merge an add-on into core.

## 3. Package

The current installer expects a signed `module.json` containing `id`, `name`, `alias`, `version`, `minimum_core`, `dependencies`, `checksum`, and `signature`. It validates package paths, symbolic links, size, checksum, signature and core compatibility before moving module files.

## 4. Install vs enable

Installation is global platform state. Enablement is per workspace and must pass: package installed → plan contains alias → dependencies active → workspace activation.

Disabling preserves module records.

## 5. HiddenLeaf enhancement

Only after WorkDo functional parity is established, optionally add manifest extension classes for Mr. Fox tools, automation triggers/actions, cross-domain search and Command Center signals. Every Mr. Fox tool must declare the appropriate module and permission.

## 6. Free-plan behavior

Never assume free means all add-ons. The existing SaaS plan's `modules` list controls access. The add-on framework does not require a paid marketplace or entitlement API.

## 7. Validation

- enabled tenant can use the module
- disabled tenant cannot use direct routes/APIs
- limited/free plan cannot activate an unentitled module
- dependencies are enforced
- disable/re-enable preserves data
- Mr. Fox cannot see disabled-module tools
- add-on automation cannot be created or executed after entitlement is removed
- WorkDo core parity remains green

## First parity module

Fleet is a useful first parity proof only after the **actual WorkDo Fleet source** is available in the build context. Do not invent a Fleet schema or feature set from marketing material.

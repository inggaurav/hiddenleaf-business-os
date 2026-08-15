# WorkDo Add-on Master Catalog

## Status

The HiddenLeaf repository contains the add-on runtime, but it does not contain the complete licensed WorkDo add-on source catalog. The catalog must therefore be generated from the reference source rather than guessed from marketing names.

## Required source-derived fields

For every add-on record: name, alias, version, category, source path, dependencies, models, controllers/public methods, routes, tables, permissions, menus/screens, settings and migration status.

Migration status must be one of:

- `NOT_STARTED`
- `ALREADY_COVERED`
- `PORT_REQUIRED`
- `PORT_IN_PROGRESS`
- `PORTED`
- `REPLACED_BY_MRFOX`
- `SKIP`

## Source-first rule

Do not claim a module is ported until its source inventory and parity manifest exist. AI Assistant, AI Business Advisor, AI Document and Workflow Automation should first be compared against existing Mr. Fox, Knowledge and Automation capabilities before deciding whether to port them or classify them as `REPLACED_BY_MRFOX`.

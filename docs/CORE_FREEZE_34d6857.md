# HiddenLeaf BusinessOS functional-core freeze

This is the authoritative record for the immutable Stage 1 functional-core baseline. Historical Stage 1 reports are preserved only as superseded audit artifacts.

## Source identity

- HiddenLeaf repository: `https://github.com/inggaurav/hiddenleaf-business-os`
- Frozen branch: `stage-1-workdo-core-real-build`
- Frozen core SHA: `34d68572a310c0131f6ff4478c9046c88dc80722`
- WorkDo functional reference SHA: `0b996a0050abcdffa2770a82fbb9261eeb2805bd`
- Integration branch created from this freeze: `integration/hiddenleaf-v1`

## Locked runtime

| Component | Frozen version |
|---|---:|
| PHP | 8.4.24 |
| Laravel | 13.25.0 |
| Inertia Laravel | 3.3.1 |
| Inertia React | 3.6.1 |
| React | 19.2.8 |
| Tailwind CSS | 4.3.3 |
| Vite | 8.2.1 |
| PostgreSQL | 17 (Compose/CI service) |
| Redis | 7 (Compose/CI service) |

## Reproduced freeze evidence

- Application routes: 334 (`artisan route:list --except-vendor --json`).
- SQLite suite: 130 tests, 569 assertions, 2 opt-in infrastructure tests skipped.
- PostgreSQL feature verification: all 130 tests executed in bounded groups, 577 assertions in aggregate, including the separately enabled infrastructure group.
- Live PostgreSQL/Redis infrastructure group: 2 tests, 8 assertions.
- PostgreSQL `migrate:fresh --seed`: passed across all 55 migrations present at the freeze.
- TypeScript (`tsc --noEmit`): passed.
- Vite production build: passed.
- Pint: passed.
- Composer validation and audit: passed with no advisories.
- npm audit: passed with no vulnerabilities.
- Stage 1 capability registry validator: 50 records validated.

These results establish the frozen baseline; they do not by themselves claim exhaustive route-for-route WorkDo parity. The v1 integration branch maintains a deeper forensic registry and executable QA audits for that purpose.

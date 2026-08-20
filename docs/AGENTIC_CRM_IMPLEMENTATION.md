# HiddenLeaf Agentic CRM Intelligence

## Purpose

This subsystem ports the useful architectural ideas from agent-first CRM systems into HiddenLeaf without copying their single-tenant assumptions. The CRM remains deterministic application infrastructure; Gideon is the intelligence runtime. Intelligence writes are durable, evidence-backed, tenant-scoped, reviewable, and replay-safe.

## Implemented architecture

`CRM/API -> AgentTask ledger -> worker lease -> direct or Gideon executor -> Finding -> deterministic evidence score -> ContactFact -> auto-apply / human review / reject -> optional reasoned recheck`

### Durable task queue

`agent_tasks` is the source of truth for background work. Workers claim due tasks with PostgreSQL `FOR UPDATE SKIP LOCKED`. Claims have expiring leases, so crashed workers do not permanently strand work. Expired leased tasks are reclaimable. Idempotency is enforced per organization and workspace.

### Two work lanes

- `direct`: deterministic tasks that should not spend model tokens, currently email, phone, and company-domain normalization.
- `research`: Gideon-backed research over the existing HMAC bridge. The request explicitly requires evidence and forbids model self-confidence from being treated as evidence.

### Evidence ledger

Every finding becomes an immutable `agent_evidence` row with source type, method, source URL, observation time, score, band, and metadata. Source classes are scored deterministically. First-party signed email, CRM history, calendar evidence, official company sites, and verified providers rank above generic web evidence. Model inference by itself is weak evidence.

High-strength source classes are accepted only when the source was attested by a deterministic/tool layer (`metadata.source_attested=true`). An LLM cannot promote its own claim by labeling it `first_party_signed_email`, `verified_provider`, or another strong source type; unattested claims are scored as model inference.

### Fact policy

- strong evidence: auto-applied
- medium evidence: queued for human review
- weak evidence: rejected as a fact but retained in the evidence ledger

Approved or newly auto-applied facts supersede previous active facts for the same tenant/entity/field rather than deleting history.

### Human review

The API controller exposes tenant-scoped pending facts and approve/reject actions. Review decisions record reviewer and time. Cross-workspace review attempts return no fact.

### Reasoned rechecks

A Gideon run can return `recheck_at` plus `recheck_reason`. The orchestrator writes a new durable task with a unique idempotency key and the parent task reference.

### Custom agents and tool safety

`AgentBuilder` creates version-hashed agent definitions with explicit tools, resources, and egress hosts. Unknown tools, wildcard hosts, and URL-shaped host entries are rejected. `ScopedToolGateway` checks both tool and resource scope before invocation. Agent definitions are persisted per organization/workspace.

### Multi-tenancy

All durable intelligence tables carry both `organization_id` and `workspace_id`. Queries are scoped by both. PostgreSQL RLS policies are included as defense in depth; production deployments should use a non-owner database role and set the HiddenLeaf tenant session variables when FORCE RLS is enabled.

## Runtime integration

Register `HiddenLeaf\AgenticCrm\AgenticCrmServiceProvider` in the real Laravel application bootstrap and run the migration `2026_08_20_000001_create_agentic_crm_intelligence_tables.php`.

Required production environment values:

- `GIDEON_AGENT_URL=https://...`
- `GIDEON_AGENT_HMAC_SECRET=<minimum 32 random characters>`
- `GIDEON_AGENT_TIMEOUT=45`
- `GIDEON_AGENT_ALLOW_HTTP=false`

The existing Gideon runtime must implement `POST /v1/agent/research`, validate the `X-HiddenLeaf-Timestamp` and `X-HiddenLeaf-Signature` HMAC, enforce replay-window checks, and return evidence-bearing findings. Tool adapters inside Gideon must stamp trustworthy findings with `metadata.source_attested=true` and an `attested_by` identifier. No database credentials are sent to Gideon.

## API permissions

- `crm.intelligence.view`
- `crm.intelligence.run`
- `crm.intelligence.review`
- `crm.intelligence.evidence.view`
- `crm.intelligence.tasks.view`
- `crm.intelligence.agents.manage`

The controller intentionally does not register public routes by itself. Routes must be placed behind the application's existing authentication, organization-context, workspace-context, and RBAC middleware rather than inventing a parallel auth path.

## Security invariants

1. Intelligence never bypasses organization/workspace scope.
2. Gideon never receives database credentials.
3. LLM self-confidence is never accepted as evidence.
4. LLM-provided source labels are not trusted without tool attestation.
5. Weak evidence never silently overwrites an active CRM fact.
6. Tool invocation is deny-by-default.
7. Network egress for custom agents is explicit-host allowlist only.
8. Worker leases expire and can be reclaimed after crashes.
9. Idempotent tasks cannot double-apply the same requested work within a tenant scope.
10. Fact history is superseded, not destroyed.
11. Human review is required for medium-strength evidence.

## Verification added

`tests/Feature/AgenticCrmTest.php` covers strong/medium/weak evidence policy, tool-source attestation, human approval, tenant-scoped idempotency, reasoned rechecks, custom-agent tool validation, and scoped tool invocation.

## Integration blocker in this repository mirror

The connected `hiddenleaf-business-os` master branch contains the Stage 1 architecture/kernel subset but not the complete runnable Laravel application bootstrap, Composer manifest, route files, or deployed CRM modules. For that reason this branch contains the production subsystem, migration, service provider, controller, worker, and tests, but it intentionally does not fabricate missing application bootstrap or auth middleware. Those final registration lines must be applied in the canonical full BusinessOS/BusiSync backend repository when that repository is connected.

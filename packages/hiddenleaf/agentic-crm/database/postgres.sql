BEGIN;

CREATE TABLE IF NOT EXISTS agent_tasks (
    id uuid PRIMARY KEY,
    organization_id bigint NOT NULL,
    workspace_id bigint NOT NULL,
    kind varchar(120) NOT NULL,
    lane varchar(20) NOT NULL CHECK (lane IN ('direct','research')),
    entity_type varchar(80) NOT NULL,
    entity_id varchar(120) NOT NULL,
    payload jsonb NOT NULL DEFAULT '{}'::jsonb,
    idempotency_key char(64) NOT NULL,
    status varchar(20) NOT NULL DEFAULT 'queued' CHECK (status IN ('queued','leased','completed','cancelled')),
    attempt integer NOT NULL DEFAULT 0,
    run_at timestamptz NOT NULL DEFAULT now(),
    leased_by varchar(160),
    lease_until timestamptz,
    last_error text,
    completed_at timestamptz,
    created_at timestamptz NOT NULL DEFAULT now(),
    updated_at timestamptz NOT NULL DEFAULT now(),
    UNIQUE (organization_id, workspace_id, idempotency_key)
);
CREATE INDEX IF NOT EXISTS agent_tasks_due_idx ON agent_tasks (organization_id, workspace_id, status, run_at);
CREATE INDEX IF NOT EXISTS agent_tasks_entity_idx ON agent_tasks (organization_id, workspace_id, entity_type, entity_id);

CREATE TABLE IF NOT EXISTS agent_evidence (
    id uuid PRIMARY KEY,
    organization_id bigint NOT NULL,
    workspace_id bigint NOT NULL,
    task_id uuid NOT NULL REFERENCES agent_tasks(id) ON DELETE CASCADE,
    entity_type varchar(80) NOT NULL,
    entity_id varchar(120) NOT NULL,
    field varchar(160) NOT NULL,
    value jsonb NOT NULL,
    source_type varchar(80) NOT NULL,
    method varchar(120) NOT NULL,
    source_url text,
    score smallint NOT NULL CHECK (score BETWEEN 0 AND 100),
    band varchar(20) NOT NULL CHECK (band IN ('strong','review','weak')),
    observed_at timestamptz NOT NULL,
    metadata jsonb NOT NULL DEFAULT '{}'::jsonb,
    created_at timestamptz NOT NULL DEFAULT now()
);
CREATE INDEX IF NOT EXISTS agent_evidence_entity_idx ON agent_evidence (organization_id, workspace_id, entity_type, entity_id, field);

CREATE TABLE IF NOT EXISTS contact_facts (
    id uuid PRIMARY KEY,
    organization_id bigint NOT NULL,
    workspace_id bigint NOT NULL,
    entity_type varchar(80) NOT NULL,
    entity_id varchar(120) NOT NULL,
    field varchar(160) NOT NULL,
    value jsonb NOT NULL,
    evidence_id uuid NOT NULL REFERENCES agent_evidence(id),
    score smallint NOT NULL CHECK (score BETWEEN 0 AND 100),
    status varchar(24) NOT NULL CHECK (status IN ('auto_applied','pending_review','rejected','approved','superseded')),
    observed_at timestamptz NOT NULL,
    reviewed_by bigint,
    reviewed_at timestamptz,
    superseded_at timestamptz,
    created_at timestamptz NOT NULL DEFAULT now(),
    updated_at timestamptz NOT NULL DEFAULT now()
);
CREATE INDEX IF NOT EXISTS contact_facts_active_idx ON contact_facts (organization_id, workspace_id, entity_type, entity_id, field, status);
CREATE INDEX IF NOT EXISTS contact_facts_review_idx ON contact_facts (organization_id, workspace_id, status, score DESC);

CREATE TABLE IF NOT EXISTS agent_definitions (
    id uuid PRIMARY KEY,
    organization_id bigint NOT NULL,
    workspace_id bigint NOT NULL,
    name varchar(160) NOT NULL,
    instructions text NOT NULL,
    allowed_tools jsonb NOT NULL DEFAULT '[]'::jsonb,
    allowed_resources jsonb NOT NULL DEFAULT '[]'::jsonb,
    allowed_hosts jsonb NOT NULL DEFAULT '[]'::jsonb,
    version_hash char(64) NOT NULL,
    enabled boolean NOT NULL DEFAULT true,
    created_by bigint NOT NULL,
    created_at timestamptz NOT NULL DEFAULT now(),
    updated_at timestamptz NOT NULL DEFAULT now(),
    UNIQUE (organization_id, workspace_id, name, version_hash)
);

ALTER TABLE agent_tasks ENABLE ROW LEVEL SECURITY;
ALTER TABLE agent_evidence ENABLE ROW LEVEL SECURITY;
ALTER TABLE contact_facts ENABLE ROW LEVEL SECURITY;
ALTER TABLE agent_definitions ENABLE ROW LEVEL SECURITY;

DO $$
BEGIN
    IF NOT EXISTS (SELECT 1 FROM pg_policies WHERE policyname='agent_tasks_tenant_scope') THEN
        CREATE POLICY agent_tasks_tenant_scope ON agent_tasks USING (
            organization_id = NULLIF(current_setting('hiddenleaf.organization_id', true), '')::bigint AND
            workspace_id = NULLIF(current_setting('hiddenleaf.workspace_id', true), '')::bigint
        );
    END IF;
    IF NOT EXISTS (SELECT 1 FROM pg_policies WHERE policyname='agent_evidence_tenant_scope') THEN
        CREATE POLICY agent_evidence_tenant_scope ON agent_evidence USING (
            organization_id = NULLIF(current_setting('hiddenleaf.organization_id', true), '')::bigint AND
            workspace_id = NULLIF(current_setting('hiddenleaf.workspace_id', true), '')::bigint
        );
    END IF;
    IF NOT EXISTS (SELECT 1 FROM pg_policies WHERE policyname='contact_facts_tenant_scope') THEN
        CREATE POLICY contact_facts_tenant_scope ON contact_facts USING (
            organization_id = NULLIF(current_setting('hiddenleaf.organization_id', true), '')::bigint AND
            workspace_id = NULLIF(current_setting('hiddenleaf.workspace_id', true), '')::bigint
        );
    END IF;
    IF NOT EXISTS (SELECT 1 FROM pg_policies WHERE policyname='agent_definitions_tenant_scope') THEN
        CREATE POLICY agent_definitions_tenant_scope ON agent_definitions USING (
            organization_id = NULLIF(current_setting('hiddenleaf.organization_id', true), '')::bigint AND
            workspace_id = NULLIF(current_setting('hiddenleaf.workspace_id', true), '')::bigint
        );
    END IF;
END $$;

COMMIT;

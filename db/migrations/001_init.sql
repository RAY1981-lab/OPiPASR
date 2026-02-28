diff --git a/db/migrations/001_init.sql b/db/migrations/001_init.sql
new file mode 100644
index 0000000000000000000000000000000000000000..3670b703a71640749f95788a9f7cc15a6460d039
--- /dev/null
+++ b/db/migrations/001_init.sql
@@ -0,0 +1,104 @@
+-- MVP schema for OPiPASR platform
+
+CREATE EXTENSION IF NOT EXISTS "pgcrypto";
+
+CREATE TABLE users (
+    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
+    username TEXT NOT NULL UNIQUE,
+    full_name TEXT NOT NULL,
+    role TEXT NOT NULL CHECK (role IN ('operator', 'chief', 'admin')),
+    is_active BOOLEAN NOT NULL DEFAULT TRUE,
+    created_at TIMESTAMPTZ NOT NULL DEFAULT now()
+);
+
+CREATE TABLE ibas_raw_events (
+    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
+    source_system TEXT NOT NULL,
+    source_event_id TEXT NOT NULL,
+    event_time TIMESTAMPTZ NOT NULL,
+    payload JSONB NOT NULL,
+    received_at TIMESTAMPTZ NOT NULL DEFAULT now(),
+    UNIQUE (source_system, source_event_id)
+);
+
+CREATE TABLE incidents (
+    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
+    external_ref TEXT,
+    incident_type TEXT NOT NULL,
+    status TEXT NOT NULL CHECK (status IN ('open', 'monitoring', 'closed')),
+    region_code TEXT,
+    latitude DOUBLE PRECISION,
+    longitude DOUBLE PRECISION,
+    started_at TIMESTAMPTZ NOT NULL,
+    updated_at TIMESTAMPTZ NOT NULL DEFAULT now()
+);
+
+CREATE TABLE incident_events (
+    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
+    incident_id UUID NOT NULL REFERENCES incidents(id) ON DELETE CASCADE,
+    raw_event_id UUID REFERENCES ibas_raw_events(id) ON DELETE SET NULL,
+    event_type TEXT NOT NULL,
+    severity SMALLINT CHECK (severity BETWEEN 1 AND 5),
+    event_time TIMESTAMPTZ NOT NULL,
+    normalized_payload JSONB NOT NULL,
+    created_at TIMESTAMPTZ NOT NULL DEFAULT now()
+);
+
+CREATE TABLE model_runs (
+    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
+    incident_id UUID NOT NULL REFERENCES incidents(id) ON DELETE CASCADE,
+    model_name TEXT NOT NULL,
+    model_version TEXT NOT NULL,
+    parameters JSONB NOT NULL DEFAULT '{}'::jsonb,
+    started_at TIMESTAMPTZ NOT NULL DEFAULT now(),
+    finished_at TIMESTAMPTZ,
+    status TEXT NOT NULL CHECK (status IN ('running', 'success', 'failed')),
+    error_message TEXT
+);
+
+CREATE TABLE forecasts (
+    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
+    incident_id UUID NOT NULL REFERENCES incidents(id) ON DELETE CASCADE,
+    model_run_id UUID NOT NULL REFERENCES model_runs(id) ON DELETE CASCADE,
+    horizon_minutes INTEGER NOT NULL CHECK (horizon_minutes > 0),
+    risk_level TEXT NOT NULL CHECK (risk_level IN ('low', 'medium', 'high', 'critical')),
+    probability NUMERIC(5,4) NOT NULL CHECK (probability >= 0 AND probability <= 1),
+    explanation JSONB NOT NULL DEFAULT '{}'::jsonb,
+    created_at TIMESTAMPTZ NOT NULL DEFAULT now()
+);
+
+CREATE TABLE recommendations (
+    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
+    incident_id UUID NOT NULL REFERENCES incidents(id) ON DELETE CASCADE,
+    model_run_id UUID NOT NULL REFERENCES model_runs(id) ON DELETE CASCADE,
+    priority SMALLINT NOT NULL CHECK (priority BETWEEN 1 AND 5),
+    action_text TEXT NOT NULL,
+    rationale TEXT,
+    created_at TIMESTAMPTZ NOT NULL DEFAULT now()
+);
+
+CREATE TABLE recommendation_decisions (
+    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
+    recommendation_id UUID NOT NULL REFERENCES recommendations(id) ON DELETE CASCADE,
+    decided_by UUID NOT NULL REFERENCES users(id) ON DELETE RESTRICT,
+    decision TEXT NOT NULL CHECK (decision IN ('accepted', 'rejected', 'modified')),
+    comment TEXT,
+    decided_at TIMESTAMPTZ NOT NULL DEFAULT now()
+);
+
+CREATE TABLE audit_log (
+    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
+    actor_user_id UUID REFERENCES users(id) ON DELETE SET NULL,
+    entity_type TEXT NOT NULL,
+    entity_id UUID,
+    action TEXT NOT NULL,
+    details JSONB NOT NULL DEFAULT '{}'::jsonb,
+    created_at TIMESTAMPTZ NOT NULL DEFAULT now()
+);
+
+CREATE INDEX idx_ibas_raw_events_event_time ON ibas_raw_events(event_time DESC);
+CREATE INDEX idx_incident_events_incident_id ON incident_events(incident_id);
+CREATE INDEX idx_model_runs_incident_id ON model_runs(incident_id);
+CREATE INDEX idx_forecasts_incident_id ON forecasts(incident_id);
+CREATE INDEX idx_recommendations_incident_id ON recommendations(incident_id);
+CREATE INDEX idx_audit_log_created_at ON audit_log(created_at DESC);

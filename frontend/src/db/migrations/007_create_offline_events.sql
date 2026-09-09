-- Migración 007: Crear tabla offline_events (Event Sourcing)
-- Tabla append-only para auditoría de eventos críticos

CREATE TABLE IF NOT EXISTS offline_events (
    -- Identificación del evento
    event_uuid TEXT PRIMARY KEY,
    idempotency_key TEXT NOT NULL UNIQUE,
    
    -- Contexto del evento (quién, dónde, cuándo)
    company_id TEXT NOT NULL,
    branch_id TEXT NOT NULL,
    terminal_id TEXT NOT NULL,
    user_id TEXT NOT NULL,
    
    -- Entidad afectada
    entity_type TEXT NOT NULL,  -- 'payment', 'movement', 'session', 'order'
    entity_uuid TEXT NOT NULL,  -- UUID de la entidad (payment_uuid, movement_uuid, etc.)
    
    -- Tipo de evento (acción realizada)
    event_type TEXT NOT NULL,   -- 'CREATE_PAYMENT', 'ADJUST_PAYMENT', 'CREATE_MOVEMENT', etc.
    
    -- Datos del evento (snapshot en el momento de creación)
    payload TEXT NOT NULL,      -- JSON con datos del evento
    
    -- Metadata
    created_at TEXT NOT NULL,   -- Timestamp inmutable
    notes TEXT,                 -- Notas opcionales
    
    -- Sincronización
    sync_status TEXT NOT NULL DEFAULT 'pending',  -- 'pending', 'syncing', 'synced', 'failed'
    cloud_event_id TEXT,        -- ID del evento en backend tras sync
    sync_error TEXT             -- Error si sync falló
);

-- Índices para queries comunes
CREATE INDEX IF NOT EXISTS idx_offline_events_entity 
ON offline_events(entity_type, entity_uuid);

CREATE INDEX IF NOT EXISTS idx_offline_events_sync_status 
ON offline_events(sync_status);

CREATE INDEX IF NOT EXISTS idx_offline_events_created_at 
ON offline_events(created_at);

CREATE INDEX IF NOT EXISTS idx_offline_events_branch 
ON offline_events(company_id, branch_id);

-- Garantía: la tabla es append-only (no UPDATE/DELETE)
-- Esto se aplica a nivel de aplicación, no de base de datos

-- Migración 008: Crear tabla local_print_jobs
-- Tabla para cola de impresión offline-first
-- Los jobs se crean localmente al momento del evento (cobro, comanda, etc.)
-- y se imprimen automáticamente cuando hay impresora disponible.

CREATE TABLE IF NOT EXISTS local_print_jobs (
    -- Identificación del job
    local_uuid TEXT PRIMARY KEY,
    cloud_id TEXT,  -- UUID del backend si existe (cuando se sincroniza)
    idempotency_key TEXT NOT NULL UNIQUE,
    
    -- Tipo de job
    job_type TEXT NOT NULL CHECK (job_type IN ('receipt', 'kitchen_command', 'bar_command')),
    
    -- Entidad origen (qué genera el print)
    entity_type TEXT NOT NULL,  -- 'order', 'bill', 'payment', 'cash_session'
    entity_uuid TEXT NOT NULL,  -- UUID de la entidad
    
    -- Datos del comprobante (JSON con toda la info para renderizar)
    payload TEXT NOT NULL,
    
    -- Bytes ESC/POS ya generados (base64). 
    -- Opcional: si no están, se generan desde payload al imprimir
    escpos_base64 TEXT,
    
    -- Metadata de impresora destino
    printer_name TEXT,  -- Nombre para UI
    printer_type TEXT CHECK (printer_type IN ('receipt', 'kitchen', 'bar')),
    
    -- Contexto multi-tenant
    company_id TEXT NOT NULL,
    branch_id TEXT NOT NULL,
    terminal_id TEXT,
    user_id TEXT NOT NULL,
    user_name TEXT,
    
    -- Número de orden/referencia (para mostrar en UI)
    reference_number TEXT,  -- ej: "Pedido #42", "Cuenta #15-1"
    
    -- Estado y retry
    status TEXT NOT NULL DEFAULT 'pending' CHECK (status IN ('pending', 'printing', 'completed', 'failed')),
    attempts INTEGER NOT NULL DEFAULT 0,
    max_attempts INTEGER NOT NULL DEFAULT 5,
    error_message TEXT,
    
    -- Timestamps
    created_at TEXT NOT NULL DEFAULT (datetime('now')),
    updated_at TEXT NOT NULL DEFAULT (datetime('now')),
    printed_at TEXT
);

-- Índices para queries frecuentes
CREATE INDEX IF NOT EXISTS idx_local_print_jobs_status 
    ON local_print_jobs(status);

CREATE INDEX IF NOT EXISTS idx_local_print_jobs_job_type 
    ON local_print_jobs(job_type);

CREATE INDEX IF NOT EXISTS idx_local_print_jobs_entity 
    ON local_print_jobs(entity_type, entity_uuid);

CREATE INDEX IF NOT EXISTS idx_local_print_jobs_created 
    ON local_print_jobs(created_at DESC);

CREATE INDEX IF NOT EXISTS idx_local_print_jobs_pending 
    ON local_print_jobs(status, created_at ASC)
    WHERE status = 'pending';

CREATE INDEX IF NOT EXISTS idx_local_print_jobs_branch 
    ON local_print_jobs(company_id, branch_id);

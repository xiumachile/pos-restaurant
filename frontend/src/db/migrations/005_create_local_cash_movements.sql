-- Migración 005: Crear tabla local_cash_movements
-- Registra movimientos de efectivo de la caja local
-- Convención: local_uuid/cloud_id (consistente con el resto)

CREATE TABLE IF NOT EXISTS local_cash_movements (
    -- Identificación
    local_uuid TEXT PRIMARY KEY,
    cloud_id TEXT,
    
    -- Tenant (obligatorio)
    company_id TEXT NOT NULL,
    branch_id TEXT NOT NULL,
    terminal_id TEXT,
    
    -- Relaciones
    cash_session_local_uuid TEXT NOT NULL,
    cash_session_cloud_id TEXT,
    user_id TEXT NOT NULL,
    user_name TEXT,
    
    -- Tipo de movimiento
    -- opening | payment | withdrawal | deposit | adjustment | closing
    type TEXT NOT NULL,
    
    -- Monto (positivo para entrada, negativo para salida)
    amount REAL NOT NULL,
    
    -- Balance después del movimiento (para auditoría)
    balance_after REAL NOT NULL,
    
    -- Metadata
    reason TEXT,
    notes TEXT,
    
    -- Referencia opcional (ej: payment que generó el movimiento)
    reference_type TEXT,
    reference_local_uuid TEXT,
    reference_cloud_id TEXT,
    
    -- Autorización (para ajustes/retiros)
    authorized_by TEXT,
    authorized_at TEXT,
    
    -- Sync
    idempotency_key TEXT NOT NULL,
    sync_status TEXT NOT NULL DEFAULT 'pending',
    sync_error TEXT,
    
    -- Timestamps
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- Índices para búsquedas comunes
CREATE INDEX IF NOT EXISTS idx_local_cash_movements_session ON local_cash_movements(cash_session_local_uuid);
CREATE INDEX IF NOT EXISTS idx_local_cash_movements_branch ON local_cash_movements(branch_id);
CREATE INDEX IF NOT EXISTS idx_local_cash_movements_type ON local_cash_movements(type);
CREATE INDEX IF NOT EXISTS idx_local_cash_movements_sync ON local_cash_movements(sync_status);
CREATE INDEX IF NOT EXISTS idx_local_cash_movements_created ON local_cash_movements(created_at);

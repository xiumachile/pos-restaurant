-- Migración 004: Crear tabla local_bills para modelo offline
-- Permite gestionar bills (cuentas) localmente sin internet
-- Convención: local_uuid/cloud_id (consistente con local_orders y local_payments)
-- Relaciones: order → bill → payment

CREATE TABLE IF NOT EXISTS local_bills (
    -- Identificación (convención del proyecto)
    local_uuid TEXT PRIMARY KEY,
    cloud_id TEXT,
    
    -- Tenant (obligatorio como en local_orders)
    company_id TEXT NOT NULL,
    branch_id TEXT NOT NULL,
    terminal_id TEXT,
    
    -- Relaciones con el order (dual: local + cloud, como en local_payments)
    order_local_uuid TEXT,
    order_cloud_id TEXT,
    
    -- Identificador humano de la bill
    bill_number TEXT NOT NULL,
    
    -- Montos (REAL como en el resto del proyecto)
    subtotal REAL NOT NULL DEFAULT 0,
    discount_total REAL NOT NULL DEFAULT 0,
    tax_total REAL NOT NULL DEFAULT 0,
    tip_amount REAL NOT NULL DEFAULT 0,
    grand_total REAL NOT NULL DEFAULT 0,
    
    -- Estado de pago
    paid_amount REAL NOT NULL DEFAULT 0,
    remaining_amount REAL NOT NULL DEFAULT 0,
    status TEXT NOT NULL DEFAULT 'open',
    
    -- Sincronización (convención del proyecto)
    idempotency_key TEXT NOT NULL,
    sync_status TEXT NOT NULL DEFAULT 'pending',
    sync_error TEXT,
    
    -- Metadata
    notes TEXT,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- Índices para búsquedas comunes
CREATE INDEX IF NOT EXISTS idx_local_bills_order_local_uuid ON local_bills(order_local_uuid);
CREATE INDEX IF NOT EXISTS idx_local_bills_order_cloud_id ON local_bills(order_cloud_id);
CREATE INDEX IF NOT EXISTS idx_local_bills_branch_id ON local_bills(branch_id);
CREATE INDEX IF NOT EXISTS idx_local_bills_status ON local_bills(status);
CREATE INDEX IF NOT EXISTS idx_local_bills_sync_status ON local_bills(sync_status);
CREATE INDEX IF NOT EXISTS idx_local_bills_cloud_id ON local_bills(cloud_id);

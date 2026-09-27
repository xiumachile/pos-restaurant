-- Migración 021: Recrear table_local_mutations con CHECK constraint
-- Propósito: Prevenir físicamente que se inserten estados inválidos como 'pending'

-- 1. Crear tabla temporal con el esquema correcto y CHECK constraint
CREATE TABLE IF NOT EXISTS table_local_mutations_new (
  table_uuid TEXT PRIMARY KEY,
  pending_status TEXT NOT NULL CHECK (pending_status IN ('available', 'occupied', 'reserved', 'maintenance')),
  pending_order_uuid TEXT,
  company_id TEXT NOT NULL,
  branch_id TEXT NOT NULL,
  action TEXT,
  payload TEXT,
  created_at TEXT DEFAULT CURRENT_TIMESTAMP
);

-- 2. Migrar datos existentes, corrigiendo los inválidos sobre la marcha
INSERT INTO table_local_mutations_new (table_uuid, pending_status, pending_order_uuid, company_id, branch_id, action, payload, created_at)
SELECT 
  table_uuid,
  CASE 
    WHEN pending_status = 'pending' AND pending_order_uuid IS NOT NULL AND pending_order_uuid != '' THEN 'occupied'
    WHEN pending_status = 'pending' THEN 'available'
    WHEN pending_status NOT IN ('available', 'occupied', 'reserved', 'maintenance') THEN 'available'
    ELSE pending_status
  END as pending_status,
  pending_order_uuid,
  COALESCE(company_id, 'default_company') as company_id,
  COALESCE(branch_id, 'default_branch') as branch_id,
  action,
  payload,
  created_at
FROM table_local_mutations;

-- 3. Reemplazar la tabla antigua
DROP TABLE table_local_mutations;
ALTER TABLE table_local_mutations_new RENAME TO table_local_mutations;

-- 4. Recrear índices si existen
CREATE INDEX IF NOT EXISTS idx_table_mutations_pending ON table_local_mutations(pending_status);
CREATE INDEX IF NOT EXISTS idx_table_mutations_tenant ON table_local_mutations(company_id, branch_id);

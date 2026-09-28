-- Migración 021: Recrear table_local_mutations con CHECK constraint
-- Propósito: Prevenir físicamente que se inserten estados inválidos como 'pending'
-- Tolerante a esquemas incompletos (columnas pueden no existir o ser NULL)

-- 1. Asegurar que las columnas necesarias existan antes de migrar
-- Usamos DEFAULT para evitar fallos si la tabla ya tiene filas (SQLite lo requiere)
ALTER TABLE table_local_mutations ADD COLUMN company_id TEXT DEFAULT 'default_company';
ALTER TABLE table_local_mutations ADD COLUMN branch_id TEXT DEFAULT 'default_branch';
ALTER TABLE table_local_mutations ADD COLUMN action TEXT DEFAULT 'update';
ALTER TABLE table_local_mutations ADD COLUMN payload TEXT DEFAULT NULL;

-- 2. Crear tabla temporal con el esquema correcto y CHECK constraint estricto
CREATE TABLE IF NOT EXISTS table_local_mutations_new (
  table_uuid TEXT PRIMARY KEY,
  pending_status TEXT NOT NULL CHECK (pending_status IN ('available', 'occupied', 'reserved', 'maintenance')),
  pending_order_uuid TEXT,
  company_id TEXT NOT NULL,
  branch_id TEXT NOT NULL,
  action TEXT DEFAULT 'update',
  payload TEXT DEFAULT NULL,
  created_at TEXT DEFAULT CURRENT_TIMESTAMP
);

-- 3. Migrar datos existentes de forma segura
-- Usamos COALESCE para garantizar que no haya NULLs en company_id y branch_id
INSERT OR IGNORE INTO table_local_mutations_new (table_uuid, pending_status, pending_order_uuid, company_id, branch_id, action, payload, created_at)
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
  COALESCE(action, 'update') as action,
  payload,
  created_at
FROM table_local_mutations;

-- 4. Reemplazar la tabla antigua
DROP TABLE table_local_mutations;
ALTER TABLE table_local_mutations_new RENAME TO table_local_mutations;

-- 5. Recrear índices
CREATE INDEX IF NOT EXISTS idx_table_mutations_pending ON table_local_mutations(pending_status);
CREATE INDEX IF NOT EXISTS idx_table_mutations_tenant ON table_local_mutations(company_id, branch_id);

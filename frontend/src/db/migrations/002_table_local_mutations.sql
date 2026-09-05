-- FASE 4: Modelo local/cloud de mesas
--
-- Esta tabla almacena mutaciones locales pendientes de sincronización.
-- Mientras exista una mutación para una mesa, PullEngine NO debe
-- sobrescribir el estado con datos del cloud.
--
-- Regla arquitectónica:
-- "Una mutación local pendiente nunca puede ser destruida por un Pull cloud."
--
-- Flujo:
-- 1. Usuario crea pedido offline → INSERT en table_local_mutations
--    (pending_status = 'occupied', pending_order_uuid = local_uuid)
-- 2. PullEngine detecta mutación → NO sobrescribe status de local_tables
-- 3. SyncEngine sincroniza pedido → backend dispara OrderConfirmed
--    → backend actualiza mesa a 'occupied'
-- 4. PullEngine trae mesa del cloud → status ya es 'occupied'
--    → DELETE de mutación (ya no es necesaria)
-- 5. local_tables refleja estado real del backend

-- Tabla de mutaciones locales pendientes
CREATE TABLE IF NOT EXISTS table_local_mutations (
  table_uuid TEXT PRIMARY KEY,
  pending_status TEXT NOT NULL,
  pending_order_uuid TEXT,
  created_at TEXT DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (table_uuid) REFERENCES local_tables(uuid) ON DELETE CASCADE,
  FOREIGN KEY (pending_order_uuid) REFERENCES local_orders(local_uuid) ON DELETE CASCADE
);

-- Índice para búsquedas rápidas por estado pendiente
CREATE INDEX IF NOT EXISTS idx_table_mutations_status ON table_local_mutations(pending_status);

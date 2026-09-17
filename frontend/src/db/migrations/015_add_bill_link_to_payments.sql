-- ═══════════════════════════════════════════════════════════════
-- MIGRACIÓN 015: Agregar bill_local_uuid a local_payments (ADR-019)
-- ═══════════════════════════════════════════════════════════════
--
-- Contexto (ADR-019):
-- En split bill offline, múltiples payments pueden aplicarse a diferentes
-- bills de un mismo order. Sin un vínculo explícito, al sincronizar con
-- el backend se pierde la estructura del split (el backend no sabe qué
-- payment va con qué bill).
--
-- Solución:
-- Agregar campo bill_local_uuid (nullable) en local_payments que liga
-- el payment a una bill específica. Los payments directos a order
-- (sin split) mantienen bill_local_uuid = NULL.
--
-- Contrato (invariants):
-- - bill_local_uuid es NULL para payments sin bill
-- - bill_local_uuid referencia a una bill del mismo order_local_uuid
-- - El SyncEngine propaga este campo al backend (FASE 4)

ALTER TABLE local_payments ADD COLUMN bill_local_uuid TEXT;

-- Índice para búsquedas por bill (útil para reportes y sync)
CREATE INDEX IF NOT EXISTS idx_local_payments_bill_local_uuid
ON local_payments(bill_local_uuid);

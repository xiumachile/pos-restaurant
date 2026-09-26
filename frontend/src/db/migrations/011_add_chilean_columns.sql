-- Migration 011: Add Chilean model columns (ADR-011)
-- 
-- ADR-011: Modelo chileno de montos
-- 
-- Esta migración asume que migración 010 ya convirtió REAL→INTEGER.
-- Solo agrega columnas nuevas del modelo chileno sin recrear tablas.

-- ═══════════════════════════════════════════════════════════════════════════════
-- local_orders: agregar columnas chilenas
-- ═══════════════════════════════════════════════════════════════════════════════

ALTER TABLE local_orders ADD COLUMN IF NOT EXISTS net_amount INTEGER DEFAULT 0;
ALTER TABLE local_orders ADD COLUMN IF NOT EXISTS amount_due INTEGER DEFAULT 0;
ALTER TABLE local_orders ADD COLUMN IF NOT EXISTS guest_count INTEGER DEFAULT 1;
ALTER TABLE local_orders ADD COLUMN IF NOT EXISTS terminal_id TEXT;
ALTER TABLE local_orders ADD COLUMN IF NOT EXISTS idempotency_key TEXT;

-- Calcular valores para filas existentes
UPDATE local_orders SET
  net_amount = CAST(ROUND(grand_total / 1.19) AS INTEGER),
  amount_due = CAST(ROUND(grand_total + tip_amount) AS INTEGER)
WHERE net_amount = 0 OR amount_due = 0;

-- Crear índice único para idempotency_key si no existe
CREATE UNIQUE INDEX IF NOT EXISTS idx_local_orders_idempotency 
  ON local_orders(idempotency_key);

-- ═══════════════════════════════════════════════════════════════════════════════
-- local_payments: agregar sale_amount
-- ═══════════════════════════════════════════════════════════════════════════════

ALTER TABLE local_payments ADD COLUMN IF NOT EXISTS sale_amount INTEGER DEFAULT 0;

UPDATE local_payments SET
  sale_amount = CAST(ROUND(amount - tip_amount) AS INTEGER)
WHERE sale_amount = 0;

-- ═══════════════════════════════════════════════════════════════════════════════
-- local_bills: agregar columnas chilenas
-- ═══════════════════════════════════════════════════════════════════════════════

ALTER TABLE local_bills ADD COLUMN IF NOT EXISTS net_amount INTEGER DEFAULT 0;
ALTER TABLE local_bills ADD COLUMN IF NOT EXISTS amount_due INTEGER DEFAULT 0;
ALTER TABLE local_bills ADD COLUMN IF NOT EXISTS company_id TEXT;
ALTER TABLE local_bills ADD COLUMN IF NOT EXISTS branch_id TEXT;
ALTER TABLE local_bills ADD COLUMN IF NOT EXISTS terminal_id TEXT;
ALTER TABLE local_bills ADD COLUMN IF NOT EXISTS idempotency_key TEXT;

UPDATE local_bills SET
  net_amount = CAST(ROUND(grand_total / 1.19) AS INTEGER),
  tax_total = CAST(ROUND(grand_total - (grand_total / 1.19)) AS INTEGER),
  amount_due = CAST(ROUND(grand_total + tip_amount) AS INTEGER)
WHERE net_amount = 0 OR amount_due = 0;

CREATE UNIQUE INDEX IF NOT EXISTS idx_local_bills_idempotency 
  ON local_bills(idempotency_key);

-- ═══════════════════════════════════════════════════════════════════════════════
-- local_cash_sessions: asegurar columnas de tenant
-- ═══════════════════════════════════════════════════════════════════════════════

-- company_id puede ya existir de migración 006, agregar solo si falta
-- SQLite no tiene IF NOT EXISTS para ALTER TABLE, así que usamos try-catch en schema.ts


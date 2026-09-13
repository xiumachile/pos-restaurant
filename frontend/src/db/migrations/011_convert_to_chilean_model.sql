-- Migration 011: Convert to Chilean POS model (ADR-011)
-- 
-- Cambios principales:
-- 1. Convertir REAL → INTEGER para todos los campos monetarios (ADR-010)
-- 2. Agregar semántica chilena: precios IVA incluido
-- 3. Nuevas columnas para modelo chileno:
--    - local_orders: net_amount, amount_due
--    - local_payments: sale_amount
--    - local_bills: amount_due
--
-- Semántica chilena (ADR-011):
-- - Precios de catálogo = IVA incluido
-- - grand_total = subtotal_gross - discount (IVA incluido)
-- - net_amount = grand_total / 1.19
-- - tax_amount = grand_total - net_amount
-- - tip_amount = propina (separada, no tributaria)
-- - amount_due = grand_total + tip_amount

-- ═══════════════════════════════════════════════════════════════════════════════
-- MIGRAR local_orders (REAL → INTEGER + nuevas columnas)
-- ═══════════════════════════════════════════════════════════════════════════════

ALTER TABLE local_orders ADD COLUMN net_amount INTEGER DEFAULT 0;
ALTER TABLE local_orders ADD COLUMN amount_due INTEGER DEFAULT 0;

-- Migrar datos existentes:
-- - Convertir REAL → INTEGER (redondeo)
-- - Calcular net_amount y amount_due basado en lógica actual
UPDATE local_orders SET
  subtotal = CAST(ROUND(subtotal) AS INTEGER),
  discount_total = CAST(ROUND(discount_total) AS INTEGER),
  tax_total = CAST(ROUND(tax_total) AS INTEGER),
  tip_amount = CAST(ROUND(tip_amount) AS INTEGER),
  grand_total = CAST(ROUND(grand_total) AS INTEGER),
  -- Lógica actual: grand_total = subtotal + tax (asume precios netos)
  -- Para migración: mantenemos grand_total, calculamos net_amount
  net_amount = CAST(ROUND(grand_total / 1.19) AS INTEGER),
  amount_due = CAST(ROUND(grand_total + tip_amount) AS INTEGER);

-- Recrear tabla con tipos INTEGER (SQLite no permite ALTER COLUMN)
CREATE TABLE local_orders_new (
  local_uuid TEXT PRIMARY KEY,
  cloud_id TEXT,
  company_id TEXT NOT NULL,
  branch_id TEXT NOT NULL,
  terminal_id TEXT,
  table_id TEXT,
  order_number TEXT NOT NULL,
  order_type TEXT DEFAULT 'dine_in',
  status TEXT DEFAULT 'draft',
  
  -- Montos brutos (IVA incluido)
  subtotal INTEGER NOT NULL DEFAULT 0,        -- subtotal_gross
  discount_total INTEGER DEFAULT 0,           -- discount_amount
  
  -- Desglose tributario (calculado)
  net_amount INTEGER NOT NULL DEFAULT 0,      -- subtotal / 1.19
  tax_amount INTEGER NOT NULL DEFAULT 0,      -- subtotal - net_amount
  
  -- Total de la venta (IVA incluido)
  grand_total INTEGER NOT NULL DEFAULT 0,     -- = subtotal - discount
  
  -- Propina (separada)
  tip_amount INTEGER DEFAULT 0,
  
  -- Total a cobrar
  amount_due INTEGER NOT NULL DEFAULT 0,      -- = grand_total + tip_amount
  
  -- Metadata
  guest_count INTEGER DEFAULT 1,
  waiter_id TEXT,
  waiter_name TEXT,
  notes TEXT,
  idempotency_key TEXT UNIQUE NOT NULL,
  sync_status TEXT DEFAULT 'pending',
  sync_error TEXT,
  created_at TEXT DEFAULT CURRENT_TIMESTAMP,
  updated_at TEXT DEFAULT CURRENT_TIMESTAMP
);

INSERT INTO local_orders_new SELECT * FROM local_orders;
DROP TABLE local_orders;
ALTER TABLE local_orders_new RENAME TO local_orders;

-- ═══════════════════════════════════════════════════════════════════════════════
-- MIGRAR local_order_items (REAL → INTEGER)
-- ═══════════════════════════════════════════════════════════════════════════════

UPDATE local_order_items SET
  unit_price = CAST(ROUND(unit_price) AS INTEGER),
  subtotal = CAST(ROUND(subtotal) AS INTEGER);

CREATE TABLE local_order_items_new (
  local_uuid TEXT PRIMARY KEY,
  order_local_uuid TEXT NOT NULL,
  cloud_id TEXT,
  product_id TEXT NOT NULL,
  product_name TEXT NOT NULL,
  quantity INTEGER NOT NULL DEFAULT 1,
  unit_price INTEGER NOT NULL,
  subtotal INTEGER NOT NULL,
  notes TEXT,
  kitchen_status TEXT DEFAULT 'pending',
  is_menu_item INTEGER DEFAULT 0,
  menu_item_id TEXT,
  created_at TEXT DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (order_local_uuid) REFERENCES local_orders(local_uuid) ON DELETE CASCADE
);

INSERT INTO local_order_items_new SELECT * FROM local_order_items;
DROP TABLE local_order_items;
ALTER TABLE local_order_items_new RENAME TO local_order_items;

-- ═══════════════════════════════════════════════════════════════════════════════
-- MIGRAR local_payments (REAL → INTEGER + sale_amount)
-- ═══════════════════════════════════════════════════════════════════════════════

ALTER TABLE local_payments ADD COLUMN sale_amount INTEGER DEFAULT 0;

UPDATE local_payments SET
  amount = CAST(ROUND(amount) AS INTEGER),
  tip_amount = CAST(ROUND(tip_amount) AS INTEGER),
  sale_amount = CAST(ROUND(amount - tip_amount) AS INTEGER);

CREATE TABLE local_payments_new (
  local_uuid TEXT PRIMARY KEY,
  cloud_id TEXT,
  company_id TEXT NOT NULL,
  branch_id TEXT NOT NULL,
  order_local_uuid TEXT,
  order_cloud_id TEXT,
  payment_method TEXT NOT NULL,
  
  -- Montos del pago
  amount INTEGER NOT NULL,           -- Total recibido
  sale_amount INTEGER NOT NULL,      -- Porción de venta
  tip_amount INTEGER DEFAULT 0,      -- Porción de propina
  
  -- Metadata
  reference_code TEXT,
  status TEXT DEFAULT 'pending',
  idempotency_key TEXT UNIQUE NOT NULL,
  sync_status TEXT DEFAULT 'pending',
  sync_error TEXT,
  created_at TEXT DEFAULT CURRENT_TIMESTAMP
);

INSERT INTO local_payments_new SELECT * FROM local_payments;
DROP TABLE local_payments;
ALTER TABLE local_payments_new RENAME TO local_payments;

-- ═══════════════════════════════════════════════════════════════════════════════
-- MIGRAR local_bills (REAL → INTEGER + amount_due)
-- ═══════════════════════════════════════════════════════════════════════════════

ALTER TABLE local_bills ADD COLUMN amount_due INTEGER DEFAULT 0;

UPDATE local_bills SET
  subtotal = CAST(ROUND(subtotal) AS INTEGER),
  discount_total = CAST(ROUND(discount_total) AS INTEGER),
  tax_total = CAST(ROUND(tax_total) AS INTEGER),
  tip_amount = CAST(ROUND(tip_amount) AS INTEGER),
  grand_total = CAST(ROUND(grand_total) AS INTEGER),
  paid_amount = CAST(ROUND(paid_amount) AS INTEGER),
  remaining_amount = CAST(ROUND(remaining_amount) AS INTEGER),
  amount_due = CAST(ROUND(grand_total + tip_amount) AS INTEGER);

CREATE TABLE local_bills_new (
  local_uuid TEXT PRIMARY KEY,
  cloud_id TEXT,
  company_id TEXT NOT NULL,
  branch_id TEXT NOT NULL,
  terminal_id TEXT,
  order_local_uuid TEXT,
  order_cloud_id TEXT,
  bill_number TEXT NOT NULL,
  
  -- Montos de la venta (IVA incluido)
  subtotal INTEGER NOT NULL DEFAULT 0,       -- subtotal_gross
  discount_total INTEGER NOT NULL DEFAULT 0,
  net_amount INTEGER NOT NULL DEFAULT 0,
  tax_amount INTEGER NOT NULL DEFAULT 0,
  grand_total INTEGER NOT NULL DEFAULT 0,    -- = subtotal - discount
  
  -- Propina (separada)
  tip_amount INTEGER NOT NULL DEFAULT 0,
  
  -- Total a cobrar
  amount_due INTEGER NOT NULL DEFAULT 0,     -- = grand_total + tip_amount
  
  -- Estado de pago
  paid_amount INTEGER NOT NULL DEFAULT 0,
  remaining_amount INTEGER NOT NULL DEFAULT 0,
  status TEXT NOT NULL DEFAULT 'open',
  
  -- Metadata
  idempotency_key TEXT NOT NULL,
  sync_status TEXT NOT NULL DEFAULT 'pending',
  sync_error TEXT,
  notes TEXT,
  created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

INSERT INTO local_bills_new SELECT * FROM local_bills;
DROP TABLE local_bills;
ALTER TABLE local_bills_new RENAME TO local_bills;

-- Recrear índices
CREATE INDEX idx_local_bills_order_local_uuid ON local_bills(order_local_uuid);
CREATE INDEX idx_local_bills_order_cloud_id ON local_bills(order_cloud_id);
CREATE INDEX idx_local_bills_branch_id ON local_bills(branch_id);
CREATE INDEX idx_local_bills_status ON local_bills(status);
CREATE INDEX idx_local_bills_sync_status ON local_bills(sync_status);
CREATE INDEX idx_local_bills_cloud_id ON local_bills(cloud_id);

-- ═══════════════════════════════════════════════════════════════════════════════
-- MIGRAR local_cash_sessions (REAL → INTEGER)
-- ═══════════════════════════════════════════════════════════════════════════════

UPDATE local_cash_sessions SET
  opening_amount = CAST(ROUND(opening_amount) AS INTEGER),
  closing_amount = CAST(ROUND(closing_amount) AS INTEGER);

CREATE TABLE local_cash_sessions_new (
  local_uuid TEXT PRIMARY KEY,
  cloud_id TEXT,
  branch_id TEXT NOT NULL,
  terminal_id TEXT,
  user_id TEXT NOT NULL,
  user_name TEXT,
  status TEXT DEFAULT 'open',
  opening_amount INTEGER DEFAULT 0,
  closing_amount INTEGER,
  opened_at TEXT DEFAULT CURRENT_TIMESTAMP,
  closed_at TEXT,
  sync_status TEXT DEFAULT 'pending',
  sync_error TEXT,
  created_at TEXT DEFAULT CURRENT_TIMESTAMP
);

INSERT INTO local_cash_sessions_new SELECT * FROM local_cash_sessions;
DROP TABLE local_cash_sessions;
ALTER TABLE local_cash_sessions_new RENAME TO local_cash_sessions;

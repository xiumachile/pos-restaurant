-- Migration 017: Enforce INTEGER for all money columns (ADR-010 / P1-001)
-- Chile usa CLP sin centavos fraccionarios. Todas las columnas de dinero DEBEN ser INTEGER.
-- Esta migración convierte REAL a INTEGER de forma defensiva (solo si la tabla existe).
-- VALIDACIÓN: Se hace en JavaScript antes de ejecutar esta migración.

-- ============================================
-- CONVERTIR local_orders (si existe)
-- ============================================
CREATE TABLE IF NOT EXISTS local_orders_int (local_uuid TEXT PRIMARY KEY, cloud_id TEXT, company_id TEXT NOT NULL, branch_id TEXT NOT NULL, terminal_id TEXT, table_id TEXT, order_number TEXT NOT NULL, order_type TEXT DEFAULT 'dine_in', status TEXT DEFAULT 'draft', subtotal INTEGER NOT NULL DEFAULT 0, discount_total INTEGER DEFAULT 0, tax_total INTEGER DEFAULT 0, tip_amount INTEGER DEFAULT 0, grand_total INTEGER NOT NULL DEFAULT 0, guest_count INTEGER DEFAULT 1, waiter_id TEXT, waiter_name TEXT, notes TEXT, idempotency_key TEXT UNIQUE NOT NULL, sync_status TEXT DEFAULT 'pending', sync_error TEXT, created_at TEXT DEFAULT CURRENT_TIMESTAMP, updated_at TEXT DEFAULT CURRENT_TIMESTAMP);
INSERT INTO local_orders_int SELECT local_uuid, cloud_id, company_id, branch_id, terminal_id, table_id, order_number, order_type, status, CAST(ROUND(subtotal) AS INTEGER), CAST(ROUND(discount_total) AS INTEGER), CAST(ROUND(tax_total) AS INTEGER), CAST(ROUND(tip_amount) AS INTEGER), CAST(ROUND(grand_total) AS INTEGER), guest_count, waiter_id, waiter_name, notes, idempotency_key, sync_status, sync_error, created_at, updated_at FROM local_orders WHERE NOT EXISTS (SELECT 1 FROM local_orders_int LIMIT 1);
DROP TABLE IF EXISTS local_orders;
ALTER TABLE local_orders_int RENAME TO local_orders;

-- ============================================
-- CONVERTIR local_order_items (si existe)
-- ============================================
CREATE TABLE IF NOT EXISTS local_order_items_int (local_uuid TEXT PRIMARY KEY, order_local_uuid TEXT NOT NULL, cloud_id TEXT, product_id TEXT NOT NULL, product_name TEXT NOT NULL, quantity INTEGER NOT NULL DEFAULT 1, unit_price INTEGER NOT NULL, subtotal INTEGER NOT NULL, notes TEXT, kitchen_status TEXT DEFAULT 'pending', is_menu_item INTEGER DEFAULT 0, menu_item_id TEXT, created_at TEXT DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY (order_local_uuid) REFERENCES local_orders(local_uuid) ON DELETE CASCADE);
INSERT INTO local_order_items_int SELECT local_uuid, order_local_uuid, cloud_id, product_id, product_name, quantity, CAST(ROUND(unit_price) AS INTEGER), CAST(ROUND(subtotal) AS INTEGER), notes, kitchen_status, is_menu_item, menu_item_id, created_at FROM local_order_items WHERE NOT EXISTS (SELECT 1 FROM local_order_items_int LIMIT 1);
DROP TABLE IF EXISTS local_order_items;
ALTER TABLE local_order_items_int RENAME TO local_order_items;

-- ============================================
-- CONVERTIR local_payments (si existe)
-- ============================================
CREATE TABLE IF NOT EXISTS local_payments_int (local_uuid TEXT PRIMARY KEY, cloud_id TEXT, company_id TEXT NOT NULL, branch_id TEXT NOT NULL, order_local_uuid TEXT, order_cloud_id TEXT, payment_method TEXT NOT NULL, amount INTEGER NOT NULL, tip_amount INTEGER DEFAULT 0, reference_code TEXT, status TEXT DEFAULT 'pending', idempotency_key TEXT UNIQUE NOT NULL, sync_status TEXT DEFAULT 'pending', sync_error TEXT, created_at TEXT DEFAULT CURRENT_TIMESTAMP);
INSERT INTO local_payments_int SELECT local_uuid, cloud_id, company_id, branch_id, order_local_uuid, order_cloud_id, payment_method, CAST(ROUND(amount) AS INTEGER), CAST(ROUND(tip_amount) AS INTEGER), reference_code, status, idempotency_key, sync_status, sync_error, created_at FROM local_payments WHERE NOT EXISTS (SELECT 1 FROM local_payments_int LIMIT 1);
DROP TABLE IF EXISTS local_payments;
ALTER TABLE local_payments_int RENAME TO local_payments;

-- ============================================
-- CONVERTIR local_bills (si existe)
-- ============================================
CREATE TABLE IF NOT EXISTS local_bills_int (local_uuid TEXT PRIMARY KEY, cloud_id TEXT, order_local_uuid TEXT NOT NULL, order_cloud_id TEXT, bill_number TEXT NOT NULL, subtotal INTEGER NOT NULL DEFAULT 0, discount_total INTEGER NOT NULL DEFAULT 0, tax_total INTEGER NOT NULL DEFAULT 0, tip_amount INTEGER NOT NULL DEFAULT 0, grand_total INTEGER NOT NULL DEFAULT 0, paid_amount INTEGER NOT NULL DEFAULT 0, remaining_amount INTEGER NOT NULL DEFAULT 0, status TEXT NOT NULL DEFAULT 'open', notes TEXT, sync_status TEXT NOT NULL DEFAULT 'pending', sync_error TEXT, created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY (order_local_uuid) REFERENCES local_orders(local_uuid) ON DELETE CASCADE);
INSERT INTO local_bills_int SELECT local_uuid, cloud_id, order_local_uuid, order_cloud_id, bill_number, CAST(ROUND(subtotal) AS INTEGER), CAST(ROUND(discount_total) AS INTEGER), CAST(ROUND(tax_total) AS INTEGER), CAST(ROUND(tip_amount) AS INTEGER), CAST(ROUND(grand_total) AS INTEGER), CAST(ROUND(paid_amount) AS INTEGER), CAST(ROUND(remaining_amount) AS INTEGER), status, notes, sync_status, sync_error, created_at, updated_at FROM local_bills WHERE NOT EXISTS (SELECT 1 FROM local_bills_int LIMIT 1);
DROP TABLE IF EXISTS local_bills;
ALTER TABLE local_bills_int RENAME TO local_bills;

-- ============================================
-- CONVERTIR local_cash_sessions (si existe)
-- ============================================
CREATE TABLE IF NOT EXISTS local_cash_sessions_int (local_uuid TEXT PRIMARY KEY, cloud_id TEXT, company_id TEXT NOT NULL, branch_id TEXT NOT NULL, terminal_id TEXT, user_id TEXT NOT NULL, user_name TEXT, status TEXT NOT NULL DEFAULT 'open', opening_amount INTEGER DEFAULT 0, closing_amount INTEGER, opened_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP, closed_at TEXT, sync_status TEXT NOT NULL DEFAULT 'pending', sync_error TEXT, created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP);
INSERT INTO local_cash_sessions_int SELECT local_uuid, cloud_id, company_id, branch_id, terminal_id, user_id, user_name, status, CAST(ROUND(opening_amount) AS INTEGER), CAST(ROUND(closing_amount) AS INTEGER), opened_at, closed_at, sync_status, sync_error, created_at FROM local_cash_sessions WHERE NOT EXISTS (SELECT 1 FROM local_cash_sessions_int LIMIT 1);
DROP TABLE IF EXISTS local_cash_sessions;
ALTER TABLE local_cash_sessions_int RENAME TO local_cash_sessions;

-- ============================================
-- CONVERTIR local_cash_movements (si existe)
-- ============================================
CREATE TABLE IF NOT EXISTS local_cash_movements_int (local_uuid TEXT PRIMARY KEY, cloud_id TEXT, cash_session_local_uuid TEXT NOT NULL, cash_session_cloud_id TEXT, type TEXT NOT NULL, amount INTEGER NOT NULL, balance_after INTEGER NOT NULL, reference_type TEXT, reference_local_uuid TEXT, reference_cloud_id TEXT, notes TEXT, sync_status TEXT NOT NULL DEFAULT 'pending', sync_error TEXT, created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY (cash_session_local_uuid) REFERENCES local_cash_sessions(local_uuid) ON DELETE CASCADE);
INSERT INTO local_cash_movements_int SELECT local_uuid, cloud_id, cash_session_local_uuid, cash_session_cloud_id, type, CAST(ROUND(amount) AS INTEGER), CAST(ROUND(balance_after) AS INTEGER), reference_type, reference_local_uuid, reference_cloud_id, notes, sync_status, sync_error, created_at FROM local_cash_movements WHERE NOT EXISTS (SELECT 1 FROM local_cash_movements_int LIMIT 1);
DROP TABLE IF EXISTS local_cash_movements;
ALTER TABLE local_cash_movements_int RENAME TO local_cash_movements;

-- ============================================
-- CONVERTIR local_products (si existe)
-- ============================================
CREATE TABLE IF NOT EXISTS local_products_int (local_uuid TEXT PRIMARY KEY, cloud_id TEXT, name TEXT NOT NULL, description TEXT, base_price INTEGER NOT NULL DEFAULT 0, tax_rate REAL DEFAULT 19.00, category_id TEXT, is_available INTEGER NOT NULL DEFAULT 1, sync_status TEXT NOT NULL DEFAULT 'pending', created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP);
INSERT INTO local_products_int SELECT local_uuid, cloud_id, name, description, CAST(ROUND(base_price) AS INTEGER), tax_rate, category_id, is_available, sync_status, created_at, updated_at FROM local_products WHERE NOT EXISTS (SELECT 1 FROM local_products_int LIMIT 1);
DROP TABLE IF EXISTS local_products;
ALTER TABLE local_products_int RENAME TO local_products;

-- Migration: Convert Money columns from REAL to INTEGER
-- ADR-010: Estrategia de integridad monetaria para CLP
-- 
-- Chile usa pesos sin centavos fraccionarios, por lo que:
-- - SQLite local: INTEGER (pesos enteros)
-- - Evita errores de punto flotante
-- - Consistente con helpers de Money
--
-- NOTA: tax_rate se mantiene REAL porque es un porcentaje (19.00), no dinero

-- ============================================
-- local_orders
-- ============================================

CREATE TABLE local_orders_new (
    local_uuid TEXT PRIMARY KEY,
    cloud_id TEXT,
    order_number TEXT NOT NULL,
    company_id TEXT NOT NULL,
    branch_id TEXT NOT NULL,
    table_id TEXT,
    waiter_id TEXT,
    waiter_name TEXT,
    order_type TEXT NOT NULL DEFAULT 'dine_in',
    status TEXT NOT NULL DEFAULT 'draft',
    subtotal INTEGER NOT NULL DEFAULT 0,
    discount_total INTEGER DEFAULT 0,
    tax_total INTEGER DEFAULT 0,
    tip_amount INTEGER DEFAULT 0,
    grand_total INTEGER NOT NULL DEFAULT 0,
    notes TEXT,
    sync_status TEXT NOT NULL DEFAULT 'pending',
    sync_error TEXT,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

INSERT INTO local_orders_new 
SELECT 
    local_uuid, cloud_id, order_number, company_id, branch_id, 
    table_id, waiter_id, waiter_name, order_type, status,
    CAST(ROUND(subtotal) AS INTEGER),
    CAST(ROUND(discount_total) AS INTEGER),
    CAST(ROUND(tax_total) AS INTEGER),
    CAST(ROUND(tip_amount) AS INTEGER),
    CAST(ROUND(grand_total) AS INTEGER),
    notes, sync_status, sync_error, created_at, updated_at
FROM local_orders;

DROP TABLE local_orders;
ALTER TABLE local_orders_new RENAME TO local_orders;

-- ============================================
-- local_order_items
-- ============================================

CREATE TABLE local_order_items_new (
    local_uuid TEXT PRIMARY KEY,
    cloud_id TEXT,
    order_local_uuid TEXT NOT NULL,
    order_cloud_id TEXT,
    product_id TEXT NOT NULL,
    product_name TEXT NOT NULL,
    quantity INTEGER NOT NULL,
    unit_price INTEGER NOT NULL,
    subtotal INTEGER NOT NULL,
    notes TEXT,
    sync_status TEXT NOT NULL DEFAULT 'pending',
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_local_uuid) REFERENCES local_orders(local_uuid) ON DELETE CASCADE
);

INSERT INTO local_order_items_new
SELECT 
    local_uuid, cloud_id, order_local_uuid, order_cloud_id,
    product_id, product_name, quantity,
    CAST(ROUND(unit_price) AS INTEGER),
    CAST(ROUND(subtotal) AS INTEGER),
    notes, sync_status, created_at
FROM local_order_items;

DROP TABLE local_order_items;
ALTER TABLE local_order_items_new RENAME TO local_order_items;

-- ============================================
-- local_payments
-- ============================================

CREATE TABLE local_payments_new (
    local_uuid TEXT PRIMARY KEY,
    cloud_id TEXT,
    order_local_uuid TEXT NOT NULL,
    order_cloud_id TEXT,
    payment_method TEXT NOT NULL,
    payment_method_uuid TEXT,
    amount INTEGER NOT NULL,
    tip_amount INTEGER DEFAULT 0,
    reference_code TEXT,
    notes TEXT,
    status TEXT NOT NULL DEFAULT 'pending',
    sync_status TEXT NOT NULL DEFAULT 'pending',
    sync_error TEXT,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_local_uuid) REFERENCES local_orders(local_uuid) ON DELETE CASCADE
);

INSERT INTO local_payments_new
SELECT 
    local_uuid, cloud_id, order_local_uuid, order_cloud_id,
    payment_method, payment_method_uuid,
    CAST(ROUND(amount) AS INTEGER),
    CAST(ROUND(tip_amount) AS INTEGER),
    reference_code, notes, status, sync_status, sync_error, created_at
FROM local_payments;

DROP TABLE local_payments;
ALTER TABLE local_payments_new RENAME TO local_payments;

-- ============================================
-- local_cash_sessions
-- ============================================

CREATE TABLE local_cash_sessions_new (
    local_uuid TEXT PRIMARY KEY,
    cloud_id TEXT,
    company_id TEXT NOT NULL,
    branch_id TEXT NOT NULL,
    terminal_id TEXT,
    user_id TEXT NOT NULL,
    user_name TEXT,
    status TEXT NOT NULL DEFAULT 'open',
    opening_amount INTEGER DEFAULT 0,
    closing_amount INTEGER,
    opened_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    closed_at TEXT,
    sync_status TEXT NOT NULL DEFAULT 'pending',
    sync_error TEXT,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

INSERT INTO local_cash_sessions_new
SELECT 
    local_uuid, cloud_id, company_id, branch_id, terminal_id,
    user_id, user_name, status,
    CAST(ROUND(opening_amount) AS INTEGER),
    CAST(ROUND(closing_amount) AS INTEGER),
    opened_at, closed_at, sync_status, sync_error, created_at
FROM local_cash_sessions;

DROP TABLE local_cash_sessions;
ALTER TABLE local_cash_sessions_new RENAME TO local_cash_sessions;

-- ============================================
-- local_bills
-- ============================================

CREATE TABLE local_bills_new (
    local_uuid TEXT PRIMARY KEY,
    cloud_id TEXT,
    order_local_uuid TEXT NOT NULL,
    order_cloud_id TEXT,
    bill_number TEXT NOT NULL,
    subtotal INTEGER NOT NULL DEFAULT 0,
    discount_total INTEGER NOT NULL DEFAULT 0,
    tax_total INTEGER NOT NULL DEFAULT 0,
    tip_amount INTEGER NOT NULL DEFAULT 0,
    grand_total INTEGER NOT NULL DEFAULT 0,
    paid_amount INTEGER NOT NULL DEFAULT 0,
    remaining_amount INTEGER NOT NULL DEFAULT 0,
    status TEXT NOT NULL DEFAULT 'open',
    notes TEXT,
    sync_status TEXT NOT NULL DEFAULT 'pending',
    sync_error TEXT,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_local_uuid) REFERENCES local_orders(local_uuid) ON DELETE CASCADE
);

INSERT INTO local_bills_new
SELECT 
    local_uuid, cloud_id, order_local_uuid, order_cloud_id, bill_number,
    CAST(ROUND(subtotal) AS INTEGER),
    CAST(ROUND(discount_total) AS INTEGER),
    CAST(ROUND(tax_total) AS INTEGER),
    CAST(ROUND(tip_amount) AS INTEGER),
    CAST(ROUND(grand_total) AS INTEGER),
    CAST(ROUND(paid_amount) AS INTEGER),
    CAST(ROUND(remaining_amount) AS INTEGER),
    status, notes, sync_status, sync_error, created_at, updated_at
FROM local_bills;

DROP TABLE local_bills;
ALTER TABLE local_bills_new RENAME TO local_bills;

-- ============================================
-- local_cash_movements
-- ============================================

CREATE TABLE local_cash_movements_new (
    local_uuid TEXT PRIMARY KEY,
    cloud_id TEXT,
    cash_session_local_uuid TEXT NOT NULL,
    cash_session_cloud_id TEXT,
    movement_type TEXT NOT NULL,
    amount INTEGER NOT NULL,
    balance_after INTEGER NOT NULL,
    reference_type TEXT,
    reference_local_uuid TEXT,
    reference_cloud_id TEXT,
    notes TEXT,
    sync_status TEXT NOT NULL DEFAULT 'pending',
    sync_error TEXT,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (cash_session_local_uuid) REFERENCES local_cash_sessions(local_uuid) ON DELETE CASCADE
);

INSERT INTO local_cash_movements_new
SELECT 
    local_uuid, cloud_id, cash_session_local_uuid, cash_session_cloud_id,
    movement_type,
    CAST(ROUND(amount) AS INTEGER),
    CAST(ROUND(balance_after) AS INTEGER),
    reference_type, reference_local_uuid, reference_cloud_id,
    notes, sync_status, sync_error, created_at
FROM local_cash_movements;

DROP TABLE local_cash_movements;
ALTER TABLE local_cash_movements_new RENAME TO local_cash_movements;

-- ============================================
-- local_products (catálogo - solo base_price)
-- NOTA: tax_rate se mantiene REAL porque es porcentaje
-- ============================================

CREATE TABLE local_products_new (
    local_uuid TEXT PRIMARY KEY,
    cloud_id TEXT,
    name TEXT NOT NULL,
    description TEXT,
    base_price INTEGER NOT NULL DEFAULT 0,
    tax_rate REAL DEFAULT 19.00,
    category_id TEXT,
    is_available INTEGER NOT NULL DEFAULT 1,
    sync_status TEXT NOT NULL DEFAULT 'pending',
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

INSERT INTO local_products_new
SELECT 
    local_uuid, cloud_id, name, description,
    CAST(ROUND(base_price) AS INTEGER),
    tax_rate,
    category_id, is_available, sync_status, created_at, updated_at
FROM local_products;

DROP TABLE local_products;
ALTER TABLE local_products_new RENAME TO local_products;

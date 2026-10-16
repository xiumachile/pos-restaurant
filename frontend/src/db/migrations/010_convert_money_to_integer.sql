-- Migration: Convert Money columns from REAL to INTEGER
-- ADR-010: Estrategia de integridad monetaria para CLP
--
-- Chile usa pesos sin centavos fraccionarios, por lo que:
-- - SQLite local: INTEGER (pesos enteros)
-- - Evita errores de punto flotante
-- - Consistente con helpers de Money
--
-- NOTA: tax_rate se mantiene REAL porque es un porcentaje (19.00), no dinero

-- Desactivar claves foráneas temporalmente para evitar CASCADE
-- durante la reconstrucción de tablas padre/hijo.
PRAGMA foreign_keys = OFF;


-- ============================================
-- local_orders
-- ============================================

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
    subtotal INTEGER NOT NULL DEFAULT 0,
    discount_total INTEGER DEFAULT 0,
    tax_total INTEGER DEFAULT 0,
    tip_amount INTEGER DEFAULT 0,
    grand_total INTEGER NOT NULL DEFAULT 0,
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

INSERT INTO local_orders_new
SELECT
    local_uuid, cloud_id, company_id, branch_id, terminal_id, table_id,
    order_number, order_type, status,
    CAST(ROUND(subtotal) AS INTEGER),
    CAST(ROUND(discount_total) AS INTEGER),
    CAST(ROUND(tax_total) AS INTEGER),
    CAST(ROUND(tip_amount) AS INTEGER),
    CAST(ROUND(grand_total) AS INTEGER),
    guest_count, waiter_id, waiter_name, notes, idempotency_key, sync_status, sync_error, created_at, updated_at
FROM local_orders;

DROP TABLE local_orders;
ALTER TABLE local_orders_new RENAME TO local_orders;

-- ============================================
-- local_order_items
-- ============================================

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

INSERT INTO local_order_items_new
SELECT
    local_uuid, order_local_uuid, cloud_id, product_id, product_name, quantity,
    CAST(ROUND(unit_price) AS INTEGER),
    CAST(ROUND(subtotal) AS INTEGER),
    notes, kitchen_status, is_menu_item, menu_item_id, created_at
FROM local_order_items;

DROP TABLE local_order_items;
ALTER TABLE local_order_items_new RENAME TO local_order_items;

-- ============================================
-- local_payments
-- ============================================

CREATE TABLE local_payments_new (
    local_uuid TEXT PRIMARY KEY,
    cloud_id TEXT,
    company_id TEXT NOT NULL,
    branch_id TEXT NOT NULL,
    order_local_uuid TEXT,
    order_cloud_id TEXT,
    bill_local_uuid TEXT,
    payment_method TEXT NOT NULL,
    payment_method_uuid TEXT,
    amount INTEGER NOT NULL,
    tip_amount INTEGER DEFAULT 0,
    total_amount INTEGER DEFAULT 0,
    reference_code TEXT,
    notes TEXT,
    status TEXT DEFAULT 'pending',
    idempotency_key TEXT UNIQUE NOT NULL,
    sync_status TEXT DEFAULT 'pending',
    sync_error TEXT,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP
);

INSERT INTO local_payments_new
SELECT
    local_uuid, cloud_id, company_id, branch_id, order_local_uuid, order_cloud_id,
    NULL as bill_local_uuid,
    payment_method,
    NULL as payment_method_uuid,
    CAST(ROUND(amount) AS INTEGER),
    CAST(ROUND(tip_amount) AS INTEGER),
    CAST(ROUND(amount + COALESCE(tip_amount, 0)) AS INTEGER) as total_amount,
    reference_code, NULL as notes, status, idempotency_key, sync_status, sync_error, created_at
FROM local_payments;

DROP TABLE local_payments;
ALTER TABLE local_payments_new RENAME TO local_payments;

-- ============================================
-- local_cash_sessions
-- ============================================

CREATE TABLE local_cash_sessions_new (
    local_uuid TEXT PRIMARY KEY,
    cloud_id TEXT,
    branch_id TEXT NOT NULL,
    terminal_id TEXT,
    user_id TEXT NOT NULL,
    user_name TEXT,
    status TEXT DEFAULT 'open',
    opening_amount INTEGER DEFAULT 0,
    closing_amount INTEGER DEFAULT 0,
    opened_at TEXT,
    closed_at TEXT,
    sync_status TEXT DEFAULT 'pending',
    created_at TEXT DEFAULT CURRENT_TIMESTAMP
);

INSERT INTO local_cash_sessions_new
SELECT
    local_uuid, cloud_id, branch_id, terminal_id, user_id, user_name, status,
    CAST(ROUND(opening_amount) AS INTEGER),
    CAST(ROUND(closing_amount) AS INTEGER),
    opened_at, closed_at, sync_status, created_at
FROM local_cash_sessions;

DROP TABLE local_cash_sessions;
ALTER TABLE local_cash_sessions_new RENAME TO local_cash_sessions;

-- ============================================
-- local_cash_movements
-- ============================================

CREATE TABLE local_cash_movements_new (
    local_uuid TEXT PRIMARY KEY,
    cloud_id TEXT,
    company_id TEXT NOT NULL,
    branch_id TEXT NOT NULL,
    terminal_id TEXT,
    cash_session_local_uuid TEXT,
    cash_session_cloud_id TEXT,
    type TEXT NOT NULL,
    amount INTEGER NOT NULL,
    balance_after INTEGER NOT NULL,
    reference_type TEXT,
    reference_local_uuid TEXT,
    reference_cloud_id TEXT,
    notes TEXT,
    reason TEXT,
    user_id TEXT,
    user_name TEXT,
    authorized_by TEXT,
    authorized_at TEXT,
    sync_status TEXT DEFAULT 'pending',
    idempotency_key TEXT UNIQUE NOT NULL,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP
);

INSERT INTO local_cash_movements_new
SELECT
    local_uuid, cloud_id, company_id, branch_id, terminal_id,
    cash_session_local_uuid, cash_session_cloud_id, type,
    CAST(ROUND(amount) AS INTEGER),
    CAST(ROUND(balance_after) AS INTEGER),
    reference_type, reference_local_uuid, reference_cloud_id,
    notes, reason, user_id, user_name, authorized_by, authorized_at,
    sync_status, idempotency_key, created_at
FROM local_cash_movements;

DROP TABLE local_cash_movements;
ALTER TABLE local_cash_movements_new RENAME TO local_cash_movements;

-- ============================================
-- local_bills
-- ============================================

CREATE TABLE local_bills_new (
    local_uuid TEXT PRIMARY KEY,
    cloud_id TEXT,
    company_id TEXT NOT NULL,
    branch_id TEXT NOT NULL,
    terminal_id TEXT,
    order_local_uuid TEXT,
    order_cloud_id TEXT,
    bill_number TEXT NOT NULL,
    subtotal INTEGER NOT NULL DEFAULT 0,
    discount_total INTEGER NOT NULL DEFAULT 0,
    tax_total INTEGER NOT NULL DEFAULT 0,
    tip_amount INTEGER NOT NULL DEFAULT 0,
    grand_total INTEGER NOT NULL DEFAULT 0,
    paid_amount INTEGER NOT NULL DEFAULT 0,
    remaining_amount INTEGER NOT NULL DEFAULT 0,
    status TEXT DEFAULT 'open',
    notes TEXT,
    idempotency_key TEXT UNIQUE NOT NULL,
    sync_status TEXT DEFAULT 'pending',
    sync_error TEXT,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP
);

INSERT INTO local_bills_new
SELECT
    local_uuid, cloud_id, company_id, branch_id, terminal_id,
    order_local_uuid, order_cloud_id, bill_number,
    CAST(ROUND(subtotal) AS INTEGER),
    CAST(ROUND(discount_total) AS INTEGER),
    CAST(ROUND(tax_total) AS INTEGER),
    CAST(ROUND(tip_amount) AS INTEGER),
    CAST(ROUND(grand_total) AS INTEGER),
    CAST(ROUND(paid_amount) AS INTEGER),
    CAST(ROUND(remaining_amount) AS INTEGER),
    status, notes, idempotency_key, sync_status, sync_error, created_at
FROM local_bills;

DROP TABLE local_bills;
ALTER TABLE local_bills_new RENAME TO local_bills;

-- ============================================
-- local_products
-- ============================================

CREATE TABLE local_products_new (
    uuid TEXT PRIMARY KEY,
    category_id TEXT,
    sku TEXT,
    name_translations TEXT,
    description_translations TEXT,
    base_price INTEGER NOT NULL DEFAULT 0,
    tax_rate REAL DEFAULT 19.00,
    is_combo INTEGER DEFAULT 0,
    kitchen_zone_id TEXT,
    is_active INTEGER DEFAULT 1,
    last_updated TEXT DEFAULT CURRENT_TIMESTAMP
);

INSERT INTO local_products_new
SELECT
    uuid, category_id, sku, name_translations, description_translations,
    CAST(ROUND(base_price) AS INTEGER),
    tax_rate,
    is_combo, kitchen_zone_id, is_active, last_updated
FROM local_products;

DROP TABLE local_products;
ALTER TABLE local_products_new RENAME TO local_products;


-- Reactivar claves foráneas
PRAGMA foreign_keys = ON;
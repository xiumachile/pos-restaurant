-- Migration 026: Create local_customers table
-- Stores customer data for delivery/takeaway orders.
-- Supports lookup by phone number for quick order creation.

CREATE TABLE IF NOT EXISTS local_customers (
    uuid TEXT PRIMARY KEY,
    company_id TEXT NOT NULL,
    branch_id TEXT NOT NULL,
    phone TEXT NOT NULL,
    name TEXT NOT NULL,
    delivery_address TEXT,
    delivery_reference TEXT,
    notes TEXT,
    is_active INTEGER DEFAULT 1,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT DEFAULT CURRENT_TIMESTAMP
);

-- Índice único por teléfono + branch (búsqueda rápida)
CREATE UNIQUE INDEX IF NOT EXISTS idx_local_customers_phone_branch 
    ON local_customers(phone, branch_id);

-- Índice para búsqueda por nombre
CREATE INDEX IF NOT EXISTS idx_local_customers_name 
    ON local_customers(name);

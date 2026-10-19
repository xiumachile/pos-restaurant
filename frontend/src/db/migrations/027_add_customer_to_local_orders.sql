-- Migration 027: Add customer fields to local_orders
-- Usamos IF NOT EXISTS compatible con SQLite moderno, o ALTER TABLE directo
ALTER TABLE local_orders ADD COLUMN customer_name TEXT;
ALTER TABLE local_orders ADD COLUMN customer_phone TEXT;
ALTER TABLE local_orders ADD COLUMN customer_address TEXT;
ALTER TABLE local_orders ADD COLUMN customer_reference TEXT;

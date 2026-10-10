-- Migración 023: Agregar campos de cliente a local_orders
-- Soporte para pedidos delivery con datos del cliente (customer_id, customer_name, etc.)
--
-- ADR: Los datos de cliente se guardan localmente para:
-- 1. Permitir creación offline de pedidos delivery
-- 2. Sincronizar al backend cuando hay conexión
-- 3. Mantener consistencia con el flujo CRM

ALTER TABLE local_orders ADD COLUMN customer_id TEXT;
ALTER TABLE local_orders ADD COLUMN customer_name TEXT;
ALTER TABLE local_orders ADD COLUMN customer_phone TEXT;
ALTER TABLE local_orders ADD COLUMN delivery_address TEXT;
ALTER TABLE local_orders ADD COLUMN delivery_notes TEXT;

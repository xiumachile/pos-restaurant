-- Migration 023: Add customer and delivery columns to local_orders
--
-- ADR-0XX: Soporte para Delivery y Takeout en pedidos locales
--
-- Estas columnas permiten almacenar la información del cliente y la entrega
-- directamente en el pedido, facilitando la sincronización con el backend.

ALTER TABLE local_orders ADD COLUMN customer_name TEXT;
ALTER TABLE local_orders ADD COLUMN customer_phone TEXT;
ALTER TABLE local_orders ADD COLUMN delivery_address TEXT;
ALTER TABLE local_orders ADD COLUMN delivery_notes TEXT;

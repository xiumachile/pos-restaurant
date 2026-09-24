-- ============================================================================
-- P1-001: Normalización de datos monetarios (DATA MIGRATION)
-- ============================================================================
-- 
-- NOTA TÉCNICA CRÍTICA:
-- Esta migración NO cambia el esquema de la base de datos (afinidad de columnas).
-- El cambio de esquema de REAL/DECIMAL a INTEGER ya fue realizado previamente 
-- por la migración 010 (reconstrucción de tablas).
--
-- PROPÓSITO DE ESTA MIGRACIÓN:
-- 1. Normalizar valores residuales que puedan haber quedado con decimales 
--    (ej. 1500.00) convirtiéndolos estrictamente a INTEGER.
-- 2. Garantizar la coherencia total con el contrato monetario INTEGER antes 
--    de que el sistema procese o sincronice estos registros.
--
-- Esta es una migración de DATOS, no de ESQUEMA.
-- ============================================================================

-- local_orders
UPDATE local_orders SET subtotal = CAST(ROUND(subtotal) AS INTEGER) WHERE subtotal IS NOT NULL;
UPDATE local_orders SET discount_total = CAST(ROUND(discount_total) AS INTEGER) WHERE discount_total IS NOT NULL;
UPDATE local_orders SET tax_total = CAST(ROUND(tax_total) AS INTEGER) WHERE tax_total IS NOT NULL;
UPDATE local_orders SET tip_amount = CAST(ROUND(tip_amount) AS INTEGER) WHERE tip_amount IS NOT NULL;
UPDATE local_orders SET grand_total = CAST(ROUND(grand_total) AS INTEGER) WHERE grand_total IS NOT NULL;

-- local_order_items
UPDATE local_order_items SET unit_price = CAST(ROUND(unit_price) AS INTEGER) WHERE unit_price IS NOT NULL;
UPDATE local_order_items SET subtotal = CAST(ROUND(subtotal) AS INTEGER) WHERE subtotal IS NOT NULL;

-- local_payments
UPDATE local_payments SET amount = CAST(ROUND(amount) AS INTEGER) WHERE amount IS NOT NULL;
UPDATE local_payments SET tip_amount = CAST(ROUND(tip_amount) AS INTEGER) WHERE tip_amount IS NOT NULL;

-- local_bills
UPDATE local_bills SET subtotal = CAST(ROUND(subtotal) AS INTEGER) WHERE subtotal IS NOT NULL;
UPDATE local_bills SET discount_total = CAST(ROUND(discount_total) AS INTEGER) WHERE discount_total IS NOT NULL;
UPDATE local_bills SET tax_total = CAST(ROUND(tax_total) AS INTEGER) WHERE tax_total IS NOT NULL;
UPDATE local_bills SET tip_amount = CAST(ROUND(tip_amount) AS INTEGER) WHERE tip_amount IS NOT NULL;
UPDATE local_bills SET grand_total = CAST(ROUND(grand_total) AS INTEGER) WHERE grand_total IS NOT NULL;
UPDATE local_bills SET paid_amount = CAST(ROUND(paid_amount) AS INTEGER) WHERE paid_amount IS NOT NULL;
UPDATE local_bills SET remaining_amount = CAST(ROUND(remaining_amount) AS INTEGER) WHERE remaining_amount IS NOT NULL;

-- local_cash_sessions
UPDATE local_cash_sessions SET opening_amount = CAST(ROUND(opening_amount) AS INTEGER) WHERE opening_amount IS NOT NULL;
UPDATE local_cash_sessions SET closing_amount = CAST(ROUND(closing_amount) AS INTEGER) WHERE closing_amount IS NOT NULL;

-- local_cash_movements
UPDATE local_cash_movements SET amount = CAST(ROUND(amount) AS INTEGER) WHERE amount IS NOT NULL;
UPDATE local_cash_movements SET balance_after = CAST(ROUND(balance_after) AS INTEGER) WHERE balance_after IS NOT NULL;

-- local_products
UPDATE local_products SET base_price = CAST(ROUND(base_price) AS INTEGER) WHERE base_price IS NOT NULL;

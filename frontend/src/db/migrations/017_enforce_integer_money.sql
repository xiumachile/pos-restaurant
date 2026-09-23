-- Migration 017: Enforce INTEGER values for all money columns (ADR-010 / P1-001)
-- Chile usa CLP sin centavos fraccionarios. Todos los valores de dinero DEBEN ser enteros.
--
-- ESTRATEGIA: En SQLite, los tipos son afinidades, no restricciones estrictas.
-- En lugar de recrear tablas (lo que pierde columnas de migraciones posteriores),
-- simplemente redondeamos todos los valores existentes a enteros.
-- Esto es seguro porque:
-- 1. SQLite no enforce tipos estrictamente
-- 2. Los valores enteros en columnas REAL se comportan como enteros
-- 3. El backend PostgreSQL ya usa INTEGER
-- 4. La validación de fraccionarios se hace en JavaScript antes de esta migración

-- ============================================
-- REDONDEAR VALORES EN local_orders
-- ============================================
UPDATE local_orders SET subtotal = CAST(ROUND(subtotal) AS INTEGER), discount_total = CAST(ROUND(discount_total) AS INTEGER), tax_total = CAST(ROUND(tax_total) AS INTEGER), tip_amount = CAST(ROUND(tip_amount) AS INTEGER), grand_total = CAST(ROUND(grand_total) AS INTEGER);

-- ============================================
-- REDONDEAR VALORES EN local_order_items
-- ============================================
UPDATE local_order_items SET unit_price = CAST(ROUND(unit_price) AS INTEGER), subtotal = CAST(ROUND(subtotal) AS INTEGER);

-- ============================================
-- REDONDEAR VALORES EN local_payments
-- ============================================
UPDATE local_payments SET amount = CAST(ROUND(amount) AS INTEGER), tip_amount = CAST(ROUND(tip_amount) AS INTEGER);

-- ============================================
-- REDONDEAR VALORES EN local_bills
-- ============================================
UPDATE local_bills SET subtotal = CAST(ROUND(subtotal) AS INTEGER), discount_total = CAST(ROUND(discount_total) AS INTEGER), tax_total = CAST(ROUND(tax_total) AS INTEGER), tip_amount = CAST(ROUND(tip_amount) AS INTEGER), grand_total = CAST(ROUND(grand_total) AS INTEGER), paid_amount = CAST(ROUND(paid_amount) AS INTEGER), remaining_amount = CAST(ROUND(remaining_amount) AS INTEGER);

-- ============================================
-- REDONDEAR VALORES EN local_cash_sessions
-- ============================================
UPDATE local_cash_sessions SET opening_amount = CAST(ROUND(opening_amount) AS INTEGER), closing_amount = CAST(ROUND(closing_amount) AS INTEGER);

-- ============================================
-- REDONDEAR VALORES EN local_cash_movements
-- ============================================
UPDATE local_cash_movements SET amount = CAST(ROUND(amount) AS INTEGER), balance_after = CAST(ROUND(balance_after) AS INTEGER);

-- ============================================
-- REDONDEAR VALORES EN local_products
-- ============================================
UPDATE local_products SET base_price = CAST(ROUND(base_price) AS INTEGER);

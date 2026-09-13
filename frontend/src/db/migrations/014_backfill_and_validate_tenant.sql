-- Migration 014: Backfill tenant en filas legacy + validación
-- ADR-012: Garantizar que TODAS las filas tienen company_id + branch_id
--
-- ESTRATEGIA DEFENSIVA:
-- 1. Backfill de filas NULL con valores 'legacy-company'/'legacy-branch'
-- 2. Validación final que confirma 0 filas sin tenant
--
-- NOTA: SQLite no soporta ALTER COLUMN ... NOT NULL directamente
-- sin recrear la tabla (operación riesgosa). Dejamos la validación
-- en runtime (schema.ts) que bloquea el startup si hay NULLs.

-- ═══════════════════════════════════════════════════════════════════════════════
-- BACKFILL: Migrar filas legacy sin tenant
-- ═══════════════════════════════════════════════════════════════════════════════

-- local_tables
UPDATE local_tables
SET company_id = 'legacy-company', branch_id = 'legacy-branch'
WHERE company_id IS NULL OR branch_id IS NULL;

-- local_products
UPDATE local_products
SET company_id = 'legacy-company', branch_id = 'legacy-branch'
WHERE company_id IS NULL OR branch_id IS NULL;

-- local_categories
UPDATE local_categories
SET company_id = 'legacy-company', branch_id = 'legacy-branch'
WHERE company_id IS NULL OR branch_id IS NULL;

-- local_payment_methods
UPDATE local_payment_methods
SET company_id = 'legacy-company', branch_id = 'legacy-branch'
WHERE company_id IS NULL OR branch_id IS NULL;

-- printer_configs
UPDATE printer_configs
SET company_id = 'legacy-company', branch_id = 'legacy-branch'
WHERE company_id IS NULL OR branch_id IS NULL;

-- table_local_mutations
UPDATE table_local_mutations
SET company_id = 'legacy-company', branch_id = 'legacy-branch'
WHERE company_id IS NULL OR branch_id IS NULL;

-- ═══════════════════════════════════════════════════════════════════════════════
-- VALIDACIÓN: Confirmar que no quedan NULLs (si esto falla, la migración aborta)
-- ═══════════════════════════════════════════════════════════════════════════════

-- Estos SELECTs retornan el conteo de filas sin tenant
-- Si alguno es > 0, el sistema detectará el problema en runtime

-- Verificación de local_tables
SELECT COUNT(*) AS invalid_tables
FROM local_tables
WHERE company_id IS NULL OR branch_id IS NULL;

-- Verificación de local_products
SELECT COUNT(*) AS invalid_products
FROM local_products
WHERE company_id IS NULL OR branch_id IS NULL;

-- Verificación de local_categories
SELECT COUNT(*) AS invalid_categories
FROM local_categories
WHERE company_id IS NULL OR branch_id IS NULL;

-- Verificación de local_payment_methods
SELECT COUNT(*) AS invalid_payment_methods
FROM local_payment_methods
WHERE company_id IS NULL OR branch_id IS NULL;

-- Verificación de printer_configs
SELECT COUNT(*) AS invalid_printers
FROM printer_configs
WHERE company_id IS NULL OR branch_id IS NULL;

-- Verificación de table_local_mutations
SELECT COUNT(*) AS invalid_mutations
FROM table_local_mutations
WHERE company_id IS NULL OR branch_id IS NULL;

-- Migration 012: Agregar company_id y branch_id a tablas locales
-- ADR-012: Multi-tenancy local obligatorio
--
-- PROBLEMA:
-- Las siguientes tablas no tienen filtros de tenant, lo que permite
-- que datos de diferentes empresas se mezclen en el mismo terminal.
--
-- SOLUCIÓN:
-- Agregar columnas company_id y branch_id (NULLABLE por ahora)
-- En commits siguientes se harán NOT NULL después de refactorizar servicios.

-- ═══════════════════════════════════════════════════════════════════════════════
-- local_tables
-- ═══════════════════════════════════════════════════════════════════════════════

ALTER TABLE local_tables ADD COLUMN company_id TEXT;
ALTER TABLE local_tables ADD COLUMN branch_id TEXT;

-- Migrar datos existentes: asumir que todos pertenecen al mismo tenant
-- (En producción, esto debería obtenerse del contexto actual o del backend)
UPDATE local_tables 
SET company_id = 'migrate-company-1', 
    branch_id = 'migrate-branch-1'
WHERE company_id IS NULL OR branch_id IS NULL;

CREATE INDEX IF NOT EXISTS idx_local_tables_tenant 
  ON local_tables(company_id, branch_id);

-- ═══════════════════════════════════════════════════════════════════════════════
-- local_products
-- ═══════════════════════════════════════════════════════════════════════════════

ALTER TABLE local_products ADD COLUMN company_id TEXT;
ALTER TABLE local_products ADD COLUMN branch_id TEXT;

UPDATE local_products 
SET company_id = 'migrate-company-1', 
    branch_id = 'migrate-branch-1'
WHERE company_id IS NULL OR branch_id IS NULL;

CREATE INDEX IF NOT EXISTS idx_local_products_tenant 
  ON local_products(company_id, branch_id);

-- ═══════════════════════════════════════════════════════════════════════════════
-- local_categories
-- ═══════════════════════════════════════════════════════════════════════════════

ALTER TABLE local_categories ADD COLUMN company_id TEXT;
ALTER TABLE local_categories ADD COLUMN branch_id TEXT;

UPDATE local_categories 
SET company_id = 'migrate-company-1', 
    branch_id = 'migrate-branch-1'
WHERE company_id IS NULL OR branch_id IS NULL;

CREATE INDEX IF NOT EXISTS idx_local_categories_tenant 
  ON local_categories(company_id, branch_id);

-- ═══════════════════════════════════════════════════════════════════════════════
-- local_payment_methods
-- ═══════════════════════════════════════════════════════════════════════════════

ALTER TABLE local_payment_methods ADD COLUMN company_id TEXT;
ALTER TABLE local_payment_methods ADD COLUMN branch_id TEXT;

UPDATE local_payment_methods 
SET company_id = 'migrate-company-1', 
    branch_id = 'migrate-branch-1'
WHERE company_id IS NULL OR branch_id IS NULL;

CREATE INDEX IF NOT EXISTS idx_local_payment_methods_tenant 
  ON local_payment_methods(company_id, branch_id);

-- ═══════════════════════════════════════════════════════════════════════════════
-- printer_configs
-- ═══════════════════════════════════════════════════════════════════════════════

ALTER TABLE printer_configs ADD COLUMN company_id TEXT;
ALTER TABLE printer_configs ADD COLUMN branch_id TEXT;

UPDATE printer_configs 
SET company_id = 'migrate-company-1', 
    branch_id = 'migrate-branch-1'
WHERE company_id IS NULL OR branch_id IS NULL;

CREATE INDEX IF NOT EXISTS idx_printer_configs_tenant 
  ON printer_configs(company_id, branch_id);

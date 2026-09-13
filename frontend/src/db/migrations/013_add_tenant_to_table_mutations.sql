-- Migration 013: Agregar company_id y branch_id a table_local_mutations
-- ADR-012: Multi-tenancy local obligatorio
--
-- table_local_mutations es el "overlay" de estado local de mesas.
-- Sin filtros de tenant, mutaciones de Empresa 1 pueden afectar mesas
-- de Empresa 2 en el mismo terminal.

ALTER TABLE table_local_mutations ADD COLUMN company_id TEXT;
ALTER TABLE table_local_mutations ADD COLUMN branch_id TEXT;

UPDATE table_local_mutations 
SET company_id = 'migrate-company-1', 
    branch_id = 'migrate-branch-1'
WHERE company_id IS NULL OR branch_id IS NULL;

CREATE INDEX IF NOT EXISTS idx_table_mutations_tenant 
  ON table_local_mutations(company_id, branch_id);

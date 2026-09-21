-- ═══════════════════════════════════════════════════════════════════════════════
-- MIGRACIÓN 016: Fix orphan local_tables (ADR-012)
-- ═══════════════════════════════════════════════════════════════════════════════
-- 
-- Problema: PullEngine.ts tiene 6 INSERT OR REPLACE en local_tables,
-- pero solo 1 incluye company_id/branch_id. Las otras 5 ramas crean
-- filas sin tenant, causando violaciones de ADR-012.
--
-- Solución: DELETE las filas sin tenant. El siguiente sync las recreará
-- con el tenant correcto desde getCurrentTenantContext().
--
-- Razón de DELETE sobre UPDATE:
-- - No sabemos qué tenant "debería" tener cada mesa
-- - El sync las recreará con el tenant correcto
-- - Más seguro que "secuestrar" datos de otro tenant
--
-- ═══════════════════════════════════════════════════════════════════════════════

-- Contar filas afectadas (para logging)
SELECT COUNT(*) AS orphan_count 
FROM local_tables 
WHERE company_id IS NULL OR branch_id IS NULL;

-- Eliminar filas sin tenant
DELETE FROM local_tables 
WHERE company_id IS NULL OR branch_id IS NULL;

-- Verificación post-cleanup
SELECT COUNT(*) AS remaining_orphans 
FROM local_tables 
WHERE company_id IS NULL OR branch_id IS NULL;

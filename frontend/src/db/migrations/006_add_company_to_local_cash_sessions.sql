-- Migración 006: Agregar company_id a local_cash_sessions
-- Caja offline debe quedar ligada inequívocamente a:
-- company + branch + terminal + user/cashier

ALTER TABLE local_cash_sessions ADD COLUMN company_id TEXT;

-- Backfill defensivo para sesiones existentes.
-- En instalaciones reales puede no saberse la empresa histórica.
UPDATE local_cash_sessions
SET company_id = 'unknown'
WHERE company_id IS NULL OR company_id = '';

-- Índices de seguridad operacional.
CREATE INDEX IF NOT EXISTS idx_local_cash_sessions_company
ON local_cash_sessions(company_id);

CREATE INDEX IF NOT EXISTS idx_local_cash_sessions_scope
ON local_cash_sessions(company_id, branch_id, terminal_id, user_id, status);

CREATE INDEX IF NOT EXISTS idx_local_cash_sessions_active
ON local_cash_sessions(company_id, branch_id, terminal_id, user_id, status, opened_at);

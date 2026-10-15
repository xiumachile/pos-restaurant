-- Migración 023: Añadir columna total_amount a local_payments
-- Propósito: Alinear el esquema con el código de dominio que espera esta columna
-- para validaciones de integridad financiera (amount + tip_amount == total_amount)

ALTER TABLE local_payments ADD COLUMN total_amount INTEGER DEFAULT 0;

-- Actualizar registros existentes para que total_amount = amount + tip_amount
UPDATE local_payments SET total_amount = amount + COALESCE(tip_amount, 0) WHERE total_amount = 0;

-- Migración 024: Añadir columna updated_at a table_local_mutations
-- Propósito: Alinear el esquema con el código de dominio que espera esta columna
-- para registrar correctamente la hora de la última mutación de estado de mesa.

ALTER TABLE table_local_mutations ADD COLUMN updated_at TEXT DEFAULT CURRENT_TIMESTAMP;

-- Actualizar registros existentes para que updated_at coincida con created_at
UPDATE table_local_mutations SET updated_at = created_at WHERE updated_at IS NULL;

-- Migración incremental para asegurar que la tabla migrations tenga la columna checksum.
-- Esto repara instalaciones antiguas que ejecutaron 000_create_migrations_table antes de la corrección.
-- El migrador de Rust está configurado para ignorar "duplicate column name", haciéndolo 100% idempotente.
ALTER TABLE migrations ADD COLUMN checksum TEXT;

-- AUD-04 FIX: Reparar bases de datos existentes agregando la columna checksum.
-- El runner de Rust ignora silenciosamente "duplicate column name", por lo que es seguro.
ALTER TABLE migrations ADD COLUMN checksum TEXT;

-- Migración 003: Agregar columna backend_id a local_categories
-- Permite filtrado correcto por categoría en modo offline

-- Intentar agregar la columna, ignorar si ya existe
-- SQLite no soporta IF NOT EXISTS en ALTER TABLE, así que
-- el código TypeScript maneja el error "duplicate column"

ALTER TABLE local_categories ADD COLUMN backend_id INTEGER;

CREATE INDEX IF NOT EXISTS idx_local_categories_backend_id 
  ON local_categories(backend_id);

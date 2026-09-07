-- Migración 003: Agregar backend_id a local_categories
--
-- PROBLEMA:
-- Los productos tienen category_id como número (ej: "5.0" = ID del backend)
-- pero local_categories solo guardaba el UUID, causando mismatch en JOINs
-- y filtros por categoría en modo offline.
--
-- SOLUCIÓN:
-- Agregar columna backend_id INTEGER que almacena el ID original del backend.
-- PullEngine lo guarda al sincronizar desde el endpoint /catalog.
--
-- Esto permite que:
--   - UI use cat.id (backend_id) para filtros
--   - listProducts compare product.category_id con category.backend_id
--   - Mantener uuid como identificador único para sincronización

ALTER TABLE local_categories ADD COLUMN backend_id INTEGER;

-- Índice para búsquedas rápidas por backend_id
CREATE INDEX IF NOT EXISTS idx_local_categories_backend_id ON local_categories(backend_id);

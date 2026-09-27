-- Migración 019: Corregir estados inválidos en local_tables
-- Causa: Migraciones destructivas anteriores asignaron 'pending' (un estado de sync) 
-- a la columna 'status' en lugar de a 'sync_status'.
-- 
-- Esta migración es ultra-conservadora: solo corrige el status, no toca sync_status
-- porque esa columna podría no existir en algunas instalaciones.

-- 1. Corregir mesas con status = 'pending' a 'available'
UPDATE local_tables 
SET status = 'available' 
WHERE status = 'pending';

-- 2. Corregir mesas con status NULL a 'available'
UPDATE local_tables 
SET status = 'available' 
WHERE status IS NULL;

-- 3. Corregir cualquier otro estado inválido conocido
UPDATE local_tables 
SET status = 'available' 
WHERE status NOT IN ('available', 'occupied', 'reserved', 'maintenance');

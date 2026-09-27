-- Migración 020: Limpiar mutaciones de mesa con estado inválido
-- Causa: Migraciones destructivas anteriores (010/011) corrompieron datos
-- Efecto: Las mesas tenían pending_status = 'pending' en lugar de estados válidos

-- Corregir mutaciones con estado inválido
UPDATE table_local_mutations 
SET pending_status = CASE 
  WHEN pending_order_uuid IS NOT NULL AND pending_order_uuid != '' THEN 'occupied'
  ELSE 'available'
END
WHERE pending_status = 'pending' 
   OR pending_status NOT IN ('available', 'occupied', 'reserved', 'maintenance');

-- ═══════════════════════════════════════════════════════
-- MIGRACIÓN 009: Configuración de impresoras
-- ═══════════════════════════════════════════════════════
--
-- Tabla que persiste la configuración de impresoras por terminal.
-- Cada impresora tiene un tipo (receipt/kitchen/bar) y opcionalmente
-- puede ser marcada como default para ese tipo.
--
-- Ejemplo de uso:
--   - Una impresora de tickets (receipt) por defecto para la caja
--   - Una impresora de cocina (kitchen) para comandas
--   - Una impresora de bar (bar) para bebidas

CREATE TABLE IF NOT EXISTS printer_configs (
  local_uuid TEXT PRIMARY KEY,
  printer_type TEXT NOT NULL CHECK (printer_type IN ('receipt', 'kitchen', 'bar')),
  name TEXT NOT NULL,
  ip TEXT NOT NULL,
  port INTEGER NOT NULL DEFAULT 9100,
  is_default INTEGER NOT NULL DEFAULT 0,
  is_active INTEGER NOT NULL DEFAULT 1,
  notes TEXT,
  created_at TEXT DEFAULT CURRENT_TIMESTAMP,
  updated_at TEXT DEFAULT CURRENT_TIMESTAMP
);

-- Índice para buscar por tipo + activa
CREATE INDEX IF NOT EXISTS idx_printer_configs_type_active
  ON printer_configs(printer_type, is_active);

-- Índice para buscar la default de cada tipo
CREATE INDEX IF NOT EXISTS idx_printer_configs_default
  ON printer_configs(printer_type, is_default);

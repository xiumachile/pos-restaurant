-- Migración 001: Esquema inicial para offline-first
-- Versión: 1.0.0
-- Fecha: 2026-08-20

-- Configuración WAL para concurrencia (ejecutar al abrir)
-- PRAGMA journal_mode=WAL;
-- PRAGMA synchronous=NORMAL;
-- PRAGMA foreign_keys=ON;

-- Tabla de control de migraciones
CREATE TABLE IF NOT EXISTS migrations (
  version TEXT PRIMARY KEY,
  applied_at TEXT DEFAULT CURRENT_TIMESTAMP,
  checksum TEXT
);

-- Pedidos locales (estructura espejo del cloud)
CREATE TABLE IF NOT EXISTS local_orders (
  local_uuid TEXT PRIMARY KEY,
  cloud_id TEXT,
  company_id TEXT NOT NULL,
  branch_id TEXT NOT NULL,
  terminal_id TEXT,
  table_id TEXT,
  order_number TEXT NOT NULL,
  order_type TEXT DEFAULT 'dine_in',
  status TEXT DEFAULT 'draft',
  subtotal REAL NOT NULL DEFAULT 0,
  discount_total REAL DEFAULT 0,
  tax_total REAL DEFAULT 0,
  tip_amount REAL DEFAULT 0,
  grand_total REAL NOT NULL DEFAULT 0,
  guest_count INTEGER DEFAULT 1,
  waiter_id TEXT,
  waiter_name TEXT,
  notes TEXT,
  idempotency_key TEXT UNIQUE NOT NULL,
  sync_status TEXT DEFAULT 'pending',
  sync_error TEXT,
  created_at TEXT DEFAULT CURRENT_TIMESTAMP,
  updated_at TEXT DEFAULT CURRENT_TIMESTAMP
);

-- Items de pedidos locales
CREATE TABLE IF NOT EXISTS local_order_items (
  local_uuid TEXT PRIMARY KEY,
  order_local_uuid TEXT NOT NULL,
  cloud_id TEXT,
  product_id TEXT NOT NULL,
  product_name TEXT NOT NULL,
  quantity INTEGER NOT NULL DEFAULT 1,
  unit_price REAL NOT NULL,
  subtotal REAL NOT NULL,
  notes TEXT,
  kitchen_status TEXT DEFAULT 'pending',
  is_menu_item INTEGER DEFAULT 0,
  menu_item_id TEXT,
  created_at TEXT DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (order_local_uuid) REFERENCES local_orders(local_uuid) ON DELETE CASCADE
);

-- Pagos locales
CREATE TABLE IF NOT EXISTS local_payments (
  local_uuid TEXT PRIMARY KEY,
  cloud_id TEXT,
  company_id TEXT NOT NULL,
  branch_id TEXT NOT NULL,
  order_local_uuid TEXT,
  order_cloud_id TEXT,
  payment_method TEXT NOT NULL,
  amount REAL NOT NULL,
  tip_amount REAL DEFAULT 0,
  reference_code TEXT,
  status TEXT DEFAULT 'pending',
  idempotency_key TEXT UNIQUE NOT NULL,
  sync_status TEXT DEFAULT 'pending',
  sync_error TEXT,
  created_at TEXT DEFAULT CURRENT_TIMESTAMP
);

-- Sesiones de caja locales
CREATE TABLE IF NOT EXISTS local_cash_sessions (
  local_uuid TEXT PRIMARY KEY,
  cloud_id TEXT,
  branch_id TEXT NOT NULL,
  terminal_id TEXT,
  user_id TEXT NOT NULL,
  user_name TEXT,
  status TEXT DEFAULT 'open',
  opening_amount REAL DEFAULT 0,
  closing_amount REAL,
  opened_at TEXT DEFAULT CURRENT_TIMESTAMP,
  closed_at TEXT,
  sync_status TEXT DEFAULT 'pending',
  created_at TEXT DEFAULT CURRENT_TIMESTAMP
);

-- Estado de mesas (cache local)
CREATE TABLE IF NOT EXISTS local_tables (
  uuid TEXT PRIMARY KEY,
  table_number TEXT NOT NULL,
  area_name TEXT,
  capacity INTEGER DEFAULT 4,
  status TEXT DEFAULT 'available',
  current_order_uuid TEXT,
  last_updated TEXT DEFAULT CURRENT_TIMESTAMP
);

-- Catálogo cache (solo lectura, se sincroniza con cloud)
CREATE TABLE IF NOT EXISTS local_categories (
  uuid TEXT PRIMARY KEY,
  name_translations TEXT,
  sort_order INTEGER DEFAULT 0,
  is_active INTEGER DEFAULT 1,
  last_updated TEXT DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS local_products (
  uuid TEXT PRIMARY KEY,
  category_id TEXT,
  sku TEXT,
  name_translations TEXT,
  description_translations TEXT,
  base_price REAL NOT NULL DEFAULT 0,
  tax_rate REAL DEFAULT 19.00,
  is_combo INTEGER DEFAULT 0,
  kitchen_zone_id TEXT,
  is_active INTEGER DEFAULT 1,
  last_updated TEXT DEFAULT CURRENT_TIMESTAMP
);

-- Métodos de pago cache
CREATE TABLE IF NOT EXISTS local_payment_methods (
  uuid TEXT PRIMARY KEY,
  code TEXT NOT NULL,
  type TEXT NOT NULL,
  is_active INTEGER DEFAULT 1,
  last_updated TEXT DEFAULT CURRENT_TIMESTAMP
);

-- Cola de sincronización (eventos pendientes de enviar al cloud)
CREATE TABLE IF NOT EXISTS sync_queue (
  id TEXT PRIMARY KEY,
  company_id TEXT NOT NULL,
  branch_id TEXT NOT NULL,
  entity_type TEXT NOT NULL,
  entity_local_uuid TEXT NOT NULL,
  entity_cloud_id TEXT,
  action TEXT NOT NULL,
  payload TEXT NOT NULL,
  sync_status TEXT DEFAULT 'pending',
  attempts INTEGER DEFAULT 0,
  max_attempts INTEGER DEFAULT 5,
  last_error TEXT,
  next_retry_at TEXT,
  created_at TEXT DEFAULT CURRENT_TIMESTAMP,
  updated_at TEXT DEFAULT CURRENT_TIMESTAMP
);

-- Estado global de sincronización
CREATE TABLE IF NOT EXISTS sync_state (
  key TEXT PRIMARY KEY,
  value TEXT,
  updated_at TEXT DEFAULT CURRENT_TIMESTAMP
);

-- Índices para queries frecuentes
CREATE INDEX IF NOT EXISTS idx_local_orders_sync_status ON local_orders(sync_status);
CREATE INDEX IF NOT EXISTS idx_local_orders_status ON local_orders(status);
CREATE INDEX IF NOT EXISTS idx_local_order_items_order ON local_order_items(order_local_uuid);
CREATE INDEX IF NOT EXISTS idx_local_payments_sync_status ON local_payments(sync_status);
CREATE INDEX IF NOT EXISTS idx_sync_queue_status ON sync_queue(sync_status);
CREATE INDEX IF NOT EXISTS idx_sync_queue_created ON sync_queue(created_at);

-- Insertar versión de migración
INSERT OR REPLACE INTO migrations (version, checksum) VALUES ('001', 'initial-schema');

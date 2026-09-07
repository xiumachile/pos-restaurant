CREATE TABLE IF NOT EXISTS local_categories_new (
  uuid TEXT PRIMARY KEY,
  backend_id INTEGER,
  name_translations TEXT,
  sort_order INTEGER DEFAULT 0,
  is_active INTEGER DEFAULT 1,
  last_updated TEXT DEFAULT CURRENT_TIMESTAMP
);

INSERT OR IGNORE INTO local_categories_new (uuid, name_translations, sort_order, is_active, last_updated)
  SELECT uuid, name_translations, sort_order, is_active, last_updated FROM local_categories;

DROP TABLE IF EXISTS local_categories;

ALTER TABLE local_categories_new RENAME TO local_categories;

CREATE INDEX IF NOT EXISTS idx_local_categories_backend_id ON local_categories(backend_id);

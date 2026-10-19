-- Migration: Add floor plan coordinates and zones
-- Adds support for visual table positioning in the restaurant floor plan editor.

-- 1. Add coordinate columns to local_tables
ALTER TABLE local_tables ADD COLUMN position_x INTEGER DEFAULT 0;
ALTER TABLE local_tables ADD COLUMN position_y INTEGER DEFAULT 0;
ALTER TABLE local_tables ADD COLUMN zone_uuid TEXT;

-- 2. Create dining_zones table if it doesn't exist
CREATE TABLE IF NOT EXISTS dining_zones (
    uuid TEXT PRIMARY KEY,
    name TEXT NOT NULL,
    description TEXT,
    color_hex TEXT DEFAULT '#3b82f6',
    company_id TEXT NOT NULL,
    branch_id TEXT NOT NULL,
    is_active INTEGER DEFAULT 1,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT DEFAULT CURRENT_TIMESTAMP
);

-- 3. Add foreign key index for performance
CREATE INDEX IF NOT EXISTS idx_local_tables_zone_uuid ON local_tables(zone_uuid);

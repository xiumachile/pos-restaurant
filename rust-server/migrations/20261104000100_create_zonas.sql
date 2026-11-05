CREATE TABLE zonas (
    id UUID PRIMARY KEY,

    establecimiento_id UUID NOT NULL
        REFERENCES establecimientos(id)
        ON DELETE RESTRICT,

    codigo VARCHAR(50) NOT NULL,
    nombre_traducciones JSONB NOT NULL,
    color VARCHAR(10) NOT NULL DEFAULT '#64748b',

    nivel_piso INTEGER NOT NULL DEFAULT 0,
    orden INTEGER NOT NULL DEFAULT 0,

    activo BOOLEAN NOT NULL DEFAULT TRUE,

    version INTEGER NOT NULL DEFAULT 1
        CHECK (version >= 1),

    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    deleted_at TIMESTAMPTZ,

    CONSTRAINT zonas_codigo_por_establecimiento
        UNIQUE (establecimiento_id, codigo),

    CONSTRAINT zonas_identidad_compuesta
        UNIQUE (establecimiento_id, id),

    CONSTRAINT zonas_nombre_traducciones_objeto
        CHECK (jsonb_typeof(nombre_traducciones) = 'object')
);

CREATE INDEX idx_zonas_establecimiento_activo
    ON zonas (establecimiento_id, activo)
    WHERE deleted_at IS NULL;

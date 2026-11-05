CREATE TABLE mesas (
    id UUID PRIMARY KEY,

    establecimiento_id UUID NOT NULL
        REFERENCES establecimientos(id) ON DELETE RESTRICT,

    zona_id UUID NULL,

    numero VARCHAR(20) NOT NULL,

    capacidad INTEGER NOT NULL DEFAULT 4
        CHECK (capacidad > 0),

    activo BOOLEAN NOT NULL DEFAULT TRUE,

    version_configuracion INTEGER NOT NULL DEFAULT 1
        CHECK (version_configuracion >= 1),

    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    deleted_at TIMESTAMPTZ,

    CONSTRAINT mesas_numero_por_establecimiento
        UNIQUE (establecimiento_id, numero),

    CONSTRAINT mesas_identidad_compuesta
        UNIQUE (establecimiento_id, id),

    CONSTRAINT mesas_zona_mismo_establecimiento
        FOREIGN KEY (establecimiento_id, zona_id)
        REFERENCES zonas (establecimiento_id, id)
        ON DELETE RESTRICT
);

CREATE INDEX idx_mesas_establecimiento_activas
    ON mesas (establecimiento_id, activo)
    WHERE deleted_at IS NULL;

CREATE INDEX idx_mesas_establecimiento_zona
    ON mesas (establecimiento_id, zona_id)
    WHERE deleted_at IS NULL;

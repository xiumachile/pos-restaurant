CREATE TABLE establecimientos (
    id UUID PRIMARY KEY,
    nombre VARCHAR(200) NOT NULL,
    activo BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE TABLE terminales (
    id UUID PRIMARY KEY,
    establecimiento_id UUID NOT NULL
        REFERENCES establecimientos(id) ON DELETE RESTRICT,
    nombre VARCHAR(100) NOT NULL,
    activo BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),

    CONSTRAINT terminales_nombre_por_establecimiento
        UNIQUE (establecimiento_id, nombre),

    CONSTRAINT terminales_identidad_compuesta
        UNIQUE (establecimiento_id, id)
);

CREATE INDEX idx_terminales_establecimiento
    ON terminales (establecimiento_id);

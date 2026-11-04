CREATE TABLE roles (
    id UUID PRIMARY KEY,
    establecimiento_id UUID NOT NULL
        REFERENCES establecimientos(id) ON DELETE RESTRICT,
    nombre VARCHAR(100) NOT NULL,
    descripcion TEXT,
    activo BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),

    CONSTRAINT roles_nombre_por_establecimiento
        UNIQUE (establecimiento_id, nombre),

    CONSTRAINT roles_identidad_compuesta
        UNIQUE (establecimiento_id, id)
);

CREATE TABLE permisos (
    id UUID PRIMARY KEY,
    codigo VARCHAR(150) NOT NULL UNIQUE,
    descripcion TEXT,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE TABLE rol_permisos (
    establecimiento_id UUID NOT NULL,
    rol_id UUID NOT NULL,
    permiso_id UUID NOT NULL REFERENCES permisos(id) ON DELETE RESTRICT,

    PRIMARY KEY (rol_id, permiso_id),

    FOREIGN KEY (establecimiento_id, rol_id)
        REFERENCES roles(establecimiento_id, id)
        ON DELETE CASCADE
);

CREATE TABLE usuarios (
    id UUID PRIMARY KEY,
    establecimiento_id UUID NOT NULL
        REFERENCES establecimientos(id) ON DELETE RESTRICT,
    rol_id UUID NOT NULL,
    nombre VARCHAR(200) NOT NULL,
    pin_hash TEXT NOT NULL,
    activo BOOLEAN NOT NULL DEFAULT TRUE,
    intentos_fallidos INTEGER NOT NULL DEFAULT 0,
    bloqueado_hasta TIMESTAMPTZ,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),

    CONSTRAINT usuarios_intentos_validos
        CHECK (intentos_fallidos >= 0),

    CONSTRAINT usuarios_rol_mismo_establecimiento
        FOREIGN KEY (establecimiento_id, rol_id)
        REFERENCES roles(establecimiento_id, id)
        ON DELETE RESTRICT,

    CONSTRAINT usuarios_identidad_compuesta
        UNIQUE (establecimiento_id, id)
);

CREATE INDEX idx_roles_establecimiento
    ON roles (establecimiento_id);

CREATE INDEX idx_usuarios_establecimiento
    ON usuarios (establecimiento_id);

CREATE INDEX idx_usuarios_rol
    ON usuarios (rol_id);

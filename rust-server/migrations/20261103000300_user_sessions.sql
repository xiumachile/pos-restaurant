CREATE TABLE sesiones_usuario (
    id UUID PRIMARY KEY,

    establecimiento_id UUID NOT NULL
        REFERENCES establecimientos(id) ON DELETE RESTRICT,

    usuario_id UUID NOT NULL,
    terminal_id UUID NOT NULL,

    token_hash TEXT NOT NULL UNIQUE,

    iniciada_en TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    ultima_actividad_en TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    expira_en TIMESTAMPTZ NOT NULL,

    cerrada_en TIMESTAMPTZ,
    motivo_cierre VARCHAR(100),

    CONSTRAINT sesiones_usuario_usuario_establecimiento
        FOREIGN KEY (establecimiento_id, usuario_id)
        REFERENCES usuarios(establecimiento_id, id)
        ON DELETE RESTRICT,

    CONSTRAINT sesiones_usuario_terminal_establecimiento
        FOREIGN KEY (establecimiento_id, terminal_id)
        REFERENCES terminales(establecimiento_id, id)
        ON DELETE RESTRICT,

    CONSTRAINT sesiones_usuario_expiracion_valida
        CHECK (expira_en > iniciada_en),

    CONSTRAINT sesiones_usuario_cierre_valido
        CHECK (cerrada_en IS NULL OR cerrada_en >= iniciada_en)
);

CREATE UNIQUE INDEX idx_sesion_activa_por_usuario
    ON sesiones_usuario (usuario_id)
    WHERE cerrada_en IS NULL;

CREATE INDEX idx_sesiones_usuario_terminal
    ON sesiones_usuario (terminal_id);

CREATE INDEX idx_sesiones_usuario_expiracion
    ON sesiones_usuario (expira_en);

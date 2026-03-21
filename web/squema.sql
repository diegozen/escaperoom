-- ============================================================
-- Escape Room Tecnológico — esquema MySQL
-- ============================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ── Tabla: usuarios ─────────────────────────────────────────
CREATE TABLE IF NOT EXISTS usuarios (
    id_usuario     INT           NOT NULL AUTO_INCREMENT,
    nombre         VARCHAR(100)  NOT NULL,
    apellidos      VARCHAR(150)  NOT NULL,
    email          VARCHAR(255)  NOT NULL,
    rol            VARCHAR(50)   NOT NULL DEFAULT 'usuario',
    contrasena     VARCHAR(255)  NOT NULL,
    fecha_registro DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id_usuario),
    UNIQUE KEY uq_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Tabla: salas ─────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS salas (
    id_sala     INT          NOT NULL AUTO_INCREMENT,
    nombre_sala VARCHAR(100) NOT NULL,
    PRIMARY KEY (id_sala)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Tabla: partidas ──────────────────────────────────────────
CREATE TABLE IF NOT EXISTS partidas (
    id_partida   INT          NOT NULL AUTO_INCREMENT,
    id_usuario   INT          NOT NULL,
    id_sala      INT                   DEFAULT 1,
    estado       VARCHAR(50)  NOT NULL,
    fecha        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    tiempo_total INT                   DEFAULT NULL,
    PRIMARY KEY (id_partida),
    CONSTRAINT fk_partidas_usuario FOREIGN KEY (id_usuario)
        REFERENCES usuarios (id_usuario) ON DELETE CASCADE,
    CONSTRAINT fk_partidas_sala FOREIGN KEY (id_sala)
        REFERENCES salas (id_sala) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Tabla: ranking ───────────────────────────────────────────
CREATE TABLE IF NOT EXISTS ranking (
    id_ranking      INT         NOT NULL AUTO_INCREMENT,
    id_usuario      INT         NOT NULL,
    id_partida      INT         NOT NULL,
    tiempo_total    INT         NOT NULL,
    estado          VARCHAR(50)          DEFAULT NULL,
    fecha_registro  DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id_ranking),
    CONSTRAINT fk_ranking_usuario FOREIGN KEY (id_usuario)
        REFERENCES usuarios (id_usuario) ON DELETE CASCADE,
    CONSTRAINT fk_ranking_partida FOREIGN KEY (id_partida)
        REFERENCES partidas (id_partida) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Tabla: sesiones_reto ─────────────────────────────────────
-- Conecta la web con el orquestador FastAPI
CREATE TABLE IF NOT EXISTS sesiones_reto (
    id_sesion    INT          NOT NULL AUTO_INCREMENT,
    id_usuario   INT          NOT NULL,
    id_partida   INT                   DEFAULT NULL,
    challenge_id VARCHAR(50)  NOT NULL,
    container_id VARCHAR(100)          DEFAULT NULL,
    ssh_host     VARCHAR(100)          DEFAULT 'localhost',
    ssh_port     INT                   DEFAULT NULL,
    ssh_user     VARCHAR(100)          DEFAULT NULL,
    ssh_pass     VARCHAR(100)          DEFAULT NULL,
    status       VARCHAR(20)  NOT NULL DEFAULT 'running',
    started_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    finished_at  DATETIME              DEFAULT NULL,
    elapsed_secs INT                   DEFAULT NULL,
    flag_used    VARCHAR(255)          DEFAULT NULL,
    PRIMARY KEY (id_sesion),
    CONSTRAINT fk_sesiones_usuario FOREIGN KEY (id_usuario)
        REFERENCES usuarios (id_usuario) ON DELETE CASCADE,
    CONSTRAINT fk_sesiones_partida FOREIGN KEY (id_partida)
        REFERENCES partidas (id_partida) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- ── Datos iniciales ──────────────────────────────────────────
INSERT INTO salas (nombre_sala) VALUES ('Laboratorio Principal');
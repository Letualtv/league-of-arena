CREATE DATABASE IF NOT EXISTS league_arena CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE league_arena;

CREATE TABLE IF NOT EXISTS invocadores (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    puuid           VARCHAR(100) UNIQUE NOT NULL,
    game_name       VARCHAR(50)  NOT NULL,
    tag_line        VARCHAR(10)  NOT NULL,
    apodo           VARCHAR(50)  NULL DEFAULT NULL,
    summoner_id     VARCHAR(100),
    region          VARCHAR(10)  NOT NULL,
    icono_id        INT          DEFAULT 1,
    nivel           INT          DEFAULT 1,
    pin_hash        VARCHAR(255) NULL DEFAULT NULL,
    ranked_solo     VARCHAR(50)  NULL DEFAULT NULL,
    top_campeon     VARCHAR(50)  NULL DEFAULT NULL,
    titulo_activo   VARCHAR(100) NULL DEFAULT NULL,
    actualizado_en  TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    creado_en       TIMESTAMP    DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS partidas_arena (
    id                INT AUTO_INCREMENT PRIMARY KEY,
    match_id          VARCHAR(50) UNIQUE NOT NULL,
    puuid             VARCHAR(100) NOT NULL,
    campeon_id        INT  NOT NULL,
    campeon_nombre    VARCHAR(50) NOT NULL,
    posicion          TINYINT NOT NULL COMMENT '1=primero, 8=ultimo',
    kills             INT DEFAULT 0,
    muertes           INT DEFAULT 0,
    asistencias       INT DEFAULT 0,
    dano_total        INT DEFAULT 0,
    duracion_segundos INT DEFAULT 0,
    jugado_en         TIMESTAMP NOT NULL,
    INDEX idx_puuid         (puuid),
    INDEX idx_puuid_campeon (puuid, campeon_id)
);

CREATE TABLE IF NOT EXISTS campeones_ganados (
    id               INT AUTO_INCREMENT PRIMARY KEY,
    puuid            VARCHAR(100) NOT NULL,
    campeon_id       INT NOT NULL,
    campeon_nombre   VARCHAR(50) NOT NULL,
    campeon_clase    VARCHAR(20) NULL DEFAULT NULL,
    veces_ganado     INT DEFAULT 1,
    marcado_manual   TINYINT(1) DEFAULT 0,
    primera_victoria TIMESTAMP NULL,
    ultima_victoria  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_puuid_campeon (puuid, campeon_id)
);

CREATE TABLE IF NOT EXISTS logros (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    clave          VARCHAR(50)  UNIQUE NOT NULL,
    nombre         VARCHAR(100) NOT NULL,
    descripcion    TEXT NOT NULL,
    titulo         VARCHAR(100) NULL DEFAULT NULL,
    icono          VARCHAR(100) NOT NULL DEFAULT 'fa-solid fa-trophy',
    tipo           VARCHAR(30)  NOT NULL,
    valor_objetivo INT NOT NULL,
    oculto         TINYINT(1) DEFAULT 0,
    creado_en      DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS logros_desbloqueados (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    puuid           VARCHAR(100) NOT NULL,
    logro_id        INT NOT NULL,
    desbloqueado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_puuid_logro (puuid, logro_id),
    FOREIGN KEY (logro_id) REFERENCES logros(id)
);

CREATE TABLE IF NOT EXISTS configuracion (
    clave VARCHAR(50) PRIMARY KEY,
    valor TEXT NOT NULL
);

CREATE DATABASE IF NOT EXISTS league_arena CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE league_arena;

CREATE TABLE IF NOT EXISTS invocadores (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    puuid         VARCHAR(100) UNIQUE NOT NULL,
    game_name     VARCHAR(50)  NOT NULL,
    tag_line      VARCHAR(10)  NOT NULL,
    summoner_id   VARCHAR(100),
    region        VARCHAR(10)  NOT NULL,
    icono_id      INT          DEFAULT 1,
    nivel         INT          DEFAULT 1,
    actualizado_en TIMESTAMP   DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    creado_en     TIMESTAMP    DEFAULT CURRENT_TIMESTAMP
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
    INDEX idx_puuid   (puuid),
    INDEX idx_puuid_campeon (puuid, campeon_id)
);

CREATE TABLE IF NOT EXISTS campeones_ganados (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    puuid           VARCHAR(100) NOT NULL,
    campeon_id      INT NOT NULL,
    campeon_nombre  VARCHAR(50) NOT NULL,
    veces_ganado    INT DEFAULT 1,
    marcado_manual  TINYINT(1) DEFAULT 0,
    primera_victoria TIMESTAMP NULL,
    ultima_victoria  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_puuid_campeon (puuid, campeon_id)
);

CREATE TABLE IF NOT EXISTS logros (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    clave          VARCHAR(50) UNIQUE NOT NULL,
    nombre         VARCHAR(100) NOT NULL,
    descripcion    TEXT NOT NULL,
    icono          VARCHAR(10) NOT NULL DEFAULT '🏆',
    tipo           ENUM('partidas','victorias','campeones','top2','top4') NOT NULL,
    valor_objetivo INT NOT NULL,
    oculto         TINYINT(1) DEFAULT 0
);

CREATE TABLE IF NOT EXISTS logros_desbloqueados (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    puuid           VARCHAR(100) NOT NULL,
    logro_id        INT NOT NULL,
    desbloqueado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_puuid_logro (puuid, logro_id),
    FOREIGN KEY (logro_id) REFERENCES logros(id)
);

INSERT IGNORE INTO logros (clave, nombre, descripcion, icono, tipo, valor_objetivo) VALUES
-- Victorias
('victoria_1',   'Primera Sangre',         'Gana tu primera partida de Arena',          '⚔️',  'victorias', 1),
('victoria_5',   'Guerrero de la Arena',   'Gana 5 partidas de Arena',                  '🗡️',  'victorias', 5),
('victoria_25',  'Veterano',               'Gana 25 partidas de Arena',                 '🛡️',  'victorias', 25),
('victoria_50',  'Campeón de la Arena',    'Gana 50 partidas de Arena',                 '🏆',  'victorias', 50),
('victoria_100', 'Leyenda de la Arena',    'Gana 100 partidas de Arena',                '👑',  'victorias', 100),
-- Partidas jugadas
('partidas_10',  'Iniciado',               'Juega 10 partidas de Arena',                '🎮',  'partidas', 10),
('partidas_50',  'Aficionado',             'Juega 50 partidas de Arena',                '⚡',  'partidas', 50),
('partidas_100', 'Comprometido',           'Juega 100 partidas de Arena',               '🔥',  'partidas', 100),
('partidas_500', 'Maratonista',            'Juega 500 partidas de Arena',               '🌋',  'partidas', 500),
-- Campeones ganados
('camp_5',       'Explorador',             'Gana con 5 campeones distintos',            '🗺️',  'campeones', 5),
('camp_20',      'Coleccionista',          'Gana con 20 campeones distintos',           '💎',  'campeones', 20),
('camp_50',      'Gran Coleccionista',     'Gana con 50 campeones distintos',           '🌟',  'campeones', 50),
('camp_100',     'Maestro del Roster',     'Gana con 100 campeones distintos',          '🎖️',  'campeones', 100),
-- Top 2 (podio)
('top2_5',       'Finalista',              'Termina en top 2 en 5 partidas',            '🥈',  'top2', 5),
('top2_25',      'Contendiente',           'Termina en top 2 en 25 partidas',           '🏅',  'top2', 25),
('top2_100',     'Élite',                  'Termina en top 2 en 100 partidas',          '💫',  'top2', 100),
-- Top 4
('top4_10',      'Superviviente',          'Termina en top 4 en 10 partidas',           '🎯',  'top4', 10),
('top4_50',      'Constante',              'Termina en top 4 en 50 partidas',           '📈',  'top4', 50);

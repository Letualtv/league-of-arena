<?php
date_default_timezone_set('Europe/Madrid');

$isProduction = ($_SERVER['HTTP_HOST'] ?? '') !== 'localhost';

if ($isProduction) {
    define('BASE_URL', '/');
    define('DB_HOST', 'tu-host-mysql');
    define('DB_NAME', 'nombre_base_de_datos');
    define('DB_USER', 'usuario_bd');
    define('DB_PASS', 'contraseña_bd');
} else {
    define('BASE_URL', '/Leagueofarena/');
    define('DB_HOST', 'localhost:3306');
    define('DB_NAME', 'league_arena');
    define('DB_USER', 'root');
    define('DB_PASS', '');
}

// Obtén tu API Key en https://developer.riotgames.com (caduca cada 24h con Development Key)
define('RIOT_API_KEY', 'RGAPI-XXXXXXXX-XXXX-XXXX-XXXX-XXXXXXXXXXXX');

define('REGIONS', [
    'EUW'  => ['platform' => 'euw1', 'regional' => 'europe'],
    'EUNE' => ['platform' => 'eun1', 'regional' => 'europe'],
    'TR'   => ['platform' => 'tr1',  'regional' => 'europe'],
    'RU'   => ['platform' => 'ru',   'regional' => 'europe'],
    'NA'   => ['platform' => 'na1',  'regional' => 'americas'],
    'BR'   => ['platform' => 'br1',  'regional' => 'americas'],
    'LAN'  => ['platform' => 'la1',  'regional' => 'americas'],
    'LAS'  => ['platform' => 'la2',  'regional' => 'americas'],
    'KR'   => ['platform' => 'kr',   'regional' => 'asia'],
    'JP'   => ['platform' => 'jp1',  'regional' => 'asia'],
    'OCE'  => ['platform' => 'oc1',  'regional' => 'sea'],
]);

define('QUEUE_ARENAS', [1750]); // Arena temporada actual (2026)
define('DDRAGON_BASE', 'https://ddragon.leagueoflegends.com');
define('MATCHES_PER_SYNC', 30); // máximo de partidas que se procesan por click en "Actualizar"

// Administrador del sitio — pon aquí tu propio Riot ID
define('ADMIN_GAME_NAME', 'TuNombreAqui');
define('ADMIN_TAG_LINE',  'EUW');

define('FEED_MAX_DEFAULT', 10);

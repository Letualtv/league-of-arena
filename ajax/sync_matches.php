<?php
ini_set('display_errors', 0);
header('Content-Type: application/json');
session_start();

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/RiotAPI.php';
require_once __DIR__ . '/../includes/LogrosManager.php';

try {
    $puuid  = $_POST['puuid']  ?? null;
    $region = strtoupper($_POST['region'] ?? 'EUW');

    if (!$puuid || !isset(REGIONS[$region])) {
        echo json_encode(['ok' => false, 'error' => 'Parametros invalidos']);
        exit;
    }

    if (RIOT_API_KEY === 'RGAPI-XXXXXXXX-XXXX-XXXX-XXXX-XXXXXXXXXXXX') {
        echo json_encode(['ok' => false, 'error' => 'La API Key de Riot no está configurada. Contacta con el administrador del sitio.']);
        exit;
    }

    $api = new RiotAPI($region);
    $db  = getDB();

    // 1. Datos básicos del invocador
    $summoner = $api->getSummonerByPuuid($puuid);
    if (!$summoner) {
        $code  = $api->lastHttpCode;
        $error = ($code === 403 || $code === 401)
            ? 'La API Key de Riot ha caducado. Contacta con el administrador del sitio para renovarla.'
            : 'No se pudo conectar con la API de Riot. Inténtalo de nuevo en unos minutos.';
        echo json_encode(['ok' => false, 'error' => $error]);
        exit;
    }

    // 2. Campeón con más maestría
    $mastery    = $api->getTopMastery($puuid, $region);
    $topChampId = $mastery[0]['championId'] ?? null;
    $topCampeon = null;

    if ($topChampId) {
        $todosChamps = RiotAPI::getChampions();
        foreach ($todosChamps as $champ) {
            if ((int)$champ['key'] === (int)$topChampId) {
                $topCampeon = $champ['id'];
                break;
            }
        }
    }

    // 3. Ranked (intenta por PUUID primero, si falla por summoner ID)
    $rankedSolo = null;
    $rankedFlex = null;
    $ranked = $api->getRankedByPuuid($puuid);
    if (!is_array($ranked) || empty($ranked)) {
        $summonerId = $summoner['id'] ?? null;
        if ($summonerId) {
            $ranked = $api->getRankedBySummonerId($summonerId, $region);
        }
    }
    if (is_array($ranked)) {
        foreach ($ranked as $entry) {
            $str = $entry['tier'] . ' ' . $entry['rank'];
            if ($entry['queueType'] === 'RANKED_SOLO_5x5') $rankedSolo = $str;
            if ($entry['queueType'] === 'RANKED_FLEX_SR')  $rankedFlex = $str;
        }
    }

    // 4. Actualizar perfil del invocador
    $db->prepare('UPDATE invocadores SET icono_id = ?, nivel = ?, top_campeon = ?, ranked_solo = ?, ranked_flex = ? WHERE puuid = ?')
       ->execute([$summoner['profileIconId'], $summoner['summonerLevel'], $topCampeon, $rankedSolo, $rankedFlex, $puuid]);

    // 5. Sincronizar partidas de Arena (todas las del queue actual, paginando)
    $matchIds        = $api->getAllArenaMatchIds($puuid);
    $partidasNuevas  = 0;
    $campeonesNuevos = 0;

    // Debug: vuelca info en cache/debug_sync.json
    $debug = [
        'fecha'        => date('c'),
        'puuid'        => $puuid,
        'queues'       => QUEUE_ARENAS,
        'total_ids'    => count($matchIds),
        'http_code'    => $api->lastHttpCode,
    ];

    if (!empty($matchIds)) {
        // Filtrar match IDs ya guardados
        $placeholders = implode(',', array_fill(0, count($matchIds), '?'));
        $stmt = $db->prepare("SELECT match_id FROM partidas_arena WHERE match_id IN ($placeholders)");
        $stmt->execute($matchIds);
        $existentes = array_column($stmt->fetchAll(), 'match_id');
        $pendientes = array_values(array_diff($matchIds, $existentes));

        // Procesar máximo MATCHES_PER_SYNC por click para que el navegador no se cuelgue.
        // Si quedan más, el usuario las trae con clicks sucesivos.
        $pendientesTotales = count($pendientes);
        $pendientes        = array_slice($pendientes, 0, MATCHES_PER_SYNC);

        $debug['existentes']        = array_values($existentes);
        $debug['pendientes_total']  = $pendientesTotales;
        $debug['pendientes_lote']   = count($pendientes);
        $debug['primera_partida']   = null;

        $insertPartida = $db->prepare('
            INSERT INTO partidas_arena
                (match_id, puuid, campeon_id, campeon_nombre, posicion, kills, muertes, asistencias, dano_total, duracion_segundos, jugado_en)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ');

        $upsertGanado = $db->prepare('
            INSERT INTO campeones_ganados (puuid, campeon_id, campeon_nombre, primera_victoria, ultima_victoria, marcado_manual)
            VALUES (?, ?, ?, ?, ?, 0)
            ON DUPLICATE KEY UPDATE
                veces_ganado    = veces_ganado + 1,
                ultima_victoria = VALUES(ultima_victoria)
        ');

        foreach ($pendientes as $matchId) {
            $match = $api->getMatch($matchId);
            usleep(60000);
            if (!$match) continue;

            // Encontrar al jugador en la partida
            $participantes = $match['info']['participants'] ?? [];
            $yo = null;
            foreach ($participantes as $p) {
                if (($p['puuid'] ?? '') === $puuid) { $yo = $p; break; }
            }
            if (!$yo) continue;

            // Guarda la primera partida cruda para diagnóstico
            if ($debug['primera_partida'] === null) {
                $debug['primera_partida'] = [
                    'match_id'         => $matchId,
                    'queueId'          => $match['info']['queueId'] ?? null,
                    'gameMode'         => $match['info']['gameMode'] ?? null,
                    'placement'        => $yo['placement'] ?? null,
                    'subteamPlacement' => $yo['subteamPlacement'] ?? null,
                    'championName'     => $yo['championName'] ?? null,
                    'campos_yo'        => array_keys($yo),
                ];
            }

            $posicion = (int)($yo['placement'] ?? $yo['subteamPlacement'] ?? 0);
            if ($posicion < 1 || $posicion > 8) continue;

            // Guardamos en UTC para mantener coherencia con el dump original;
            // el display convierte a Madrid vía tiempoRelativo().
            $jugadoEn = gmdate('Y-m-d H:i:s', (int)(($match['info']['gameStartTimestamp'] ?? $match['info']['gameCreation'] ?? 0) / 1000));

            $insertPartida->execute([
                $matchId,
                $puuid,
                (int)($yo['championId'] ?? 0),
                (string)($yo['championName'] ?? ''),
                $posicion,
                (int)($yo['kills'] ?? 0),
                (int)($yo['deaths'] ?? 0),
                (int)($yo['assists'] ?? 0),
                (int)($yo['totalDamageDealtToChampions'] ?? 0),
                (int)($match['info']['gameDuration'] ?? 0),
                $jugadoEn,
            ]);
            $partidasNuevas++;

            // Si quedó primero, se cuenta como campeón ganado
            if ($posicion === 1) {
                $upsertGanado->execute([
                    $puuid,
                    (int)($yo['championId'] ?? 0),
                    (string)($yo['championName'] ?? ''),
                    $jugadoEn,
                    $jugadoEn,
                ]);
                $campeonesNuevos++;
            }
        }
    }

    // 6. Verificar logros tras la sync
    $lm = new LogrosManager($db);
    $logrosDesbloqueados = $lm->verificarYDesbloquear($puuid);

    // Volcar debug
    $debug['partidas_nuevas']  = $partidasNuevas;
    $debug['campeones_nuevos'] = $campeonesNuevos;
    @file_put_contents(__DIR__ . '/../cache/debug_sync.json', json_encode($debug, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

    $restantes = max(0, ($debug['pendientes_total'] ?? 0) - $partidasNuevas);
    $msg = 'Perfil actualizado';
    if ($restantes > 0) {
        $msg .= " — quedan $restantes partidas por sincronizar, pulsa Actualizar otra vez.";
    }

    echo json_encode([
        'ok' => true,
        'mensaje' => $msg,
        'main' => $topCampeon,
        'partidas_nuevas' => $partidasNuevas,
        'campeones_nuevos' => $campeonesNuevos,
        'logros_nuevos' => count($logrosDesbloqueados),
        'restantes' => $restantes,
    ]);

} catch (Throwable $e) {
    echo json_encode(['ok' => false, 'error' => 'Error: ' . $e->getMessage()]);
}

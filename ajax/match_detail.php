<?php
ini_set('display_errors', 0);
header('Content-Type: application/json; charset=utf-8');

try {
    require_once __DIR__ . '/../config/config.php';
    require_once __DIR__ . '/../includes/RiotAPI.php';

    $matchId = $_GET['match_id'] ?? null;
    $region  = strtoupper($_GET['region'] ?? 'EUW');
    $puuid   = $_GET['puuid']   ?? null;

    if (!$matchId || !isset(REGIONS[$region]) || !$puuid) {
        echo json_encode(['ok' => false, 'error' => 'Parámetros inválidos']);
        exit;
    }

    // Validar formato del match_id (ej. EUW1_1234567890)
    if (!preg_match('/^[A-Z0-9]+_\d+$/', $matchId)) {
        echo json_encode(['ok' => false, 'error' => 'Match ID inválido']);
        exit;
    }

    $cacheDir = __DIR__ . '/../cache/matches';
    if (!is_dir($cacheDir)) @mkdir($cacheDir, 0755, true);
    $cacheFile = "$cacheDir/$matchId.json";

    // Cache permanente: las partidas terminadas nunca cambian
    if (file_exists($cacheFile)) {
        $match = json_decode(file_get_contents($cacheFile), true);
    } else {
        if (RIOT_API_KEY === 'RGAPI-XXXXXXXX-XXXX-XXXX-XXXX-XXXXXXXXXXXX') {
            echo json_encode(['ok' => false, 'error' => 'La API Key de Riot no está configurada.']);
            exit;
        }
        $api   = new RiotAPI($region);
        $match = $api->getMatch($matchId);
        if (!$match) {
            $code = $api->lastHttpCode;
            $err  = ($code === 403 || $code === 401)
                ? 'La API Key de Riot ha caducado.'
                : "No se pudo obtener la partida (HTTP $code).";
            echo json_encode(['ok' => false, 'error' => $err]);
            exit;
        }
        @file_put_contents($cacheFile, json_encode($match));
    }

    // Extraer solo lo que necesitamos para el panel
    $info = $match['info'] ?? [];
    $participantes = $info['participants'] ?? [];

    // Agrupar por subteam (en Arena hay 4 subteams: 1, 2, 3, 4)
    $equipos = [];
    $miSubteamId = null;
    $yo = null;
    foreach ($participantes as $p) {
        $subId = (int)($p['playerSubteamId'] ?? $p['subteamId'] ?? 0);
        $equipos[$subId][] = [
            'campeon'      => $p['championName']     ?? '',
            'campeon_id'   => (int)($p['championId'] ?? 0),
            'placement'    => (int)($p['placement'] ?? $p['subteamPlacement'] ?? 0),
            'kills'        => (int)($p['kills']      ?? 0),
            'deaths'       => (int)($p['deaths']     ?? 0),
            'assists'      => (int)($p['assists']    ?? 0),
            'es_yo'        => ($p['puuid'] ?? '') === $puuid,
            'riot_id'      => trim(($p['riotIdGameName'] ?? $p['summonerName'] ?? '') . '#' . ($p['riotIdTagline'] ?? '')),
        ];
        if (($p['puuid'] ?? '') === $puuid) {
            $miSubteamId = $subId;
            $yo = $p;
        }
    }

    // Ordenar equipos por placement (el del usuario destacado)
    uasort($equipos, function ($a, $b) {
        return ($a[0]['placement'] ?? 9) <=> ($b[0]['placement'] ?? 9);
    });

    // Items y augments del jugador
    $itemIds = [];
    for ($i = 0; $i <= 6; $i++) {
        $itemId = (int)($yo["item$i"] ?? 0);
        if ($itemId > 0) $itemIds[] = $itemId;
    }
    $itemsMap = RiotAPI::getItems();
    $items = array_map(fn($id) => [
        'id'   => $id,
        'name' => $itemsMap[$id]['name'] ?? "Item $id",
        'desc' => $itemsMap[$id]['desc'] ?? ($itemsMap[$id]['plaintext'] ?? ''),
    ], $itemIds);
    $augmentIds = array_filter([
        (int)($yo['playerAugment1'] ?? 0),
        (int)($yo['playerAugment2'] ?? 0),
        (int)($yo['playerAugment3'] ?? 0),
        (int)($yo['playerAugment4'] ?? 0),
        (int)($yo['playerAugment5'] ?? 0),
        (int)($yo['playerAugment6'] ?? 0),
    ], fn($a) => $a > 0);
    $augmentsMap = RiotAPI::getArenaAugments();
    // Riot match-v5 devuelve IDs con offset por tier:
    //   1xxx  → Gold (base = id - 1000)
    //   2xxx  → Prismatic (base = id - 2000)
    //   < 500 → Silver (base directo)
    // CDragon solo mapea ~227 augments. Si no encontramos ni el ID directo ni la base,
    // al menos mostramos el tier deducido del rango y el ID.
    $augments = [];
    foreach ($augmentIds as $id) {
        // Detectar tier por rango del ID (siempre, incluso si no encontramos data)
        if     ($id >= 2000) $tier = 'prismatic';
        elseif ($id >= 1000) $tier = 'gold';
        else                 $tier = 'silver';

        // Buscar la base que CDragon SÍ tiene mirrorizada
        $baseId = $id;
        if (!isset($augmentsMap[$id])) {
            if ($tier === 'prismatic' && isset($augmentsMap[$id - 2000])) $baseId = $id - 2000;
            elseif ($tier === 'gold'   && isset($augmentsMap[$id - 1000])) $baseId = $id - 1000;
        }

        $tierLabel = ['silver' => 'Plata', 'gold' => 'Oro', 'prismatic' => 'Prismático'][$tier];
        $nameFallback = "Augment $id ($tierLabel) — sin datos en CDragon";

        $augments[] = [
            'id'       => $id,
            'tier'     => $tier,
            'name'     => $augmentsMap[$baseId]['name']     ?? $nameFallback,
            'desc'     => $augmentsMap[$baseId]['desc']     ?? "Este augment ($tierLabel, ID $id) todavía no está documentado en CommunityDragon. Se actualiza con cada parche del juego.",
            'icon'     => $augmentsMap[$baseId]['icon']     ?? '',
            'icon_alt' => $augmentsMap[$baseId]['icon_alt'] ?? '',
        ];
    }

    echo json_encode([
        'ok'              => true,
        'match_id'        => $matchId,
        'duracion'        => (int)($info['gameDuration'] ?? 0),
        'jugado_en'       => (int)(($info['gameStartTimestamp'] ?? 0) / 1000),
        'mi_subteam'      => $miSubteamId,
        'mi_placement'    => (int)($yo['placement'] ?? $yo['subteamPlacement'] ?? 0),
        'mi_dano'         => (int)($yo['totalDamageDealtToChampions'] ?? 0),
        'mi_kills'        => (int)($yo['kills']     ?? 0),
        'mi_deaths'       => (int)($yo['deaths']    ?? 0),
        'mi_assists'      => (int)($yo['assists']   ?? 0),
        'mi_oro'          => (int)($yo['goldEarned'] ?? 0),
        'mi_items'        => $items,
        'mi_augments'     => $augments,
        'equipos'         => $equipos,
        'cached'          => file_exists($cacheFile),
    ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    echo json_encode([
        'ok'    => false,
        'error' => 'Error: ' . $e->getMessage(),
        'file'  => basename($e->getFile()) . ':' . $e->getLine(),
    ]);
}

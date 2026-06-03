<?php
ini_set('display_errors', 0);
header('Content-Type: application/json');
session_start();

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/RiotAPI.php';

try {
    $puuid  = $_POST['puuid']  ?? null;
    $region = strtoupper($_POST['region'] ?? 'EUW');

    if (!$puuid || !isset(REGIONS[$region])) {
        echo json_encode(['ok' => false, 'error' => 'Parametros invalidos']);
        exit;
    }

    if (RIOT_API_KEY === 'RGAPI-XXXXXXXX-XXXX-XXXX-XXXX-XXXXXXXXXXXX') {
        echo json_encode(['ok' => false, 'error' => 'Configura tu API Key en config/config.php']);
        exit;
    }

    $api = new RiotAPI($region);
    $db  = getDB();

    // 1. Datos básicos del invocador
    $summoner = $api->getSummonerByPuuid($puuid);
    if (!$summoner) {
        $code  = $api->lastHttpCode;
        $error = ($code === 403 || $code === 401)
            ? 'La API Key ha caducado. Contacta con Letual para renovarla.'
            : 'No se pudo conectar con la API de Riot. Contacta con Letual si el problema persiste.';
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

    // 4. Actualizar BD
    $db->prepare('UPDATE invocadores SET icono_id = ?, nivel = ?, top_campeon = ?, ranked_solo = ?, ranked_flex = ? WHERE puuid = ?')
       ->execute([$summoner['profileIconId'], $summoner['summonerLevel'], $topCampeon, $rankedSolo, $rankedFlex, $puuid]);

    echo json_encode(['ok' => true, 'mensaje' => 'Perfil actualizado', 'main' => $topCampeon]);

} catch (Throwable $e) {
    echo json_encode(['ok' => false, 'error' => 'Error: ' . $e->getMessage()]);
}

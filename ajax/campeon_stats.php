<?php
ini_set('display_errors', 0);
header('Content-Type: application/json; charset=utf-8');

try {
    require_once __DIR__ . '/../config/config.php';
    require_once __DIR__ . '/../config/db.php';

    $puuid     = $_GET['puuid']     ?? null;
    $campeonId = (int)($_GET['campeon_id'] ?? 0);

    if (!$puuid || !$campeonId) {
        echo json_encode(['ok' => false, 'error' => 'Parámetros inválidos']);
        exit;
    }

    $db = getDB();

// Stats agregadas
$stmt = $db->prepare('
    SELECT
        COUNT(*)                                       AS partidas,
        SUM(CASE WHEN posicion = 1 THEN 1 ELSE 0 END)  AS victorias,
        SUM(CASE WHEN posicion <= 2 THEN 1 ELSE 0 END) AS top2,
        SUM(CASE WHEN posicion <= 4 THEN 1 ELSE 0 END) AS top4,
        MIN(posicion)                                  AS mejor,
        AVG(posicion)                                  AS pos_media,
        SUM(kills)                                     AS kills_tot,
        SUM(muertes)                                   AS muertes_tot,
        SUM(asistencias)                               AS asist_tot,
        AVG(dano_total)                                AS dano_medio,
        AVG(duracion_segundos)                         AS dur_media,
        MAX(jugado_en)                                 AS ultima_partida
    FROM partidas_arena
    WHERE puuid = ? AND campeon_id = ?
');
$stmt->execute([$puuid, $campeonId]);
$stats = $stmt->fetch() ?: [];

// Distribución de placements (cuántas veces 1º, 2º, 3º... 8º)
$stmt = $db->prepare('
    SELECT posicion, COUNT(*) AS n
    FROM partidas_arena
    WHERE puuid = ? AND campeon_id = ?
    GROUP BY posicion
    ORDER BY posicion ASC
');
$stmt->execute([$puuid, $campeonId]);
// dist[0]..dist[7] = veces que quedó 1º..8º (índices 0-based para que json_encode lo trate como array)
$dist = array_fill(0, 8, 0);
foreach ($stmt->fetchAll() as $row) {
    $idx = (int)$row['posicion'] - 1;
    if ($idx >= 0 && $idx < 8) $dist[$idx] = (int)$row['n'];
}

// Datos de la entrada en campeones_ganados (si existe)
$stmt = $db->prepare('SELECT primera_victoria, ultima_victoria, veces_ganado, marcado_manual FROM campeones_ganados WHERE puuid = ? AND campeon_id = ?');
$stmt->execute([$puuid, $campeonId]);
$ganado = $stmt->fetch() ?: null;

    echo json_encode([
        'ok'             => true,
        'partidas'       => (int)($stats['partidas'] ?? 0),
        'victorias'      => (int)($stats['victorias'] ?? 0),
        'top2'           => (int)($stats['top2'] ?? 0),
        'top4'           => (int)($stats['top4'] ?? 0),
        'mejor'          => isset($stats['mejor']) && $stats['mejor'] !== null ? (int)$stats['mejor'] : null,
        'pos_media'      => isset($stats['pos_media']) && $stats['pos_media'] !== null ? (float)$stats['pos_media'] : null,
        'kills_tot'      => (int)($stats['kills_tot'] ?? 0),
        'muertes_tot'    => (int)($stats['muertes_tot'] ?? 0),
        'asist_tot'      => (int)($stats['asist_tot'] ?? 0),
        'dano_medio'     => (float)($stats['dano_medio'] ?? 0),
        'dur_media'      => (float)($stats['dur_media'] ?? 0),
        'ultima_partida' => $stats['ultima_partida'] ?? null,
        'distribucion'   => $dist,
        'ganado_info'    => $ganado,
    ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    echo json_encode([
        'ok'    => false,
        'error' => 'Error servidor: ' . $e->getMessage(),
        'file'  => basename($e->getFile()) . ':' . $e->getLine(),
    ]);
}

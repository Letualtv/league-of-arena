<?php
session_start();
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/RiotAPI.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/LogrosManager.php';

$db = getDB();

// Solo invocadores que han reclamado cuenta
$stmt = $db->query('SELECT * FROM invocadores WHERE pin_hash IS NOT NULL ORDER BY creado_en ASC');
$invocadores = $stmt->fetchAll();

if (empty($invocadores)) {
    $pageTitle = 'Ranking — Leagueofarena';
    $navPuuid  = null;
    $navRegion = null;
    foreach ($_SESSION as $key => $val) {
        if (str_starts_with($key, 'auth_') && $val) {
            $candidato = substr($key, 5);
            $stmtNav = $db->prepare('SELECT puuid, region FROM invocadores WHERE puuid = ?');
            $stmtNav->execute([$candidato]);
            $rowNav = $stmtNav->fetch();
            if ($rowNav) { $navPuuid = $rowNav['puuid']; $navRegion = $rowNav['region']; break; }
        }
    }
    include __DIR__ . '/includes/header.php';
    echo '<div class="container"><div class="card" style="text-align:center;padding:3rem"><h2>Nadie ha reclamado cuenta aun</h2><p class="text-muted">Sé el primero en <a href="' . BASE_URL . '">reclamar tu cuenta</a>.</p></div></div>';
    include __DIR__ . '/includes/footer.php';
    exit;
}

$version     = RiotAPI::getDDragonVersion();
$todosChamps = RiotAPI::getChampions();

$champKeyById   = [];
$champKeyByName = [];
foreach ($todosChamps as $champ) {
    $champKeyById[(int)$champ['key']]           = $champ['id'];
    $champKeyByName[strtolower($champ['name'])] = $champ['id'];
}

function resolverDDKey(array $champKeyById, array $champKeyByName, array $todosChamps, int $id, string $nombre): string {
    if (isset($champKeyById[$id])) return $champKeyById[$id];
    if (isset($champKeyByName[strtolower($nombre)])) return $champKeyByName[strtolower($nombre)];
    $norm = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $nombre));
    foreach ($todosChamps as $ch) {
        if (strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $ch['name'])) === $norm) return $ch['id'];
    }
    return preg_replace('/[^a-zA-Z0-9]/', '', $nombre);
}

// Estadísticas de cada jugador
$jugadores = [];
foreach ($invocadores as $inv) {
    $lm    = new LogrosManager($db);
    $stats = $lm->getEstadisticas($inv['puuid']);

    // Logros desbloqueados
    $stmt2 = $db->prepare('SELECT COUNT(*) FROM logros_desbloqueados WHERE puuid = ?');
    $stmt2->execute([$inv['puuid']]);
    $stats['logros_total'] = (int)$stmt2->fetchColumn();

    // Campeones marcados recientemente
    $stmt2 = $db->prepare('SELECT campeon_nombre FROM campeones_ganados WHERE puuid = ? ORDER BY ultima_victoria DESC LIMIT 3');
    $stmt2->execute([$inv['puuid']]);
    $stats['recientes'] = array_column($stmt2->fetchAll(), 'campeon_nombre');

    // Posición media en Arena (más bajo = mejor)
    $stmt2 = $db->prepare('SELECT AVG(posicion) AS pos_media, COUNT(*) AS partidas FROM partidas_arena WHERE puuid = ?');
    $stmt2->execute([$inv['puuid']]);
    $rowPos = $stmt2->fetch();
    $stats['arena_pos_media'] = $rowPos && $rowPos['partidas'] > 0 ? (float)$rowPos['pos_media'] : null;
    $stats['arena_partidas']  = $rowPos ? (int)$rowPos['partidas'] : 0;

    // Main de Arena: campeón más jugado en partidas_arena
    $stmt2 = $db->prepare('
        SELECT campeon_id, campeon_nombre, COUNT(*) AS veces
        FROM partidas_arena WHERE puuid = ?
        GROUP BY campeon_id, campeon_nombre
        ORDER BY veces DESC LIMIT 1
    ');
    $stmt2->execute([$inv['puuid']]);
    $mainArena = $stmt2->fetch();
    $stats['arena_main_nombre'] = $mainArena['campeon_nombre'] ?? null;
    $stats['arena_main_id']     = $mainArena ? (int)$mainArena['campeon_id'] : null;
    $stats['arena_main_veces']  = $mainArena ? (int)$mainArena['veces'] : 0;

    $jugadores[] = array_merge($inv, $stats);
}

// Ordenar por total de campeones ganados
usort($jugadores, fn($a, $b) => $b['total'] <=> $a['total']);

// ===== Stats curiosas globales =====

// Primero en marcar cada campeón (el más rápido)
$stmt = $db->query('
    SELECT campeon_nombre, MIN(campeon_id) AS campeon_id, puuid, MIN(primera_victoria) as fecha
    FROM campeones_ganados
    WHERE primera_victoria IS NOT NULL
    GROUP BY campeon_nombre
    ORDER BY fecha ASC
    LIMIT 5
');
$primerosEnMarcar = $stmt->fetchAll();

// Campeón más popular (el que más gente ha ganado)
$stmt = $db->query('
    SELECT campeon_nombre, MIN(campeon_id) AS campeon_id, COUNT(DISTINCT puuid) as jugadores
    FROM campeones_ganados
    GROUP BY campeon_nombre
    ORDER BY jugadores DESC
    LIMIT 5
');
$campeonesMasPopulares = $stmt->fetchAll();

// Campeón más exclusivo (solo 1 persona lo tiene)
$stmt = $db->query('
    SELECT campeon_nombre, campeon_id, puuid
    FROM campeones_ganados
    WHERE campeon_id IN (
        SELECT campeon_id FROM campeones_ganados
        GROUP BY campeon_id HAVING COUNT(DISTINCT puuid) = 1
    )
    ORDER BY RAND()
    LIMIT 5
');
$exclusivos = $stmt->fetchAll();

// Mapa puuid → nombre para el ranking
$puuidToName = array_column($jugadores, null, 'puuid');

// Feed de actividad: últimos logros desbloqueados (límite configurable desde admin)
$feedMax = max(1, min(200, (int)getConfig($db, 'feed_max', FEED_MAX_DEFAULT)));
try {
    $feedLogros = $db->query("
        SELECT ld.desbloqueado_en, i.game_name, i.tag_line, i.apodo, i.puuid, i.region,
               i.titulo_activo,
               l.nombre AS logro_nombre, l.icono AS logro_icono, l.titulo AS logro_titulo
        FROM logros_desbloqueados ld
        JOIN invocadores i ON i.puuid = ld.puuid
        JOIN logros l ON l.id = ld.logro_id
        WHERE i.pin_hash IS NOT NULL
        ORDER BY ld.desbloqueado_en DESC
        LIMIT {$feedMax}
    ")->fetchAll();
} catch (PDOException $e) {
    $feedLogros = [];
}

// Función para generar título gracioso según stats
function tituloGracioso(array $j): string {
    if ($j['total'] === 0)          return '👻 El Fantasma';
    if ($j['total'] >= 100)         return '👑 El Dios del Roster';
    if ($j['total'] >= 50)          return '🏆 El Campeón';
    if ($j['clase_Support'] >= 10)  return '💚 La Mama del Equipo';
    if ($j['clase_Assassin'] >= 10) return '🗡️ El Rata';
    if ($j['clase_Tank'] >= 10)     return '🛡️ El Carne de Cañón';
    if ($j['clase_Mage'] >= 10)     return '🔮 El Nerd';
    if ($j['clase_Marksman'] >= 10) return '🎯 El Sniper';
    if ($j['clase_Fighter'] >= 10)  return '💪 El Musculitos';
    if ($j['total'] <= 2)           return '😴 El Vago';
    return '⚔️ Aspirante';
}

function rankTier(string $ranked): int {
    $r = strtolower($ranked);
    if (str_contains($r, 'challenger'))  return 9;
    if (str_contains($r, 'grandmaster')) return 8;
    if (str_contains($r, 'master'))      return 7;
    if (str_contains($r, 'diamond'))     return 6;
    if (str_contains($r, 'emerald'))     return 5;
    if (str_contains($r, 'platinum'))    return 4;
    if (str_contains($r, 'gold'))        return 3;
    if (str_contains($r, 'silver'))      return 2;
    if (str_contains($r, 'bronze'))      return 1;
    if (str_contains($r, 'iron'))        return 0;
    return -1;
}

function bestRank(?string $solo, ?string $flex): string {
    $solo = $solo ?? '';
    $flex = $flex ?? '';
    if (!$solo && !$flex) return '';
    if (!$solo) return $flex;
    if (!$flex) return $solo;
    return rankTier($solo) >= rankTier($flex) ? $solo : $flex;
}

function rankBadge(string $ranked): string {
    $ranked = strtolower($ranked);
    if (str_contains($ranked, 'challenger'))  return '<span class="rank-badge rank-challenger">Challenger</span>';
    if (str_contains($ranked, 'grandmaster')) return '<span class="rank-badge rank-master">GM</span>';
    if (str_contains($ranked, 'master'))      return '<span class="rank-badge rank-master">Master</span>';
    if (str_contains($ranked, 'diamond'))     return '<span class="rank-badge rank-diamond">Diamond</span>';
    if (str_contains($ranked, 'emerald'))     return '<span class="rank-badge rank-emerald">Emerald</span>';
    if (str_contains($ranked, 'platinum'))    return '<span class="rank-badge rank-platinum">Platinum</span>';
    if (str_contains($ranked, 'gold'))        return '<span class="rank-badge rank-gold">Gold</span>';
    if (str_contains($ranked, 'silver'))      return '<span class="rank-badge rank-silver">Silver</span>';
    if (str_contains($ranked, 'bronze'))      return '<span class="rank-badge rank-bronze">Bronze</span>';
    if (str_contains($ranked, 'iron'))        return '<span class="rank-badge rank-iron">Iron</span>';
    return '<span class="rank-badge rank-none">Sin rango</span>';
}

$pageTitle = 'Ranking — Leagueofarena';

// Detectar si hay un usuario autenticado en sesión para mostrar su nav
$navPuuid  = null;
$navRegion = null;
foreach ($_SESSION as $key => $val) {
    if (str_starts_with($key, 'auth_') && $val) {
        $candidato = substr($key, 5);
        $stmtNav = $db->prepare('SELECT puuid, region FROM invocadores WHERE puuid = ?');
        $stmtNav->execute([$candidato]);
        $rowNav = $stmtNav->fetch();
        if ($rowNav) {
            $navPuuid  = $rowNav['puuid'];
            $navRegion = $rowNav['region'];
            break;
        }
    }
}

include __DIR__ . '/includes/header.php';
?>

<div class="container">

    <div class="page-header">
        <div>
            <h1 class="page-title">
                <i class="fa-solid fa-dragon" style="color:var(--gold)"></i>
                Lagartos del Sol
            </h1>
            <p class="page-subtitle"><?= count($jugadores) ?> lagarto<?= count($jugadores) !== 1 ? 's' : '' ?> en la Arena</p>
        </div>
    </div>

    <!-- Tabla de ranking -->
    <div class="card" style="margin-bottom:2rem;padding:0;overflow:hidden">
        <table class="ranking-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Invocador</th>
                    <th>Titulo</th>
                    <th>Campeones</th>
                    <th>Clase fav.</th>
                    <th title="Posición media en partidas de Arena (más bajo = mejor)">Pos. media</th>
                    <th>Main Arena</th>
                    <th>Logros</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($jugadores as $i => $j):
                    $pos = $i + 1;
                    $posClass = match($pos) { 1 => 'pos-oro', 2 => 'pos-plata', 3 => 'pos-bronce', default => '' };
                    $claseEs  = LogrosManager::CLASES[$j['clase_favorita'] ?? ''] ?? '—';
                    $claseKey = strtolower($j['clase_favorita'] ?? '');
                    $riotId   = nombreDisplay($j) . ' (' . $j['game_name'] . '#' . $j['tag_line'] . ')';
                ?>
                <tr class="ranking-row <?= $pos <= 3 ? 'ranking-row--top' : '' ?>">
                    <td class="ranking-pos <?= $posClass ?>">
                        <?= match($pos) { 1 => '🥇', 2 => '🥈', 3 => '🥉', default => $pos } ?>
                    </td>
                    <td class="ranking-player">
                        <img
                            src="<?= profileIconUrl((int)$j['icono_id'], $version) ?>"
                            class="ranking-icon"
                            onerror="this.style.display='none'"
                        >
                        <div>
                            <a href="<?= urlPerfil($j['puuid'], $j['region']) ?>" class="ranking-name">
                                <?= htmlspecialchars(nombreDisplay($j)) ?>
                                <?php if (empty($j['apodo'])): ?>
                                <span class="tag">#<?= htmlspecialchars($j['tag_line']) ?></span>
                                <?php endif; ?>
                            </a>
                            <?php if (!empty($j['titulo_activo'])): ?>
                            <div class="ranking-titulo-activo">
                                <i class="fa-solid fa-tag"></i> <?= htmlspecialchars($j['titulo_activo']) ?>
                            </div>
                            <?php endif; ?>
                            <?php if (!empty($j['recientes'])): ?>
                            <div class="ranking-recientes">
                                Últimos: <?= htmlspecialchars(implode(', ', $j['recientes'])) ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    </td>
                    <td class="ranking-titulo"><?= tituloGracioso($j) ?></td>
                    <td class="ranking-total">
                        <span class="text-gold" style="font-size:1.2rem;font-weight:700"><?= $j['total'] ?></span>
                    </td>
                    <td>
                        <?php if ($j['clase_favorita']): ?>
                        <span class="clase-badge clase-<?= $claseKey ?>">
                            <?= $claseEs ?> (<?= $j['clase_favorita_n'] ?>)
                        </span>
                        <?php else: ?>
                        <span class="text-muted">—</span>
                        <?php endif; ?>
                    </td>
                    <td class="ranking-arena-pos">
                        <?php if ($j['arena_pos_media'] !== null): ?>
                            <span class="text-gold" style="font-size:1.1rem;font-weight:700">
                                <?= number_format($j['arena_pos_media'], 2, ',', '.') ?>
                            </span>
                            <div class="text-muted" style="font-size:.7rem"><?= $j['arena_partidas'] ?> partidas</div>
                        <?php else: ?>
                            <span class="text-muted">—</span>
                        <?php endif; ?>
                    </td>
                    <td class="ranking-main">
                        <?php if ($j['arena_main_nombre']):
                            $mainKey = resolverDDKey($champKeyById, $champKeyByName, $todosChamps, (int)$j['arena_main_id'], $j['arena_main_nombre']);
                        ?>
                        <img
                            src="<?= championImgUrl($mainKey, $version) ?>"
                            class="ranking-main-img"
                            title="Main Arena: <?= htmlspecialchars($j['arena_main_nombre']) ?> (<?= $j['arena_main_veces'] ?> partidas)"
                            onerror="this.style.display='none'"
                        >
                        <span class="ranking-main-name"><?= htmlspecialchars($j['arena_main_nombre']) ?></span>
                        <?php else: ?>
                        <span class="text-muted">—</span>
                        <?php endif; ?>
                    </td>
                    <td class="text-muted"><?= $j['logros_total'] ?> 🏅</td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Stats curiosas -->
    <h2 class="section-title">&#128202; Curiosidades del grupo</h2>
    <div class="curiosidades-grid">

        <!-- Campeones más populares -->
        <div class="card">
            <h3 class="card-title">&#128081; Campeones que todos tienen</h3>
            <?php if (empty($campeonesMasPopulares)): ?>
            <p class="text-muted">Nadie ha marcado nada aún.</p>
            <?php else: ?>
            <div class="curiosidad-list">
                <?php foreach ($campeonesMasPopulares as $c): ?>
                <div class="curiosidad-row">
                    <img src="<?= championImgUrl(resolverDDKey($champKeyById, $champKeyByName, $todosChamps, (int)$c['campeon_id'], $c['campeon_nombre']), $version) ?>" class="curiosidad-img"
                         onerror="this.style.display='none'">
                    <span class="curiosidad-nombre"><?= htmlspecialchars($c['campeon_nombre']) ?></span>
                    <span class="badge badge-win"><?= $c['jugadores'] ?> jugador<?= $c['jugadores'] > 1 ? 'es' : '' ?></span>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

        <!-- Campeones exclusivos -->
        <div class="card">
            <h3 class="card-title">&#128142; Solo yo lo tengo</h3>
            <?php if (empty($exclusivos)): ?>
            <p class="text-muted">Sin campeones exclusivos aún.</p>
            <?php else: ?>
            <div class="curiosidad-list">
                <?php foreach ($exclusivos as $c):
                    $owner = $puuidToName[$c['puuid']] ?? null;
                ?>
                <div class="curiosidad-row">
                    <img src="<?= championImgUrl(resolverDDKey($champKeyById, $champKeyByName, $todosChamps, (int)$c['campeon_id'], $c['campeon_nombre']), $version) ?>" class="curiosidad-img"
                         onerror="this.style.display='none'">
                    <span class="curiosidad-nombre"><?= htmlspecialchars($c['campeon_nombre']) ?></span>
                    <?php if ($owner): ?>
                    <span class="text-muted" style="font-size:.8rem">solo <?= htmlspecialchars(nombreDisplay($owner)) ?></span>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

        <!-- Primeros en marcar -->
        <div class="card">
            <h3 class="card-title">&#9889; Primeros en marcar</h3>
            <?php if (empty($primerosEnMarcar)): ?>
            <p class="text-muted">Sin datos aún.</p>
            <?php else: ?>
            <div class="curiosidad-list">
                <?php foreach ($primerosEnMarcar as $c):
                    $owner = $puuidToName[$c['puuid']] ?? null;
                ?>
                <div class="curiosidad-row">
                    <img src="<?= championImgUrl(resolverDDKey($champKeyById, $champKeyByName, $todosChamps, (int)$c['campeon_id'], $c['campeon_nombre']), $version) ?>" class="curiosidad-img"
                         onerror="this.style.display='none'">
                    <span class="curiosidad-nombre"><?= htmlspecialchars($c['campeon_nombre']) ?></span>
                    <?php if ($owner): ?>
                    <span class="text-muted" style="font-size:.8rem"><?= htmlspecialchars(nombreDisplay($owner)) ?></span>
                    <?php endif; ?>
                    <span class="text-muted" style="font-size:.75rem"><?= tiempoRelativo($c['fecha']) ?></span>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

        <!-- Mini-ranking por clase -->
        <div class="card">
            <h3 class="card-title">&#127942; Reyes por clase</h3>
            <div class="curiosidad-list">
                <?php foreach (LogrosManager::CLASES as $claseEn => $claseEs):
                    // Buscar quién tiene más de esta clase
                    $stmt = $db->prepare('
                        SELECT i.game_name, i.apodo, COUNT(*) as n
                        FROM campeones_ganados cg
                        JOIN invocadores i ON i.puuid = cg.puuid
                        WHERE cg.campeon_clase = ? AND i.pin_hash IS NOT NULL
                        GROUP BY cg.puuid ORDER BY n DESC LIMIT 1
                    ');
                    $stmt->execute([$claseEn]);
                    $rey = $stmt->fetch();
                ?>
                <div class="curiosidad-row">
                    <span class="clase-badge clase-<?= strtolower($claseEn) ?>"><?= $claseEs ?></span>
                    <?php if ($rey): ?>
                    <span class="curiosidad-nombre"><?= htmlspecialchars(nombreDisplay($rey)) ?></span>
                    <span class="badge"><?= $rey['n'] ?></span>
                    <?php else: ?>
                    <span class="text-muted">nadie aún</span>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

    </div>

    <!-- ===== Feed de actividad ===== -->
    <h2 class="section-title" style="margin-top:2.5rem">
        <i class="fa-solid fa-bolt" style="color:var(--gold)"></i> Actividad reciente del grupo
    </h2>
    <div class="card feed-card">
        <?php if (empty($feedLogros)): ?>
        <p class="text-muted" style="text-align:center;padding:1.5rem">Nadie ha desbloqueado logros aún. Marcad campeones para empezar.</p>
        <?php else: ?>
        <div class="feed-list">
            <?php foreach ($feedLogros as $f): ?>
            <div class="feed-item">
                <div class="feed-icon"><i class="<?= htmlspecialchars($f['logro_icono']) ?>"></i></div>
                <div class="feed-body">
                    <div class="feed-texto">
                        <a href="<?= urlPerfil($f['puuid'], $f['region']) ?>" class="feed-nombre">
                            <?= htmlspecialchars(nombreDisplay($f)) ?>
                        </a>
                        <span class="feed-accion">desbloqueó</span>
                        <strong class="feed-logro"><?= htmlspecialchars($f['logro_nombre']) ?></strong>
                        <?php if ($f['logro_titulo']): ?>
                        <span class="feed-titulo"><i class="fa-solid fa-tag"></i> "<?= htmlspecialchars($f['logro_titulo']) ?>"</span>
                        <?php endif; ?>
                    </div>
                    <div class="feed-tiempo text-muted"><?= tiempoRelativo($f['desbloqueado_en']) ?></div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

</div>

<?php include __DIR__ . '/includes/footer.php'; ?>

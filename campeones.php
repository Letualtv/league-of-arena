<?php
session_start();
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/RiotAPI.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/LogrosManager.php';

$puuid  = $_GET['puuid']  ?? null;
$region = strtoupper($_GET['region'] ?? 'EUW');

if (!$puuid || !isset(REGIONS[$region])) {
    header('Location: ' . BASE_URL);
    exit;
}

$db  = getDB();
$inv = $db->prepare('SELECT * FROM invocadores WHERE puuid = ?');
$inv->execute([$puuid]);
$invocador = $inv->fetch();
if (!$invocador) { header('Location: /Leagueofarena/'); exit; }

// Campeones ganados por este jugador
$stmt = $db->prepare('SELECT campeon_id FROM campeones_ganados WHERE puuid = ?');
$stmt->execute([$puuid]);
$ganados = array_column($stmt->fetchAll(), 'campeon_id', 'campeon_id');

$todosChamps = RiotAPI::getChampions();
$version     = RiotAPI::getDDragonVersion();
uasort($todosChamps, fn($a, $b) => strcmp($a['name'], $b['name']));

$totalChamps  = count($todosChamps);
$totalGanados = count($ganados);
$esOwner      = !empty($_SESSION['auth_' . $puuid]);

// Clases disponibles para filtrar
$clasesDisponibles = array_keys(LogrosManager::CLASES);

$pageTitle    = 'Campeones · ' . $invocador['game_name'];
$navPuuid     = $puuid;
$navRegion    = $region;
$navInvocador = $invocador;

include __DIR__ . '/includes/header.php';
?>

<div class="container">

    <div class="page-header">
        <div>
            <h1 class="page-title">Campeones ganados en Arena</h1>
            <p class="page-subtitle">
                <span class="text-gold"><?= $totalGanados ?></span> de <?= $totalChamps ?>
                (<?= $totalChamps > 0 ? round(($totalGanados / $totalChamps) * 100) : 0 ?>%)
            </p>
        </div>
        <div class="page-header-actions">
            <input type="text" id="champ-search" placeholder="Buscar campeon..." class="search-input-sm">
            <label class="toggle-label">
                <input type="checkbox" id="only-won"> Solo ganados
            </label>
        </div>
    </div>

    <!-- Barra de progreso -->
    <div class="progress-bar-wrap card">
        <div class="progress-label">
            <span>Progreso de coleccion</span>
            <span class="text-gold"><?= $totalGanados ?> / <?= $totalChamps ?></span>
        </div>
        <div class="progress-bar">
            <div class="progress-fill" style="width:<?= $totalChamps > 0 ? round(($totalGanados / $totalChamps) * 100) : 0 ?>%"></div>
        </div>
    </div>

    <!-- Filtros por clase -->
    <div class="clase-filtros">
        <button class="clase-filtro active" data-clase="">Todos</button>
        <?php foreach (LogrosManager::CLASES as $claseEn => $claseEs): ?>
        <button class="clase-filtro clase-filtro-<?= strtolower($claseEn) ?>" data-clase="<?= $claseEn ?>">
            <?= $claseEs ?>
        </button>
        <?php endforeach; ?>
    </div>

    <?php if (!$esOwner && !empty($invocador['pin_hash'])): ?>
    <div class="alert alert-info" style="margin-bottom:1rem">
        &#128274; Estas viendo el perfil en modo lectura.
        <a href="<?= BASE_URL ?>reclamar.php?puuid=<?= urlencode($puuid) ?>&region=<?= urlencode($region) ?>">Inicia sesion</a>
        para marcar campeones.
    </div>
    <?php elseif (!$esOwner && empty($invocador['pin_hash'])): ?>
    <div class="alert alert-info" style="margin-bottom:1rem">
        <a href="<?= BASE_URL ?>reclamar.php?puuid=<?= urlencode($puuid) ?>&region=<?= urlencode($region) ?>">Reclama esta cuenta</a>
        para poder marcar campeones.
    </div>
    <?php endif; ?>

    <!-- Grid de campeones -->
    <div class="champ-grid" id="champ-grid">
        <?php foreach ($todosChamps as $champ):
            $champId  = (int)$champ['key'];
            $ganado   = isset($ganados[$champId]);
            $claseEn  = $champ['tags'][0] ?? '';
            $claseEs  = LogrosManager::CLASES[$claseEn] ?? $claseEn;
        ?>
        <div
            class="champ-card <?= $ganado ? 'ganado' : '' ?>"
            data-id="<?= $champId ?>"
            data-nombre="<?= strtolower($champ['name']) ?>"
            data-ganado="<?= $ganado ? '1' : '0' ?>"
            data-clase="<?= htmlspecialchars($claseEn) ?>"
            title="<?= htmlspecialchars($champ['name']) ?> — <?= $claseEs ?>"
        >
            <div class="champ-card-img-wrap">
                <img
                    src="<?= championImgUrl($champ['id'], $version) ?>"
                    alt="<?= htmlspecialchars($champ['name']) ?>"
                    loading="lazy"
                    onerror="this.parentElement.innerHTML='<div class=\'champ-no-img\'><?= $champ['name'][0] ?></div>'"
                >
                <?php if ($ganado): ?>
                <div class="champ-card-badge">&#10003;</div>
                <?php endif; ?>
                <div class="champ-clase-tag clase-<?= strtolower($claseEn) ?>"><?= $claseEs ?></div>
                <?php if ($esOwner): ?>
                <button
                    class="btn-toggle-ganado"
                    data-puuid="<?= htmlspecialchars($puuid) ?>"
                    data-region="<?= htmlspecialchars($region) ?>"
                    data-id="<?= $champId ?>"
                    data-nombre="<?= htmlspecialchars($champ['name']) ?>"
                    data-clase="<?= htmlspecialchars($claseEn) ?>"
                    title="<?= $ganado ? 'Quitar marca' : 'Marcar como ganado' ?>"
                ><i class="fa-solid fa-<?= $ganado ? 'xmark' : 'plus' ?>"></i></button>
                <?php endif; ?>
            </div>
            <div class="champ-card-name"><?= htmlspecialchars($champ['name']) ?></div>
        </div>
        <?php endforeach; ?>
    </div>

</div>

<?php include __DIR__ . '/includes/footer.php'; ?>

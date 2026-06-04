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

    <p class="text-muted" style="margin-bottom:1rem;font-size:.9rem">
        <i class="fa-solid fa-circle-info"></i>
        Pulsa cualquier campeón para ver tus estadísticas con él en Arena.
    </p>

    <!-- Grid de campeones -->
    <div class="champ-grid" id="champ-grid">
        <?php foreach ($todosChamps as $champ):
            $champId  = (int)$champ['key'];
            $ganado   = isset($ganados[$champId]);
            $claseEn  = $champ['tags'][0] ?? '';
            $claseEs  = LogrosManager::CLASES[$claseEn] ?? $claseEn;
        ?>
        <div
            class="champ-card champ-card-clickable <?= $ganado ? 'ganado' : '' ?>"
            data-id="<?= $champId ?>"
            data-nombre="<?= strtolower($champ['name']) ?>"
            data-display="<?= htmlspecialchars($champ['name']) ?>"
            data-ddkey="<?= htmlspecialchars($champ['id']) ?>"
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
            </div>
            <div class="champ-card-name"><?= htmlspecialchars($champ['name']) ?></div>
        </div>
        <?php endforeach; ?>
    </div>

</div>

<!-- Modal de stats por campeón -->
<div id="champ-stats-modal" class="champ-modal-overlay" style="display:none">
    <div class="champ-modal">
        <button class="champ-modal-close" aria-label="Cerrar">&times;</button>
        <div class="champ-modal-header">
            <img id="modal-champ-img" alt="" onerror="this.style.display='none'">
            <div>
                <h2 id="modal-champ-name">—</h2>
                <div id="modal-champ-sub" class="text-muted"></div>
            </div>
        </div>
        <div id="modal-champ-body">
            <div class="text-muted" style="text-align:center;padding:2rem">Cargando…</div>
        </div>
    </div>
</div>

<style>
.champ-card-clickable { cursor: pointer; }
.champ-card-clickable:hover { transform: translateY(-2px); }

.champ-modal-overlay {
    position: fixed; inset: 0;
    background: rgba(0,0,0,0.75);
    z-index: 9999;
    display: flex; align-items: center; justify-content: center;
    padding: 1rem;
    backdrop-filter: blur(4px);
}
.champ-modal {
    background: var(--bg-card);
    border: 1px solid var(--gold);
    border-radius: 12px;
    max-width: 600px; width: 100%;
    max-height: 90vh; overflow-y: auto;
    padding: 1.5rem;
    position: relative;
}
.champ-modal-close {
    position: absolute; top: .5rem; right: .75rem;
    background: none; border: none; color: var(--text-muted);
    font-size: 2rem; cursor: pointer; line-height: 1;
    transition: color .15s;
}
.champ-modal-close:hover { color: var(--gold); }

.champ-modal-header {
    display: flex; align-items: center; gap: 1rem;
    margin-bottom: 2.25rem; padding-bottom: 1.25rem;
    border-bottom: 1px solid var(--border);
}
.champ-modal-header img {
    width: 72px; height: 72px;
    border-radius: 8px;
    border: 2px solid var(--gold);
}
.champ-modal-header h2 {
    font-family: 'Cinzel', serif;
    color: var(--text-bright);
    margin: 0;
}

.modal-stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(130px, 1fr));
    gap: .75rem;
    margin-bottom: 1.5rem;
}
.modal-stat {
    background: var(--bg-elevated);
    border: 1px solid var(--border);
    border-radius: 8px;
    padding: .75rem;
    text-align: center;
}
.modal-stat-val {
    font-family: 'Cinzel', serif;
    font-size: 1.4rem; font-weight: 700;
    color: var(--gold);
    line-height: 1.1;
}
.modal-stat-label {
    font-size: .7rem;
    color: var(--text-muted);
    text-transform: uppercase;
    letter-spacing: .05em;
    margin-top: .25rem;
}

.modal-dist-title {
    font-size: .85rem; color: var(--text-muted);
    margin-top: 1rem; margin-bottom: 1.25rem;
    text-transform: uppercase; letter-spacing: .05em;
    font-weight: 600;
}
.modal-dist {
    display: flex; gap: 6px; align-items: flex-end;
    height: 110px;
    padding-top: .5rem;
}
.modal-dist-bar {
    flex: 1;
    display: flex; flex-direction: column; align-items: center;
    gap: 4px;
}
.modal-dist-bar-fill {
    width: 100%; min-height: 4px;
    background: var(--border);
    border-radius: 4px 4px 0 0;
    transition: height .3s;
}
.modal-dist-bar-fill.pos-1 { background: #f0c040; }
.modal-dist-bar-fill.pos-2 { background: #b8c5d6; }
.modal-dist-bar-fill.pos-3 { background: #c97b3a; }
.modal-dist-bar-fill.pos-4 { background: #8a9ab4; }
.modal-dist-label {
    font-size: .7rem; color: var(--text-muted);
}
.modal-dist-n {
    font-size: .65rem; color: var(--text-bright); font-weight: 600;
}

.modal-empty {
    text-align: center; padding: 2rem 0;
    color: var(--text-muted);
}
.modal-empty i { font-size: 2.5rem; color: var(--gold); opacity: .4; display: block; margin-bottom: .75rem; }
</style>

<script>
(function() {
    const modal     = document.getElementById('champ-stats-modal');
    const modalImg  = document.getElementById('modal-champ-img');
    const modalName = document.getElementById('modal-champ-name');
    const modalSub  = document.getElementById('modal-champ-sub');
    const modalBody = document.getElementById('modal-champ-body');
    const closeBtn  = modal.querySelector('.champ-modal-close');

    const PUUID   = <?= json_encode($puuid) ?>;
    const VERSION = <?= json_encode($version) ?>;
    const DDBASE  = 'https://ddragon.leagueoflegends.com';

    function abrirModal(card) {
        const id      = card.dataset.id;
        const nombre  = card.dataset.display;
        const ddKey   = card.dataset.ddkey;
        const ganado  = card.dataset.ganado === '1';

        modalImg.src = `${DDBASE}/cdn/${VERSION}/img/champion/${ddKey}.png`;
        modalName.textContent = nombre;
        modalSub.textContent  = ganado ? '✓ Campeón ganado' : 'Aún sin victoria';
        modalBody.innerHTML   = '<div class="text-muted" style="text-align:center;padding:2rem">Cargando…</div>';
        modal.style.display   = 'flex';
        document.body.style.overflow = 'hidden';

        fetch(`${BASE_URL}ajax/campeon_stats.php?puuid=${encodeURIComponent(PUUID)}&campeon_id=${id}`)
            .then(async r => {
                const text = await r.text();
                try { return JSON.parse(text); }
                catch (e) { throw new Error('Respuesta no JSON: ' + text.slice(0, 200)); }
            })
            .then(d => {
                if (!d.ok) {
                    modalBody.innerHTML = `<div class="modal-empty"><i class="fa-solid fa-triangle-exclamation"></i>${d.error || 'Error desconocido'}${d.file ? '<br><small>' + d.file + '</small>' : ''}</div>`;
                    return;
                }
                renderStats(d);
            })
            .catch(err => { modalBody.innerHTML = `<div class="modal-empty"><i class="fa-solid fa-triangle-exclamation"></i>${err.message}</div>`; });
    }

    function renderStats(d) {
        if (!d.ok || d.partidas === 0) {
            modalBody.innerHTML = `
                <div class="modal-empty">
                    <i class="fa-solid fa-chart-bar"></i>
                    Aún no has jugado ninguna partida con este campeón en Arena.
                </div>`;
            return;
        }
        const wr     = ((d.victorias / d.partidas) * 100).toFixed(1);
        const top4r  = ((d.top4 / d.partidas) * 100).toFixed(1);
        const kdaR   = ((d.kills_tot + d.asist_tot) / Math.max(1, d.muertes_tot)).toFixed(2);
        const durMM  = Math.floor(d.dur_media / 60);
        const durSS  = String(Math.round(d.dur_media % 60)).padStart(2, '0');
        const fmt    = n => Math.round(n).toLocaleString('es-ES');
        const posMed = d.pos_media !== null ? d.pos_media.toFixed(2).replace('.', ',') : '—';

        // distribucion[0..7] = veces que quedó 1º..8º
        const dist    = Array.isArray(d.distribucion) ? d.distribucion : Object.values(d.distribucion || {});
        const maxDist = dist.length ? Math.max(...dist) : 0;
        const distBars = Array.from({length: 8}, (_, i) => {
            const pos = i + 1;
            const n   = dist[i] || 0;
            const h   = maxDist > 0 ? Math.max(4, (n / maxDist) * 70) : 4;
            const cls = pos <= 4 ? `pos-${pos}` : '';
            return `
                <div class="modal-dist-bar">
                    <div class="modal-dist-n">${n}</div>
                    <div class="modal-dist-bar-fill ${cls}" style="height:${h}px"></div>
                    <div class="modal-dist-label">${pos}º</div>
                </div>`;
        }).join('');

        modalBody.innerHTML = `
            <div class="modal-stats-grid">
                <div class="modal-stat"><div class="modal-stat-val">${d.partidas}</div><div class="modal-stat-label">Partidas</div></div>
                <div class="modal-stat"><div class="modal-stat-val">${d.victorias}</div><div class="modal-stat-label">Victorias · ${wr}%</div></div>
                <div class="modal-stat"><div class="modal-stat-val">${d.top4}</div><div class="modal-stat-label">Top 4 · ${top4r}%</div></div>
                <div class="modal-stat"><div class="modal-stat-val">${posMed}</div><div class="modal-stat-label">Pos. media</div></div>
                <div class="modal-stat"><div class="modal-stat-val">${kdaR}</div><div class="modal-stat-label">KDA medio</div></div>
                <div class="modal-stat"><div class="modal-stat-val">${fmt(d.dano_medio)}</div><div class="modal-stat-label">Daño medio</div></div>
                <div class="modal-stat"><div class="modal-stat-val">${durMM}:${durSS}</div><div class="modal-stat-label">Duración media</div></div>
                <div class="modal-stat"><div class="modal-stat-val">${d.mejor || '—'}º</div><div class="modal-stat-label">Mejor placement</div></div>
            </div>
            <div class="modal-dist-title">Distribución de posiciones</div>
            <div class="modal-dist">${distBars}</div>
        `;
    }

    function cerrar() {
        modal.style.display = 'none';
        document.body.style.overflow = '';
    }

    document.querySelectorAll('.champ-card-clickable').forEach(card => {
        card.addEventListener('click', () => abrirModal(card));
    });
    closeBtn.addEventListener('click', cerrar);
    modal.addEventListener('click', e => { if (e.target === modal) cerrar(); });
    document.addEventListener('keydown', e => { if (e.key === 'Escape') cerrar(); });
})();
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>

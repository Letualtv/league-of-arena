<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/db.php';
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

if (!$invocador) {
    header('Location: ' . BASE_URL);
    exit;
}

$lm     = new LogrosManager($db);
$stats  = $lm->getEstadisticas($puuid);
$logros = $lm->getTodosConProgreso($puuid);

$totalDesbloqueados = count(array_filter($logros, fn($l) => $l['desbloqueado']));

// Derivar categoría desde tipo/clave sin necesidad de columna extra en BD
function categoriaLogro(array $l): string {
    $tipo  = $l['tipo'];
    $clave = $l['clave'];
    if ($tipo === 'total') return 'Cantidad';
    if (in_array($tipo, ['Fighter','Tank','Mage','Assassin','Support','Marksman'])) return 'Clases';
    if ($tipo === 'campeon') return 'Campeones';
    if ($tipo === 'especial') {
        if (str_starts_with($clave, 'par_')) return 'Parejas';
        static $regiones = ['demacia_5','freljord_5','shurima_5','bilgewater_4','targon_4','piltover_5','noxus_5','ionia_5'];
        if (in_array($clave, $regiones)) return 'Regiones';
        static $lore = ['yordles','void_3','shadow_4','darkin','arcane_4','bestias_5','femeninas_5','ascendidos','kinkou_4','cazadores_4','tilt_3'];
        if (in_array($clave, $lore)) return 'Lore';
        return 'Especiales';
    }
    return 'Especiales';
}

$categorias = ['Todos','Cantidad','Clases','Regiones','Lore','Parejas','Campeones','Especiales'];

$pageTitle    = 'Logros · ' . $invocador['game_name'];
$navPuuid     = $puuid;
$navRegion    = $region;
$navInvocador = $invocador;

include __DIR__ . '/includes/header.php';
?>
<style>
/* Barra de filtros logros */
.logros-filtros {
  display: flex;
  flex-wrap: wrap;
  gap: .4rem;
  margin-bottom: 1rem;
}
.logro-filtro {
  padding: .3rem .8rem;
  border-radius: 999px;
  border: 1px solid var(--border);
  background: var(--bg-elevated);
  color: var(--text-muted);
  font-size: .78rem;
  cursor: pointer;
  transition: all .15s;
  font-family: 'Cinzel', serif;
  letter-spacing: .04em;
}
.logro-filtro:hover { border-color: var(--gold); color: var(--text); }
.logro-filtro.active { background: var(--gold); color: #1a0e00; border-color: var(--gold); font-weight: 700; }

/* Badge título dorado */
.logro-titulo-badge {
  display: inline-flex;
  align-items: center;
  gap: .35rem;
  font-family: 'Cinzel', serif;
  font-size: .75rem;
  font-weight: 700;
  color: var(--gold-bright);
  letter-spacing: .08em;
  margin: .4rem 0 .2rem;
  padding: .25rem .6rem;
  background: linear-gradient(135deg, rgba(200,155,60,.18), rgba(255,224,102,.08));
  border: 1px solid rgba(200,155,60,.45);
  border-radius: 4px;
  text-shadow: 0 0 8px rgba(255,224,102,.5);
  box-shadow: 0 0 10px rgba(200,155,60,.15);
}
.logro-titulo-badge i { color: var(--gold); font-size: .7rem; }
.logro-titulo-badge--locked {
  color: var(--text-muted);
  background: transparent;
  border-color: var(--border);
  text-shadow: none;
  box-shadow: none;
  opacity: .55;
}
.logro-estado-bloqueado {
  font-size: .75rem;
  margin-top: .4rem;
  display: flex;
  align-items: center;
  gap: .3rem;
  opacity: .5;
}

/* Sección de logros con separador */
.logros-seccion-titulo {
  font-family: 'Cinzel', serif;
  font-size: .8rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: .12em;
  color: var(--text-muted);
  margin: 1.5rem 0 .6rem;
  display: flex;
  align-items: center;
  gap: .6rem;
}
.logros-seccion-titulo::after {
  content: '';
  flex: 1;
  height: 1px;
  background: var(--border);
}
.logros-sin-resultados {
  text-align: center;
  color: var(--text-muted);
  padding: 2rem;
  font-size: .9rem;
  display: none;
}
</style>

<div class="container">

    <div class="page-header">
        <div>
            <h1 class="page-title">Logros de Arena</h1>
            <p class="page-subtitle">
                <span class="text-gold"><?= $totalDesbloqueados ?></span> de <?= count($logros) ?> desbloqueados
            </p>
        </div>
        <div class="page-header-actions">
            <input type="text" id="logro-search" placeholder="Buscar logro o campeon..."
                   class="search-input-sm" style="min-width:200px">
        </div>
    </div>

    <!-- Stats -->
    <div class="stats-grid stats-grid--sm card" style="margin-bottom:1.5rem">
        <div class="stat-item">
            <div class="stat-num text-gold"><?= $stats['total'] ?></div>
            <div class="stat-lbl">Camps. ganados</div>
        </div>
        <div class="stat-item">
            <div class="stat-num"><?= $stats['clases_distintas'] ?></div>
            <div class="stat-lbl">Clases distintas</div>
        </div>
        <div class="stat-item">
            <div class="stat-num"><?= $stats['max_por_clase'] ?></div>
            <div class="stat-lbl">Max. en una clase</div>
        </div>
        <div class="stat-item">
            <div class="stat-num"><?= $stats['yordles'] ?></div>
            <div class="stat-lbl">Yordles</div>
        </div>
        <div class="stat-item">
            <div class="stat-num" style="font-size:1rem;color:var(--text-muted)">
                <?= $stats['clase_favorita'] ? (LogrosManager::CLASES[$stats['clase_favorita']] ?? '—') : '—' ?>
            </div>
            <div class="stat-lbl">Clase favorita</div>
        </div>
    </div>

    <!-- Filtros de categoría -->
    <div class="logros-filtros">
        <?php foreach ($categorias as $cat): ?>
        <button class="logro-filtro <?= $cat === 'Todos' ? 'active' : '' ?>" data-cat="<?= $cat ?>">
            <?= $cat ?>
        </button>
        <?php endforeach; ?>
    </div>

    <!-- Desbloqueados -->
    <?php
    $hayDesbloqueados = !empty(array_filter($logros, fn($l) => $l['desbloqueado']));
    if ($hayDesbloqueados):
    ?>
    <div class="logros-seccion-titulo" id="titulo-desbloqueados">
        <i class="fa-solid fa-circle-check" style="color:var(--support)"></i> Desbloqueados
    </div>
    <div class="logros-grid" id="grid-desbloqueados">
        <?php foreach ($logros as $l):
            if (!$l['desbloqueado']) continue;
            $cat = categoriaLogro($l);
            $textoB = strtolower($l['nombre'] . ' ' . $l['descripcion'] . ' ' . ($l['titulo'] ?? ''));
        ?>
        <div class="logro-card logro-card--done card"
             data-cat="<?= htmlspecialchars($cat) ?>"
             data-texto="<?= htmlspecialchars($textoB) ?>">
            <div class="logro-card-icon"><i class="<?= htmlspecialchars($l['icono']) ?>"></i></div>
            <div class="logro-card-body">
                <div class="logro-card-nombre"><?= htmlspecialchars($l['nombre']) ?></div>
                <div class="logro-card-desc"><?= htmlspecialchars($l['descripcion']) ?></div>
                <?php if (!empty($l['titulo'])): ?>
                <div class="logro-titulo-badge"><i class="fa-solid fa-tag"></i> "<?= htmlspecialchars($l['titulo']) ?>"</div>
                <?php endif; ?>
                <div class="logro-fecha text-muted">Desbloqueado: <?= date('d/m/Y', strtotime($l['desbloqueado_en'])) ?></div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Pendientes -->
    <div class="logros-seccion-titulo" id="titulo-pendientes" style="margin-top:<?= $hayDesbloqueados ? '2rem' : '0' ?>">
        <i class="fa-solid fa-lock" style="color:var(--text-muted)"></i> Pendientes
    </div>
    <div class="logros-grid" id="grid-pendientes">
        <?php foreach ($logros as $l):
            if ($l['desbloqueado']) continue;
            $cat = categoriaLogro($l);
            $textoP = strtolower($l['nombre'] . ' ' . $l['descripcion'] . ' ' . ($l['titulo'] ?? ''));
            $esNuevo = !empty($l['creado_en']) && (time() - strtotime($l['creado_en'])) < 14 * 86400;
        ?>
        <div class="logro-card card <?= $esNuevo ? 'logro-card--nuevo' : '' ?>"
             data-cat="<?= htmlspecialchars($cat) ?>"
             data-texto="<?= htmlspecialchars($textoP) ?>">
            <?php if ($esNuevo): ?><div class="badge-nuevo">NUEVO</div><?php endif; ?>
            <div class="logro-card-icon locked"><i class="<?= htmlspecialchars($l['icono']) ?>"></i></div>
            <div class="logro-card-body">
                <div class="logro-card-nombre"><?= htmlspecialchars($l['nombre']) ?></div>
                <div class="logro-card-desc"><?= htmlspecialchars($l['descripcion']) ?></div>
                <?php if (!empty($l['titulo'])): ?>
                <div class="logro-titulo-badge logro-titulo-badge--locked"><i class="fa-solid fa-tag"></i> "<?= htmlspecialchars($l['titulo']) ?>"</div>
                <?php endif; ?>
                <div class="logro-estado-bloqueado"><i class="fa-solid fa-lock"></i> No conseguido</div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <div class="logros-sin-resultados" id="sin-resultados">
        <i class="fa-solid fa-magnifying-glass" style="font-size:2rem;opacity:.3;display:block;margin-bottom:.5rem"></i>
        Sin resultados para tu búsqueda
    </div>

</div>

<script>
(function () {
    let catActiva = 'Todos';
    let query = '';

    const grids   = document.querySelectorAll('.logros-grid');
    const cards   = document.querySelectorAll('.logro-card');
    const sinRes  = document.getElementById('sin-resultados');
    const titDes  = document.getElementById('titulo-desbloqueados');
    const titPen  = document.getElementById('titulo-pendientes');
    const gridDes = document.getElementById('grid-desbloqueados');
    const gridPen = document.getElementById('grid-pendientes');

    function filtrar() {
        let visibleDes = 0, visiblePen = 0;

        cards.forEach(card => {
            const cat    = card.dataset.cat;
            const texto  = card.dataset.texto || '';
            const esDes  = card.classList.contains('logro-card--done');
            const matchCat  = catActiva === 'Todos' || cat === catActiva;
            const matchText = !query || texto.includes(query);
            const visible   = matchCat && matchText;

            card.style.display = visible ? '' : 'none';
            if (visible) esDes ? visibleDes++ : visiblePen++;
        });

        if (titDes) titDes.style.display = visibleDes ? '' : 'none';
        if (gridDes) gridDes.style.display = visibleDes ? '' : 'none';
        titPen.style.display = visiblePen ? '' : 'none';
        gridPen.style.display = visiblePen ? '' : 'none';
        sinRes.style.display = (visibleDes + visiblePen === 0) ? 'block' : 'none';
    }

    // Filtros de categoría
    document.querySelectorAll('.logro-filtro').forEach(btn => {
        btn.addEventListener('click', () => {
            document.querySelectorAll('.logro-filtro').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            catActiva = btn.dataset.cat;
            filtrar();
        });
    });

    // Buscador
    document.getElementById('logro-search').addEventListener('input', e => {
        query = e.target.value.toLowerCase().trim();
        filtrar();
    });
})();
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>

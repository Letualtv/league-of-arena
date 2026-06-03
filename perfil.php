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

if (!$invocador) {
    header('Location: ' . BASE_URL);
    exit;
}

// Guardar apodo si el owner lo envía
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'set_apodo') {
    if (!empty($_SESSION['auth_' . $puuid])) {
        $apodo = trim($_POST['apodo'] ?? '');
        $apodo = $apodo === '' ? null : mb_substr($apodo, 0, 50);
        $db->prepare('UPDATE invocadores SET apodo = ? WHERE puuid = ?')->execute([$apodo, $puuid]);
        // Refrescar datos
        $inv->execute([$puuid]);
        $invocador = $db->prepare('SELECT * FROM invocadores WHERE puuid = ?');
        $invocador->execute([$puuid]);
        $invocador = $invocador->fetch();
    }
}

$lm        = new LogrosManager($db);
$stats     = $lm->getEstadisticas($puuid);
$lm->verificarYDesbloquear($puuid);
try { $titulos = $lm->getTitulosDesbloqueados($puuid); } catch (PDOException $e) { $titulos = []; }

// Campeones ganados (top #1)
$stmt = $db->prepare('SELECT * FROM campeones_ganados WHERE puuid = ? ORDER BY campeon_nombre ASC');
$stmt->execute([$puuid]);
$recientes = $stmt->fetchAll();

// Mapas para resolver DDragon key correctamente
$champKeyById   = [];
$champKeyByName = [];
foreach ($todosChamps as $champ) {
    $champKeyById[(int)$champ['key']]            = $champ['id'];
    $champKeyByName[strtolower($champ['name'])]  = $champ['id'];
}

// Logros desbloqueados recientes
$stmt = $db->prepare('
    SELECT l.*, ld.desbloqueado_en FROM logros_desbloqueados ld
    JOIN logros l ON l.id = ld.logro_id
    WHERE ld.puuid = ? ORDER BY ld.desbloqueado_en DESC LIMIT 6
');
$stmt->execute([$puuid]);
$logrosRecientes = $stmt->fetchAll();

// Total de campeones en el juego (para % progreso)
$todosChamps = RiotAPI::getChampions();
$totalChamps = count($todosChamps);
$version     = RiotAPI::getDDragonVersion();

// ---- Data para tarjeta de compartir ----
try {
    $stmtRankPos = $db->query('SELECT puuid FROM (SELECT puuid, COUNT(*) as n FROM campeones_ganados WHERE puuid IN (SELECT puuid FROM invocadores WHERE pin_hash IS NOT NULL) GROUP BY puuid ORDER BY n DESC) AS ranked');
    $rankPuuids  = array_column($stmtRankPos->fetchAll(), 'puuid');
    $rankingPos  = ($idx = array_search($puuid, $rankPuuids)) !== false ? (int)$idx + 1 : 0;
} catch (PDOException $e) { $rankingPos = 0; }

$stmtLT = $db->prepare('SELECT COUNT(*) FROM logros_desbloqueados WHERE puuid = ?');
$stmtLT->execute([$puuid]);
$logrosCount = (int)$stmtLT->fetchColumn();

$champShare = [];
foreach (array_slice($recientes, 0, 5) as $c) {
    $ddKey = null;
    $norm  = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $c['campeon_nombre']));
    foreach ($todosChamps as $ch) {
        if (strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $ch['name'])) === $norm) { $ddKey = $ch['id']; break; }
    }
    $ddKey = $ddKey ?? preg_replace('/[^a-zA-Z0-9]/', '', $c['campeon_nombre']);
    $champShare[] = ['nombre' => $c['campeon_nombre'], 'url' => championImgUrl($ddKey, $version)];
}

$esOwner      = !empty($_SESSION['auth_' . $puuid]);
$reclamado    = !empty($invocador['pin_hash']);
$pageTitle    = nombreDisplay($invocador) . ' — Leagueofarena';
$navPuuid     = $puuid;
$navRegion    = $region;
$navInvocador = $invocador;

include __DIR__ . '/includes/header.php';
?>

<div class="container">

    <!-- Cabecera del perfil -->
    <div class="profile-header card">
        <img
            src="<?= profileIconUrl((int)$invocador['icono_id'], $version) ?>"
            alt="Icono"
            class="profile-icon"
            onerror="this.src='https://ddragon.leagueoflegends.com/cdn/<?= $version ?>/img/profileicon/1.png'"
        >
        <div class="profile-info">
            <h1 class="profile-name">
                <?= htmlspecialchars(nombreDisplay($invocador)) ?>
                <?php if (empty($invocador['apodo'])): ?>
                <span class="tag">#<?= htmlspecialchars($invocador['tag_line']) ?></span>
                <?php else: ?>
                <span class="tag" style="font-size:.75em;opacity:.65"><?= htmlspecialchars($invocador['game_name']) ?>#<?= htmlspecialchars($invocador['tag_line']) ?></span>
                <?php endif; ?>
            </h1>
            <?php if (!empty($invocador['titulo_activo'])): ?>
            <div class="titulo-activo">
                <i class="fa-solid fa-tag"></i>
                <?= htmlspecialchars($invocador['titulo_activo']) ?>
            </div>
            <?php endif; ?>
            <div class="profile-meta">
                <span class="badge badge-region"><?= htmlspecialchars($region) ?></span>
                <?php if ($invocador['nivel']): ?>
                <span class="badge">Nivel <?= (int)$invocador['nivel'] ?></span>
                <?php endif; ?>
            </div>
        </div>
        <div style="display:flex;flex-direction:column;gap:.6rem;align-items:flex-end">
            <div style="display:flex;gap:.75rem;align-items:center;flex-wrap:wrap">
                <button class="btn btn-outline btn-sm" id="btn-sync"
                        data-puuid="<?= htmlspecialchars($puuid) ?>"
                        data-region="<?= htmlspecialchars($region) ?>">
                    &#8635; Actualizar perfil
                </button>
                <button class="btn btn-outline btn-sm" id="btn-share" title="Compartir perfil">
                    <i class="fa-solid fa-share-nodes"></i> Compartir
                </button>
                <?php if (!$reclamado): ?>
                    <a href="<?= BASE_URL ?>reclamar.php?puuid=<?= urlencode($puuid) ?>&region=<?= urlencode($region) ?>"
                       class="btn btn-primary">&#128296; Reclamar cuenta</a>
                <?php elseif (!$esOwner): ?>
                    <a href="<?= BASE_URL ?>reclamar.php?puuid=<?= urlencode($puuid) ?>&region=<?= urlencode($region) ?>"
                       class="btn btn-outline">&#128274; Iniciar sesion</a>
                <?php endif; ?>
            </div>
            <?php if ($esOwner && !empty($titulos)): ?>
            <div style="margin-top:.75rem;padding:.75rem 1rem;background:var(--bg-elevated);border:1px solid var(--gold);border-radius:8px;display:flex;align-items:center;gap:.75rem;flex-wrap:wrap;">
                <label for="select-titulo" style="color:var(--gold);font-family:'Cinzel',serif;font-size:.85rem;font-weight:600;white-space:nowrap;">
                    <i class="fa-solid fa-tag"></i> Título activo
                </label>
                <select id="select-titulo" data-puuid="<?= htmlspecialchars($puuid) ?>"
                        style="flex:1;min-width:160px;background:var(--bg-card);color:var(--text-bright);
                               border:1px solid var(--border);border-radius:6px;
                               padding:.45rem .9rem;font-family:'Cinzel',serif;
                               font-size:.9rem;cursor:pointer;">
                    <option value="">— Sin título —</option>
                    <?php foreach ($titulos as $t): ?>
                    <option value="<?= htmlspecialchars($t) ?>"
                        <?= ($invocador['titulo_activo'] ?? '') === $t ? 'selected' : '' ?>>
                        <?= htmlspecialchars($t) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                <span id="titulo-feedback" style="font-size:.85rem;color:var(--support);display:none">
                    <i class="fa-solid fa-check"></i> Guardado
                </span>
            </div>
            <?php endif; ?>
            <?php if ($esOwner): ?>
            <form method="post" action="<?= BASE_URL ?>perfil.php?puuid=<?= urlencode($puuid) ?>&region=<?= urlencode($region) ?>"
                  style="margin-top:.75rem;padding:.75rem 1rem;background:var(--bg-elevated);border:1px solid var(--border);border-radius:8px;display:flex;align-items:center;gap:.75rem;flex-wrap:wrap;">
                <input type="hidden" name="action" value="set_apodo">
                <label style="color:var(--text-muted);font-size:.85rem;font-weight:600;white-space:nowrap;">
                    <i class="fa-solid fa-pen"></i> Apodo
                </label>
                <div style="flex:1;min-width:140px;position:relative">
                    <input type="text" name="apodo" id="apodo-input" maxlength="50"
                           placeholder="<?= htmlspecialchars($invocador['game_name']) ?>"
                           value="<?= htmlspecialchars($invocador['apodo'] ?? '') ?>"
                           style="width:100%;background:var(--bg-card);color:var(--text-bright);
                                  border:1px solid var(--border);border-radius:6px;
                                  padding:.4rem 3rem .4rem .85rem;font-size:.9rem;box-sizing:border-box;">
                    <span id="apodo-count" style="position:absolute;right:.6rem;top:50%;transform:translateY(-50%);
                                                  font-size:.72rem;color:var(--text-muted);pointer-events:none">
                        <?= mb_strlen($invocador['apodo'] ?? '') ?>/50
                    </span>
                </div>
                <button type="submit" class="btn btn-outline btn-sm">Guardar</button>
                <?php if (!empty($invocador['apodo'])): ?>
                <button type="submit" name="apodo" value=""
                        class="btn btn-sm"
                        style="color:var(--text-muted);background:transparent;border:1px solid var(--border)"
                        onclick="return confirm('¿Quitar el apodo?')">
                    <i class="fa-solid fa-pen-slash"></i> Quitar apodo
                </button>
                <?php endif; ?>
                <script>
                (function(){
                    var inp = document.getElementById('apodo-input');
                    var cnt = document.getElementById('apodo-count');
                    if (!inp) return;
                    inp.addEventListener('input', function(){
                        var n = inp.value.length;
                        cnt.textContent = n + '/50';
                        cnt.style.color = n >= 45 ? 'var(--fire,#ff7a45)' : 'var(--text-muted)';
                    });
                })();
                </script>
            </form>
            <?php endif; ?>
        </div>
    </div>

    <div id="sync-msg" class="alert" style="display:none"></div>

    <!-- Progreso general -->
    <div class="card" style="margin-bottom:1.5rem">
        <div class="progress-label">
            <span style="font-weight:600">Campeones ganados</span>
            <span><span class="text-gold" style="font-size:1.3rem;font-weight:700"><?= $stats['total'] ?></span> / <?= $totalChamps ?></span>
        </div>
        <div class="progress-bar" style="height:12px;margin-top:.5rem">
            <div class="progress-fill" style="width:<?= $totalChamps > 0 ? round(($stats['total'] / $totalChamps) * 100) : 0 ?>%"></div>
        </div>
        <div style="text-align:right;font-size:.8rem;color:var(--text-muted);margin-top:.3rem">
            <?= $totalChamps > 0 ? round(($stats['total'] / $totalChamps) * 100) : 0 ?>% del total
        </div>
    </div>

    <div class="two-col">

        <!-- Stats por clase -->
        <div class="card">
            <h2 class="card-title">Por clase de campeon</h2>
            <div class="clase-list">
                <?php foreach (LogrosManager::CLASES as $claseEn => $claseEs):
                    $n = $stats['clase_' . $claseEn];
                ?>
                <div class="clase-row">
                    <span class="clase-badge clase-<?= strtolower($claseEn) ?>"><?= $claseEs ?></span>
                    <div class="progress-bar" style="flex:1">
                        <div class="progress-fill clase-fill-<?= strtolower($claseEn) ?>"
                             style="width:<?= $n > 0 ? min(100, round(($n / max(1, $stats['total'])) * 100)) : 0 ?>%"></div>
                    </div>
                    <span class="clase-n text-gold"><?= $n ?></span>
                </div>
                <?php endforeach; ?>
            </div>
            <?php if ($esOwner): ?>
            <a href="<?= urlCampeones($puuid, $region) ?>" class="btn btn-primary" style="margin-top:1.25rem;width:100%;justify-content:center">
                &#43; Marcar campeones ganados
            </a>
            <?php endif; ?>
        </div>

        <!-- Logros recientes -->
        <div class="card">
            <h2 class="card-title">Logros desbloqueados</h2>
            <?php if (empty($logrosRecientes)): ?>
            <p class="text-muted">Aun no tienes logros. Empieza a marcar campeones.</p>
            <?php else: ?>
            <div class="logros-mini">
                <?php foreach ($logrosRecientes as $l): ?>
                <div class="logro-mini-item">
                    <span class="logro-icon"><i class="<?= htmlspecialchars($l['icono']) ?>"></i></span>
                    <div>
                        <div class="logro-nombre"><?= htmlspecialchars($l['nombre']) ?></div>
                        <div class="logro-fecha text-muted"><?= tiempoRelativo($l['desbloqueado_en']) ?></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
            <a href="<?= urlLogros($puuid, $region) ?>" class="btn btn-sm btn-outline" style="margin-top:1rem">
                Ver todos los logros &rarr;
            </a>
        </div>

    </div>

    <!-- Campeones ganados top #1 -->
    <?php if (!empty($recientes)): ?>
    <div class="card">
        <h2 class="card-title">Tus top #1 <span style="font-size:.85rem;color:var(--text-muted);font-weight:400">(<?= count($recientes) ?> campeones)</span></h2>
        <div class="recientes-grid">
            <?php foreach ($recientes as $c):
                $ddKey = $champKeyById[(int)$c['campeon_id']]
                      ?? $champKeyByName[strtolower($c['campeon_nombre'])]
                      ?? null;
                if (!$ddKey) {
                    // Búsqueda tolerante: ignorar apóstrofes, espacios y mayúsculas
                    $normalizado = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $c['campeon_nombre']));
                    foreach ($todosChamps as $ch) {
                        if (strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $ch['name'])) === $normalizado) {
                            $ddKey = $ch['id'];
                            break;
                        }
                    }
                    $ddKey = $ddKey ?? preg_replace('/[^a-zA-Z0-9]/', '', $c['campeon_nombre']);
                }
            ?>
            <div class="reciente-item" title="<?= htmlspecialchars($c['campeon_nombre']) ?>">
                <img
                    src="<?= championImgUrl($ddKey, $version) ?>"
                    alt="<?= htmlspecialchars($c['campeon_nombre']) ?>"
                    onerror="this.style.display='none'"
                >
                <span class="reciente-nombre"><?= htmlspecialchars($c['campeon_nombre']) ?></span>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>


</div>

<script>
const selectTitulo = document.getElementById('select-titulo');
if (selectTitulo) {
    selectTitulo.addEventListener('change', async () => {
        const puuid  = selectTitulo.dataset.puuid;
        const titulo = selectTitulo.value;
        const form   = new FormData();
        form.append('puuid', puuid);
        form.append('titulo', titulo);
        const res  = await fetch(BASE_URL + 'ajax/set_titulo.php', { method: 'POST', body: form });
        const data = await res.json();
        if (data.ok) {
            const feedback = document.getElementById('titulo-feedback');
            feedback.style.display = '';
            setTimeout(() => feedback.style.display = 'none', 2000);
            const display = document.querySelector('.titulo-activo');
            if (titulo) {
                if (display) { display.innerHTML = '<i class="fa-solid fa-tag"></i> ' + titulo; display.style.display = ''; }
                else {
                    const el = document.createElement('div');
                    el.className = 'titulo-activo';
                    el.innerHTML = '<i class="fa-solid fa-tag"></i> ' + titulo;
                    document.querySelector('.profile-name').after(el);
                }
            } else if (display) {
                display.style.display = 'none';
            }
        }
    });
}
</script>

<script>
// Polyfill roundRect para navegadores antiguos
if (!CanvasRenderingContext2D.prototype.roundRect) {
    CanvasRenderingContext2D.prototype.roundRect = function(x,y,w,h,r){
        r=Math.min(r,w/2,h/2);
        this.beginPath();
        this.moveTo(x+r,y); this.lineTo(x+w-r,y); this.quadraticCurveTo(x+w,y,x+w,y+r);
        this.lineTo(x+w,y+h-r); this.quadraticCurveTo(x+w,y+h,x+w-r,y+h);
        this.lineTo(x+r,y+h); this.quadraticCurveTo(x,y+h,x,y+h-r);
        this.lineTo(x,y+r); this.quadraticCurveTo(x,y,x+r,y);
        this.closePath();
    };
}

const SHARE_DATA = <?= json_encode([
    'nombre'      => $invocador['game_name'],
    'tag'         => $invocador['tag_line'],
    'region'      => $region,
    'nivel'       => (int)($invocador['nivel'] ?? 0),
    'titulo'      => $invocador['titulo_activo'] ?? '',
    'total'       => (int)$stats['total'],
    'totalChamps' => (int)$totalChamps,
    'rankingPos'  => $rankingPos,
    'logrosTotal' => $logrosCount,
    'claseFav'    => LogrosManager::CLASES[$stats['clase_favorita'] ?? ''] ?? '',
    'claseFavN'   => (int)($stats['clase_favorita_n'] ?? 0),
    'iconUrl'     => profileIconUrl((int)$invocador['icono_id'], $version),
    'campeones'   => $champShare,
], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>;

async function crearTarjetaCompartir() {
    const W = 900, H = 480;
    const canvas = document.createElement('canvas');
    canvas.width = W; canvas.height = H;
    const ctx = canvas.getContext('2d');

    const GOLD     = '#c89b3c';
    const GOLD_DIM = 'rgba(200,155,60,0.28)';
    const TEXT     = '#f0e6d3';
    const MUTED    = 'rgba(240,230,211,0.45)';

    const loadImg = src => new Promise(res => {
        const img = new Image(); img.crossOrigin = 'anonymous';
        img.onload = () => res(img); img.onerror = () => res(null); img.src = src;
    });

    await document.fonts.ready;

    // Fondo degradado
    const bg = ctx.createLinearGradient(0, 0, W, H);
    bg.addColorStop(0, '#0d0d16'); bg.addColorStop(1, '#13111f');
    ctx.fillStyle = bg; ctx.fillRect(0, 0, W, H);

    // Textura de rejilla sutil
    ctx.strokeStyle = 'rgba(200,155,60,0.05)'; ctx.lineWidth = 1;
    for (let x = 0; x < W; x += 44) { ctx.beginPath(); ctx.moveTo(x,0); ctx.lineTo(x,H); ctx.stroke(); }
    for (let y = 0; y < H; y += 44) { ctx.beginPath(); ctx.moveTo(0,y); ctx.lineTo(W,y); ctx.stroke(); }

    // Bordes dorados
    ctx.strokeStyle = GOLD; ctx.lineWidth = 2.5; ctx.strokeRect(10,10,W-20,H-20);
    ctx.strokeStyle = GOLD_DIM; ctx.lineWidth = 1; ctx.strokeRect(15,15,W-30,H-30);

    // Diamantes en las esquinas
    const D = (x,y) => { ctx.save(); ctx.translate(x,y); ctx.rotate(Math.PI/4); ctx.fillStyle=GOLD; ctx.fillRect(-5,-5,10,10); ctx.restore(); };
    [[10,10],[W-10,10],[10,H-10],[W-10,H-10]].forEach(([x,y]) => D(x,y));

    // Branding arriba derecha
    ctx.font='500 12px Inter,sans-serif'; ctx.fillStyle='rgba(200,155,60,0.45)'; ctx.textAlign='right';
    ctx.fillText('leagueofarena.gamer.free', W-22, 34);

    // ---- Icono de perfil ----
    const IR=58, IX=32, IY=44;
    const iconImg = await loadImg(SHARE_DATA.iconUrl);
    if (iconImg) {
        ctx.save(); ctx.beginPath(); ctx.arc(IX+IR,IY+IR,IR,0,Math.PI*2); ctx.clip();
        ctx.drawImage(iconImg, IX,IY, IR*2,IR*2); ctx.restore();
        ctx.strokeStyle=GOLD; ctx.lineWidth=3;
        ctx.beginPath(); ctx.arc(IX+IR,IY+IR,IR+3,0,Math.PI*2); ctx.stroke();
    }

    // ---- Nombre + tag ----
    const NX = IX + IR*2 + 22;
    ctx.textAlign = 'left';
    const nameText = SHARE_DATA.nombre + '  #' + SHARE_DATA.tag;
    let fs = 34;
    ctx.font = `700 ${fs}px Cinzel,serif`;
    while (ctx.measureText(nameText).width > W - NX - 220 && fs > 18) { fs -= 2; ctx.font = `700 ${fs}px Cinzel,serif`; }
    ctx.fillStyle = TEXT; ctx.fillText(nameText, NX, IY + 42);

    // Título activo
    if (SHARE_DATA.titulo) {
        ctx.font = 'italic 15px Cinzel,serif'; ctx.fillStyle = GOLD;
        ctx.fillText('✦  ' + SHARE_DATA.titulo + '  ✦', NX, IY + 64);
    }

    // Badges región / nivel
    let bx=NX, by=IY + (SHARE_DATA.titulo ? 84 : 64);
    const drawBadge = (txt, bgC, fgC) => {
        ctx.font='600 12px Inter,sans-serif';
        const tw=ctx.measureText(txt).width+16;
        ctx.fillStyle=bgC; ctx.beginPath(); ctx.roundRect(bx,by,tw,22,4); ctx.fill();
        ctx.fillStyle=fgC; ctx.fillText(txt,bx+8,by+15); bx+=tw+8;
    };
    drawBadge(SHARE_DATA.region, 'rgba(200,155,60,0.18)', GOLD);
    if (SHARE_DATA.nivel) drawBadge('Nivel '+SHARE_DATA.nivel, 'rgba(255,255,255,0.07)', 'rgba(240,230,211,0.7)');

    // ---- Cajas de stats ----
    const SY=196, SW=128, SH=92;
    const statBox = (x, icon, val, lbl) => {
        ctx.fillStyle='rgba(200,155,60,0.07)'; ctx.beginPath(); ctx.roundRect(x,SY,SW,SH,7); ctx.fill();
        ctx.strokeStyle=GOLD_DIM; ctx.lineWidth=1; ctx.beginPath(); ctx.roundRect(x,SY,SW,SH,7); ctx.stroke();
        ctx.textAlign='center';
        ctx.font='20px serif'; ctx.fillStyle=TEXT; ctx.fillText(icon,x+SW/2,SY+27);
        ctx.font='700 26px Cinzel,serif'; ctx.fillStyle=GOLD; ctx.fillText(String(val),x+SW/2,SY+58);
        ctx.font='400 11px Inter,sans-serif'; ctx.fillStyle=MUTED; ctx.fillText(lbl,x+SW/2,SY+76);
    };
    const posIco = SHARE_DATA.rankingPos===1?'🥇':SHARE_DATA.rankingPos===2?'🥈':SHARE_DATA.rankingPos===3?'🥉':'⚔️';
    statBox(32,  '🏆', SHARE_DATA.total,       'campeones ganados');
    statBox(172, posIco, SHARE_DATA.rankingPos?'#'+SHARE_DATA.rankingPos:'—', 'en el ranking');
    statBox(312, '🏅', SHARE_DATA.logrosTotal,  'logros');

    // Separador vertical
    ctx.strokeStyle=GOLD_DIM; ctx.lineWidth=1;
    ctx.beginPath(); ctx.moveTo(455,SY+8); ctx.lineTo(455,SY+SH-8); ctx.stroke();

    // Porcentaje de progreso
    const PX=468, PW=W-PX-32;
    const pct=SHARE_DATA.totalChamps>0?Math.round(SHARE_DATA.total/SHARE_DATA.totalChamps*100):0;
    ctx.textAlign='left'; ctx.font='600 11px Inter,sans-serif'; ctx.fillStyle=MUTED;
    ctx.fillText('PROGRESO TOTAL', PX, SY+15);
    ctx.textAlign='center'; ctx.font='700 38px Cinzel,serif'; ctx.fillStyle=GOLD;
    ctx.fillText(pct+'%', PX+PW/2, SY+60);
    ctx.textAlign='left'; ctx.font='400 11px Inter,sans-serif'; ctx.fillStyle=MUTED;
    ctx.fillText(SHARE_DATA.total+' / '+SHARE_DATA.totalChamps, PX, SY+76);
    // Barra
    const BRY=SY+SH+4, BRW=PW;
    ctx.fillStyle='rgba(255,255,255,0.07)'; ctx.beginPath(); ctx.roundRect(PX,BRY,BRW,6,3); ctx.fill();
    const fw=BRW*(SHARE_DATA.total/Math.max(1,SHARE_DATA.totalChamps));
    if(fw>0){ const g=ctx.createLinearGradient(PX,0,PX+fw,0); g.addColorStop(0,'#785a28'); g.addColorStop(1,'#c89b3c'); ctx.fillStyle=g; ctx.beginPath(); ctx.roundRect(PX,BRY,fw,6,3); ctx.fill(); }

    // ---- Tira de campeones ----
    if (SHARE_DATA.campeones?.length) {
        const CY=318, CS=66;
        ctx.textAlign='left'; ctx.font='600 10px Inter,sans-serif'; ctx.fillStyle='rgba(200,155,60,0.5)';
        ctx.fillText('ÚLTIMAS VICTORIAS', 32, CY-10);
        const imgs = await Promise.all(SHARE_DATA.campeones.map(c=>loadImg(c.url)));
        imgs.forEach((img,i)=>{
            const cx=32+i*(CS+10), cy=CY;
            if(img){
                ctx.save(); ctx.beginPath(); ctx.roundRect(cx,cy,CS,CS,6); ctx.clip();
                ctx.drawImage(img,cx,cy,CS,CS); ctx.restore();
                ctx.strokeStyle='rgba(200,155,60,0.45)'; ctx.lineWidth=1.5;
                ctx.beginPath(); ctx.roundRect(cx,cy,CS,CS,6); ctx.stroke();
            }
            const n=(SHARE_DATA.campeones[i]?.nombre||'').substring(0,9);
            ctx.font='10px Inter,sans-serif'; ctx.fillStyle=MUTED; ctx.textAlign='center';
            ctx.fillText(n, cx+CS/2, cy+CS+14);
        });

        // Clase favorita a la derecha de los campeones
        if (SHARE_DATA.claseFav) {
            const clX = 32 + Math.min(5, SHARE_DATA.campeones.length)*(CS+10) + 16;
            ctx.textAlign='left'; ctx.font='600 10px Inter,sans-serif'; ctx.fillStyle='rgba(200,155,60,0.5)';
            ctx.fillText('CLASE FAVORITA', clX, CY-10);
            ctx.font='700 18px Cinzel,serif'; ctx.fillStyle=GOLD;
            ctx.fillText(SHARE_DATA.claseFav, clX, CY+28);
            ctx.font='400 12px Inter,sans-serif'; ctx.fillStyle=MUTED;
            ctx.fillText(SHARE_DATA.claseFavN+' victorias', clX, CY+46);
        }
    }

    // Branding abajo derecha
    ctx.textAlign='right'; ctx.font='700 15px Cinzel,serif'; ctx.fillStyle='rgba(200,155,60,0.55)';
    ctx.fillText('⚔ LEAGUE OF ARENA', W-22, H-16);

    return canvas;
}

// Mini popup de compartir (URL + descarga)
function mostrarSharePopup(blob, url) {
    document.getElementById('share-popup')?.remove();

    const popup = document.createElement('div');
    popup.id = 'share-popup';
    popup.style.cssText = `
        position:fixed;inset:0;z-index:9999;display:flex;align-items:center;justify-content:center;
        background:rgba(0,0,0,.7);backdrop-filter:blur(4px);
    `;
    popup.innerHTML = `
        <div style="background:var(--bg-card);border:1px solid var(--gold);border-radius:12px;
                    padding:1.5rem;max-width:480px;width:calc(100% - 2rem);box-shadow:0 8px 40px rgba(0,0,0,.6)">
            <h3 style="font-family:'Cinzel',serif;color:var(--gold);margin:0 0 1rem">
                <i class="fa-solid fa-share-nodes"></i> Compartir perfil
            </h3>

            <img id="share-preview" style="width:100%;border-radius:8px;margin-bottom:1rem;border:1px solid var(--border)">

            <p style="font-size:.8rem;color:var(--text-muted);margin-bottom:.4rem">Enlace del perfil</p>
            <div style="display:flex;gap:.5rem;margin-bottom:1rem">
                <input id="share-url-input" type="text" value="${url}" readonly
                       style="flex:1;background:var(--bg-elevated);color:var(--text-bright);
                              border:1px solid var(--border);border-radius:6px;padding:.4rem .7rem;
                              font-size:.8rem;overflow:hidden;text-overflow:ellipsis">
                <button id="share-copy-btn" class="btn btn-outline btn-sm" style="white-space:nowrap">
                    <i class="fa-solid fa-copy"></i> Copiar
                </button>
            </div>

            <div style="display:flex;gap:.75rem;justify-content:flex-end">
                <a id="share-download-btn" class="btn btn-primary btn-sm" download="leagueofarena-${SHARE_DATA.nombre}.png">
                    <i class="fa-solid fa-download"></i> Descargar imagen
                </a>
                <button id="share-close-btn" class="btn btn-outline btn-sm">Cerrar</button>
            </div>
        </div>
    `;

    document.body.appendChild(popup);

    // Preview + enlace de descarga
    const objUrl = URL.createObjectURL(blob);
    popup.querySelector('#share-preview').src = objUrl;
    popup.querySelector('#share-download-btn').href = objUrl;

    // Copiar enlace
    popup.querySelector('#share-copy-btn').addEventListener('click', async () => {
        const btn = popup.querySelector('#share-copy-btn');
        try {
            await navigator.clipboard.writeText(url);
            btn.innerHTML = '<i class="fa-solid fa-check"></i> ¡Copiado!';
        } catch {
            popup.querySelector('#share-url-input').select();
            document.execCommand('copy');
            btn.innerHTML = '<i class="fa-solid fa-check"></i> ¡Copiado!';
        }
        setTimeout(() => btn.innerHTML = '<i class="fa-solid fa-copy"></i> Copiar', 2000);
    });

    // Cerrar
    const cerrar = () => { popup.remove(); URL.revokeObjectURL(objUrl); };
    popup.querySelector('#share-close-btn').addEventListener('click', cerrar);
    popup.addEventListener('click', e => { if (e.target === popup) cerrar(); });
}

const btnShare = document.getElementById('btn-share');
if (btnShare) {
    btnShare.addEventListener('click', async () => {
        const orig = btnShare.innerHTML;
        btnShare.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Generando…';
        btnShare.disabled = true;
        try {
            const canvas = await crearTarjetaCompartir();
            const blob   = await new Promise(r => canvas.toBlob(r, 'image/png'));
            const file   = new File([blob], 'leagueofarena.png', { type: 'image/png' });
            const url    = window.location.href;
            const title  = SHARE_DATA.nombre + ' #' + SHARE_DATA.tag + ' — League of Arena';

            if (navigator.canShare && navigator.canShare({ files: [file] })) {
                await navigator.share({ files: [file], title, url });
            } else {
                mostrarSharePopup(blob, url);
            }
        } catch(e) { if (e.name !== 'AbortError') console.error(e); }
        btnShare.innerHTML = orig;
        btnShare.disabled = false;
    });
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>

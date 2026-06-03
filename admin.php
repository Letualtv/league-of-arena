<?php
session_start();
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/RiotAPI.php';
require_once __DIR__ . '/includes/LogrosManager.php';

$db = getDB();

// ===== Identificar al admin =====
// Buscar si hay alguna sesión activa que corresponda al admin
$adminInvocador = null;
foreach ($_SESSION as $key => $val) {
    if (str_starts_with($key, 'auth_') && $val) {
        $puuidSession = substr($key, 5);
        $stmt = $db->prepare('SELECT * FROM invocadores WHERE puuid = ?');
        $stmt->execute([$puuidSession]);
        $inv = $stmt->fetch();
        if ($inv && isAdmin($inv)) {
            $adminInvocador = $inv;
            break;
        }
    }
}

if (!$adminInvocador) {
    http_response_code(403);
    $pageTitle = 'Acceso denegado';
    $navPuuid  = null;
    $navRegion = null;
    include __DIR__ . '/includes/header.php';
    echo '<div class="container"><div class="card" style="text-align:center;padding:3rem">
        <h2><i class="fa-solid fa-lock"></i> Acceso restringido</h2>
        <p class="text-muted">Solo el administrador puede acceder a este panel.</p>
        <a href="<?= BASE_URL ?>" class="btn btn-outline" style="margin-top:1rem">Volver al inicio</a>
    </div></div>';
    include __DIR__ . '/includes/footer.php';
    exit;
}

$version   = RiotAPI::getDDragonVersion();
$campeones = RiotAPI::getChampions();
uasort($campeones, fn($a, $b) => strcmp($a['name'], $b['name']));

$msg = '';
$msgType = 'success';

// ===== Detectar si la BD está migrada =====
$bdMigrada = true;
try {
    $db->query('SELECT titulo, creado_en FROM logros LIMIT 1');
} catch (PDOException $e) {
    $bdMigrada = false;
}

// ===== Procesar: migrar BD + recargar logros =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'migrar') {
    $steps = [];
    $errores = [];

    $alters = [
        "CREATE TABLE IF NOT EXISTS configuracion (clave VARCHAR(50) PRIMARY KEY, valor TEXT NOT NULL)" => 'tabla configuracion',
        "ALTER TABLE logros MODIFY COLUMN tipo VARCHAR(30) NOT NULL"                                                           => 'logros.tipo → VARCHAR(30)',
        "UPDATE logros SET icono = 'fa-solid fa-trophy' WHERE LENGTH(icono) > 40"                                             => 'logros.icono → limpiar emojis viejos',
        "ALTER TABLE logros MODIFY COLUMN icono VARCHAR(100) NOT NULL DEFAULT 'fa-solid fa-trophy'"                            => 'logros.icono → VARCHAR(100)',
        "ALTER TABLE logros ADD COLUMN titulo VARCHAR(100) NULL DEFAULT NULL AFTER descripcion"                                => 'logros.titulo',
        "ALTER TABLE logros ADD COLUMN creado_en DATETIME DEFAULT CURRENT_TIMESTAMP AFTER valor_objetivo"                     => 'logros.creado_en',
        "ALTER TABLE invocadores ADD COLUMN pin_hash VARCHAR(255) NULL DEFAULT NULL"                                           => 'invocadores.pin_hash',
        "ALTER TABLE invocadores ADD COLUMN ranked_solo VARCHAR(50) NULL DEFAULT NULL"                                         => 'invocadores.ranked_solo',
        "ALTER TABLE invocadores ADD COLUMN top_campeon VARCHAR(50) NULL DEFAULT NULL"                                         => 'invocadores.top_campeon',
        "ALTER TABLE invocadores ADD COLUMN titulo_activo VARCHAR(100) NULL DEFAULT NULL"                                      => 'invocadores.titulo_activo',
        "ALTER TABLE campeones_ganados ADD COLUMN campeon_clase VARCHAR(20) NULL DEFAULT NULL AFTER campeon_nombre"            => 'campeones_ganados.campeon_clase',
    ];

    foreach ($alters as $sql => $desc) {
        try {
            $db->exec($sql);
            $steps[] = "OK: $desc";
        } catch (PDOException $e) {
            if (str_contains($e->getMessage(), '1060') || str_contains($e->getMessage(), 'Duplicate column')) {
                $steps[] = "YA EXISTE: $desc";
            } elseif (str_contains($e->getMessage(), '1265') || str_contains($e->getMessage(), 'Data truncated')) {
                $steps[] = "AVISO (datos truncados): $desc";
            } else {
                $errores[] = "ERROR ($desc): " . $e->getMessage();
            }
        }
    }

    $bdMigrada = empty($errores);
    $msg = empty($errores)
        ? 'Migracion completada. Ahora recarga los logros.'
        : 'Migracion con errores: ' . implode(' | ', $errores);
    $msgType = empty($errores) ? 'success' : 'error';
}

// ===== Procesar: recargar logros =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'recargar_logros') {
    if (!$bdMigrada) {
        $msg = 'Primero ejecuta la migracion de BD.';
        $msgType = 'error';
    } else {
        ob_start();
        include __DIR__ . '/fix_logros.php';
        ob_end_clean();
        $msg = 'Logros recargados correctamente desde fix_logros.php.';
    }
}

// ===== Procesar formulario: crear logro =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'crear') {
    $clave   = trim($_POST['clave']   ?? '');
    $nombre  = trim($_POST['nombre']  ?? '');
    $desc    = trim($_POST['desc']    ?? '');
    $titulo  = trim($_POST['titulo']  ?? '') ?: null;
    $icono   = trim($_POST['icono']   ?? 'fa-solid fa-trophy');
    $tipo    = $_POST['tipo'] ?? 'total';
    $objetivo = (int)($_POST['objetivo'] ?? 1);

    // Para tipo campeon, el objetivo es el ID del campeón
    if ($tipo === 'campeon') {
        $objetivo = (int)($_POST['campeon_id'] ?? 0);
    }

    if ($clave && $nombre && $desc && $objetivo > 0) {
        try {
            $db->prepare('INSERT INTO logros (clave, nombre, descripcion, titulo, icono, tipo, valor_objetivo) VALUES (?,?,?,?,?,?,?)')
               ->execute([$clave, $nombre, $desc, $titulo, $icono, $tipo, $objetivo]);
            $msg = "Logro '$nombre' creado correctamente.";
        } catch (PDOException $e) {
            $msg     = 'Error: ' . $e->getMessage();
            $msgType = 'error';
        }
    } else {
        $msg     = 'Faltan campos obligatorios o el objetivo es 0.';
        $msgType = 'error';
    }
}

// ===== Procesar: configuración del feed =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'set_feed_max') {
    $val = (int)($_POST['feed_max'] ?? FEED_MAX_DEFAULT);
    $val = max(1, min(200, $val));
    setConfig($db, 'feed_max', $val);
    $msg = "Feed de actividad limitado a $val eventos.";
}

// ===== Procesar: eliminar logro =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'borrar') {
    $id = (int)($_POST['logro_id'] ?? 0);
    if ($id > 0) {
        $db->prepare('DELETE FROM logros_desbloqueados WHERE logro_id = ?')->execute([$id]);
        $db->prepare('DELETE FROM logros WHERE id = ?')->execute([$id]);
        $msg = 'Logro eliminado.';
    }
}

// ===== Cargar logros existentes =====
$logrosExistentes = $db->query('SELECT * FROM logros ORDER BY tipo, valor_objetivo')->fetchAll();

$pageTitle   = 'Panel Admin — League of Arena';
$navPuuid    = $adminInvocador['puuid'];
$navRegion   = $adminInvocador['region'];
$navInvocador = $adminInvocador;
include __DIR__ . '/includes/header.php';
?>

<div class="container">

    <div class="page-header">
        <div>
            <h1 class="page-title">
                <i class="fa-solid fa-screwdriver-wrench" style="color:var(--gold)"></i>
                Panel de Administrador
            </h1>
            <p class="page-subtitle">Bienvenido, <?= htmlspecialchars($adminInvocador['game_name']) ?>. Con gran poder, etc.</p>
        </div>
    </div>

    <?php if ($msg): ?>
    <div class="alert alert-<?= $msgType === 'error' ? 'error' : 'success' ?>" style="margin-bottom:1.5rem">
        <?= htmlspecialchars($msg) ?>
    </div>
    <?php endif; ?>

    <?php if (!$bdMigrada): ?>
    <div class="card" style="margin-bottom:1.5rem;border-top-color:var(--fire,#FF6B35)">
        <h3 style="color:var(--fire,#FF6B35);margin-bottom:.75rem">
            <i class="fa-solid fa-triangle-exclamation"></i> Base de datos no migrada
        </h3>
        <p class="text-muted" style="margin-bottom:1rem">
            Faltan columnas (<code>titulo</code>, <code>creado_en</code>, etc.). Ejecuta la migración primero y luego recarga los logros.
        </p>
        <div style="display:flex;gap:.75rem;flex-wrap:wrap">
            <form method="POST">
                <input type="hidden" name="action" value="migrar">
                <button type="submit" class="btn btn-primary">
                    <i class="fa-solid fa-database"></i> 1. Migrar BD
                </button>
            </form>
            <form method="POST">
                <input type="hidden" name="action" value="recargar_logros">
                <button type="submit" class="btn btn-outline">
                    <i class="fa-solid fa-rotate"></i> 2. Recargar logros (~95)
                </button>
            </form>
        </div>
    </div>
    <?php else: ?>
    <div style="display:flex;gap:.75rem;flex-wrap:wrap;margin-bottom:1.5rem;justify-content:flex-end">
        <form method="POST" onsubmit="return confirm('Esto borrará TODOS los logros y desbloqueos y los recargará desde fix_logros.php. ¿Continuar?')">
            <input type="hidden" name="action" value="recargar_logros">
            <button type="submit" class="btn btn-sm btn-outline">
                <i class="fa-solid fa-rotate"></i> Recargar logros desde fix_logros.php
            </button>
        </form>
    </div>
    <?php endif; ?>

    <div class="two-col" style="gap:2rem;align-items:start">

        <!-- ===== Formulario crear logro ===== -->
        <div class="card">
            <h2 class="card-title"><i class="fa-solid fa-plus"></i> Crear nuevo logro</h2>
            <form method="POST" style="display:flex;flex-direction:column;gap:1rem">
                <input type="hidden" name="action" value="crear">

                <div class="form-group">
                    <label class="form-label">Tipo de logro</label>
                    <select name="tipo" id="admin-tipo" class="form-input" onchange="adminTipoChange(this.value)">
                        <option value="total">Cantidad total de campeones</option>
                        <?php foreach (LogrosManager::CLASES as $en => $es): ?>
                        <option value="<?= $en ?>"><?= $es ?> (por clase)</option>
                        <?php endforeach; ?>
                        <option value="campeon">Campeón específico</option>
                    </select>
                </div>

                <!-- Campeón específico (solo visible cuando tipo=campeon) -->
                <div class="form-group" id="group-campeon" style="display:none">
                    <label class="form-label">Campeón</label>
                    <select name="campeon_id" id="admin-campeon" class="form-input" onchange="adminAutoFill()">
                        <?php foreach ($campeones as $key => $c): ?>
                        <option value="<?= (int)$c['key'] ?>" data-nombre="<?= htmlspecialchars($c['name']) ?>">
                            <?= htmlspecialchars($c['name']) ?> (<?= (int)$c['key'] ?>)
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Objetivo (oculto para tipo campeon) -->
                <div class="form-group" id="group-objetivo">
                    <label class="form-label">Cantidad objetivo</label>
                    <input type="number" name="objetivo" class="form-input" value="1" min="1" max="9999">
                </div>

                <div class="form-group">
                    <label class="form-label">Clave interna <span class="text-muted">(sin espacios, única)</span></label>
                    <input type="text" name="clave" id="admin-clave" class="form-input" placeholder="ej: camp_75 / campeon_miss_fortune" required>
                </div>

                <div class="form-group">
                    <label class="form-label">Nombre del logro</label>
                    <input type="text" name="nombre" id="admin-nombre" class="form-input" placeholder="ej: La Fortuna Sonrie" required>
                </div>

                <div class="form-group">
                    <label class="form-label">Descripción</label>
                    <input type="text" name="desc" class="form-input" placeholder="ej: Gana con Miss Fortune. Clásica del tirador." required>
                </div>

                <div class="form-group">
                    <label class="form-label">Título que desbloquea <span class="text-muted">(opcional)</span></label>
                    <input type="text" name="titulo" class="form-input" placeholder="ej: La Fortuna de la Arena">
                    <small class="text-muted">Si lo rellenas, el jugador podrá equipar este título en su perfil.</small>
                </div>

                <div class="form-group">
                    <label class="form-label">Icono <span class="text-muted">(clase Font Awesome)</span></label>
                    <div style="display:flex;gap:.5rem;align-items:center">
                        <input type="text" name="icono" id="admin-icono" class="form-input" value="fa-solid fa-trophy"
                               oninput="document.getElementById('admin-icono-preview').className=this.value">
                        <i id="admin-icono-preview" class="fa-solid fa-trophy" style="font-size:1.5rem;color:var(--gold);min-width:2rem"></i>
                    </div>
                    <small class="text-muted">Busca en <a href="https://fontawesome.com/icons" target="_blank" style="color:var(--gold)">fontawesome.com/icons</a> → copiar la clase</small>
                </div>

                <button type="submit" class="btn btn-primary" style="margin-top:.5rem">
                    <i class="fa-solid fa-plus"></i> Crear logro
                </button>
            </form>
        </div>

        <!-- ===== Lista de logros existentes ===== -->
        <div class="card" style="max-height:80vh;overflow-y:auto">
            <h2 class="card-title"><i class="fa-solid fa-list"></i> Logros existentes (<?= count($logrosExistentes) ?>)</h2>
            <div style="display:flex;flex-direction:column;gap:.6rem">
                <?php foreach ($logrosExistentes as $l): ?>
                <div style="display:flex;align-items:center;gap:.75rem;padding:.6rem;background:var(--card-bg-2,rgba(255,255,255,.03));border-radius:6px">
                    <i class="<?= htmlspecialchars($l['icono']) ?>" style="color:var(--gold);width:1.2rem;text-align:center"></i>
                    <div style="flex:1;min-width:0">
                        <div style="font-weight:600;font-size:.85rem"><?= htmlspecialchars($l['nombre']) ?></div>
                        <div style="font-size:.72rem;color:var(--text-muted)"><?= htmlspecialchars($l['tipo']) ?> · obj:<?= $l['valor_objetivo'] ?> · <?= htmlspecialchars($l['clave']) ?></div>
                        <?php if (!empty($l['titulo'] ?? null)): ?>
                        <div style="font-size:.72rem;color:var(--gold)"><i class="fa-solid fa-tag"></i> "<?= htmlspecialchars($l['titulo']) ?>"</div>
                        <?php endif; ?>
                        <?php
                        $diasDesde = isset($l['creado_en'])
                            ? floor((time() - strtotime($l['creado_en'])) / 86400)
                            : 999;
                        if ($diasDesde <= 14): ?>
                        <span class="badge-nuevo" style="font-size:.65rem">NUEVO</span>
                        <?php endif; ?>
                    </div>
                    <form method="POST" onsubmit="return confirm('¿Borrar este logro y todos sus desbloqueos?')">
                        <input type="hidden" name="action" value="borrar">
                        <input type="hidden" name="logro_id" value="<?= $l['id'] ?>">
                        <button type="submit" class="btn btn-sm" style="background:none;color:var(--text-muted);padding:.2rem .4rem" title="Eliminar">
                            <i class="fa-solid fa-trash"></i>
                        </button>
                    </form>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

    </div>

    <!-- ===== Configuración del feed ===== -->
    <div class="card" style="margin-top:2rem">
        <h2 class="card-title">
            <i class="fa-solid fa-bolt" style="color:var(--gold)"></i>
            Feed de actividad reciente
        </h2>
        <p class="text-muted" style="margin-bottom:1.25rem">
            Máximo de eventos que se muestran en el feed del ranking. Rango permitido: 1–200.
            Valor actual: <strong style="color:var(--gold)"><?= (int)getConfig($db, 'feed_max', FEED_MAX_DEFAULT) ?></strong>
        </p>
        <form method="POST" style="display:flex;align-items:center;gap:.75rem;flex-wrap:wrap">
            <input type="hidden" name="action" value="set_feed_max">
            <input type="number" name="feed_max"
                   value="<?= (int)getConfig($db, 'feed_max', FEED_MAX_DEFAULT) ?>"
                   min="1" max="200" step="1"
                   style="width:100px;background:var(--bg-elevated);color:var(--text-bright);
                          border:1px solid var(--border);border-radius:6px;padding:.45rem .7rem;
                          font-size:.95rem;">
            <button type="submit" class="btn btn-primary">
                <i class="fa-solid fa-floppy-disk"></i> Guardar
            </button>
        </form>
    </div>

</div>

<script>
function adminTipoChange(tipo) {
    const isCampeon = tipo === 'campeon';
    document.getElementById('group-campeon').style.display = isCampeon ? '' : 'none';
    document.getElementById('group-objetivo').style.display = isCampeon ? 'none' : '';
    if (isCampeon) adminAutoFill();
}

function adminAutoFill() {
    const sel = document.getElementById('admin-campeon');
    const opt = sel.options[sel.selectedIndex];
    const nombre = opt.dataset.nombre || '';
    const id = sel.value;
    document.getElementById('admin-clave').value = 'campeon_' + nombre.toLowerCase().replace(/[^a-z0-9]/g,'_');
    document.getElementById('admin-nombre').value = 'Jugador de ' + nombre;
    document.getElementById('admin-icono').value  = 'fa-solid fa-star';
    document.getElementById('admin-icono-preview').className = 'fa-solid fa-star';
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>

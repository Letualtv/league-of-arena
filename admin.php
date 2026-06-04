<?php
session_start();
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/RiotAPI.php';
require_once __DIR__ . '/includes/LogrosManager.php';

$db = getDB();

// ===== Identificar al admin =====
$adminInvocador = getAdminFromSession($db);

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
        "ALTER TABLE invocadores ADD COLUMN apodo VARCHAR(50) NULL DEFAULT NULL AFTER tag_line"                                => 'invocadores.apodo',
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

// ===== Procesar: resetear PIN de una cuenta =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'reset_pin') {
    $puuid = trim($_POST['puuid'] ?? '');
    if ($puuid) {
        $db->prepare('UPDATE invocadores SET pin_hash = NULL WHERE puuid = ?')->execute([$puuid]);
        $msg = 'PIN reseteado. El jugador puede volver a reclamar su cuenta.';
    }
}

// ===== Procesar: borrar apodo de una cuenta =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'borrar_apodo') {
    $puuid = trim($_POST['puuid'] ?? '');
    if ($puuid) {
        $db->prepare('UPDATE invocadores SET apodo = NULL WHERE puuid = ?')->execute([$puuid]);
        $msg = 'Apodo eliminado.';
    }
}

// ===== Procesar: borrar cuenta completa =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'borrar_cuenta') {
    $puuid = trim($_POST['puuid'] ?? '');
    if ($puuid) {
        $db->prepare('DELETE FROM logros_desbloqueados WHERE puuid = ?')->execute([$puuid]);
        $db->prepare('DELETE FROM campeones_ganados    WHERE puuid = ?')->execute([$puuid]);
        $db->prepare('DELETE FROM partidas_arena       WHERE puuid = ?')->execute([$puuid]);
        $db->prepare('DELETE FROM invocadores          WHERE puuid = ?')->execute([$puuid]);
        $msg = 'Cuenta eliminada completamente.';
    }
}

// ===== Cargar logros existentes =====
$logrosExistentes = $db->query('SELECT * FROM logros ORDER BY tipo, valor_objetivo')->fetchAll();

// ===== Cargar invocadores registrados =====
$invocadores = $db->query('SELECT * FROM invocadores ORDER BY game_name ASC')->fetchAll();

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

            <!-- Previsualización en vivo -->
            <div id="logro-preview" style="
                display:flex;align-items:center;gap:1rem;
                padding:.85rem 1rem;margin-bottom:1.25rem;
                background:var(--bg-elevated);border:1px solid var(--border);
                border-radius:10px;border-left:3px solid var(--gold)">
                <div style="width:44px;height:44px;border-radius:50%;background:rgba(200,155,60,.15);
                            border:2px solid rgba(200,155,60,.35);display:flex;align-items:center;
                            justify-content:center;flex-shrink:0">
                    <i id="prev-icono" class="fa-solid fa-trophy" style="font-size:1.15rem;color:var(--gold)"></i>
                </div>
                <div style="min-width:0">
                    <div id="prev-nombre" style="font-weight:700;font-size:.95rem;color:var(--text-bright)">Nombre del logro</div>
                    <div id="prev-desc" style="font-size:.78rem;color:var(--text-muted);margin-top:.1rem">Descripción del logro</div>
                    <div id="prev-titulo" style="display:none;font-size:.72rem;color:var(--gold);margin-top:.2rem">
                        <i class="fa-solid fa-tag"></i> <span id="prev-titulo-text"></span>
                    </div>
                </div>
            </div>

            <form method="POST" style="display:flex;flex-direction:column;gap:.85rem">
                <input type="hidden" name="action" value="crear">

                <!-- Tipo + Objetivo en la misma fila -->
                <div style="display:grid;grid-template-columns:1fr auto;gap:.75rem;align-items:end">
                    <div class="form-group" style="margin:0">
                        <label class="form-label">Tipo de logro</label>
                        <select name="tipo" id="admin-tipo" class="form-input" onchange="adminTipoChange(this.value)">
                            <option value="total">Total de campeones ganados</option>
                            <?php foreach (LogrosManager::CLASES as $en => $es): ?>
                            <option value="<?= $en ?>"><?= $es ?> (por clase)</option>
                            <?php endforeach; ?>
                            <option value="campeon">Campeón específico</option>
                        </select>
                    </div>
                    <div class="form-group" id="group-objetivo" style="margin:0;width:90px">
                        <label class="form-label">Objetivo</label>
                        <input type="number" name="objetivo" id="admin-objetivo" class="form-input" value="1" min="1" max="9999">
                    </div>
                </div>

                <!-- Campeón específico (solo visible cuando tipo=campeon) -->
                <div class="form-group" id="group-campeon" style="display:none;margin:0">
                    <label class="form-label">Campeón</label>
                    <select name="campeon_id" id="admin-campeon" class="form-input" onchange="adminAutoFill()">
                        <?php foreach ($campeones as $key => $c): ?>
                        <option value="<?= (int)$c['key'] ?>" data-nombre="<?= htmlspecialchars($c['name']) ?>">
                            <?= htmlspecialchars($c['name']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Nombre (genera la clave automáticamente) -->
                <div class="form-group" style="margin:0">
                    <label class="form-label">Nombre del logro</label>
                    <input type="text" name="nombre" id="admin-nombre" class="form-input"
                           placeholder="ej: La Fortuna Sonríe"
                           oninput="adminSyncNombre(this.value)" required>
                </div>

                <div class="form-group" style="margin:0">
                    <label class="form-label">Descripción</label>
                    <input type="text" name="desc" id="admin-desc" class="form-input"
                           placeholder="ej: Gana con Miss Fortune. Clásica del tirador."
                           oninput="document.getElementById('prev-desc').textContent=this.value||'Descripción del logro'"
                           required>
                </div>

                <!-- Icono con previsualización integrada -->
                <div class="form-group" style="margin:0">
                    <label class="form-label">
                        Icono &nbsp;<a href="https://fontawesome.com/icons" target="_blank"
                           style="font-size:.75rem;color:var(--gold);font-weight:400">
                            fontawesome.com/icons <i class="fa-solid fa-arrow-up-right-from-square" style="font-size:.65rem"></i>
                        </a>
                    </label>
                    <input type="text" name="icono" id="admin-icono" class="form-input"
                           value="fa-solid fa-trophy"
                           placeholder="fa-solid fa-trophy"
                           oninput="adminSyncIcono(this.value)">
                </div>

                <!-- Título (opcional) -->
                <div class="form-group" style="margin:0">
                    <label class="form-label">
                        Título que desbloquea &nbsp;<span class="text-muted" style="font-weight:400;font-size:.78rem">— opcional</span>
                    </label>
                    <input type="text" name="titulo" id="admin-titulo" class="form-input"
                           placeholder="ej: La Fortuna de la Arena"
                           oninput="adminSyncTitulo(this.value)">
                </div>

                <!-- Clave interna (colapsada, auto-generada) -->
                <details style="margin-top:.15rem">
                    <summary style="cursor:pointer;font-size:.78rem;color:var(--text-muted);user-select:none">
                        <i class="fa-solid fa-key" style="font-size:.7rem"></i> Clave interna (auto-generada)
                    </summary>
                    <div style="margin-top:.5rem">
                        <input type="text" name="clave" id="admin-clave" class="form-input"
                               placeholder="ej: camp_75"
                               style="font-size:.82rem;font-family:monospace" required>
                        <small class="text-muted">Se genera sola al escribir el nombre. Cámbiala si es necesario.</small>
                    </div>
                </details>

                <button type="submit" class="btn btn-primary" style="margin-top:.25rem">
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

    <!-- ===== Gestión de cuentas ===== -->
    <div class="card" style="margin-top:2rem">
        <h2 class="card-title">
            <i class="fa-solid fa-users"></i>
            Gestión de cuentas (<?= count($invocadores) ?>)
        </h2>
        <p class="text-muted" style="margin-bottom:1.25rem;font-size:.85rem">
            <strong style="color:var(--fire)">Resetear PIN</strong> — el jugador puede volver a reclamar su cuenta con un PIN nuevo.
            &nbsp;·&nbsp;
            <strong style="color:var(--blood)">Eliminar cuenta</strong> — borra el invocador y todos sus datos (partidas, campeones, logros).
        </p>

        <?php if (empty($invocadores)): ?>
        <p class="text-muted">No hay invocadores registrados aún.</p>
        <?php else: ?>
        <div style="display:flex;flex-direction:column;gap:.5rem">
            <?php foreach ($invocadores as $inv):
                $esSelf   = $inv['puuid'] === $adminInvocador['puuid'];
                $reclamada = !empty($inv['pin_hash']);
            ?>
            <div style="display:flex;align-items:center;gap:.75rem;padding:.65rem .85rem;background:var(--bg-elevated);border-radius:8px;border:1px solid var(--border)">

                <!-- Icono -->
                <img src="https://ddragon.leagueoflegends.com/cdn/<?= $version ?>/img/profileicon/<?= (int)$inv['icono_id'] ?>.png"
                     style="width:36px;height:36px;border-radius:50%;border:2px solid var(--border);flex-shrink:0"
                     onerror="this.style.display='none'">

                <!-- Info -->
                <div style="flex:1;min-width:0">
                    <span style="font-weight:600;font-size:.9rem;color:var(--text-bright)">
                        <?php if (!empty($inv['apodo'])): ?>
                        <?= htmlspecialchars($inv['apodo']) ?>
                        <span style="color:var(--text-muted);font-weight:400;font-size:.8em"> (<?= htmlspecialchars($inv['game_name']) ?>#<?= htmlspecialchars($inv['tag_line']) ?>)</span>
                        <?php else: ?>
                        <?= htmlspecialchars($inv['game_name']) ?><span style="color:var(--text-muted);font-weight:400">#<?= htmlspecialchars($inv['tag_line']) ?></span>
                        <?php endif; ?>
                    </span>
                    <span class="badge badge-region" style="margin-left:.4rem"><?= htmlspecialchars($inv['region']) ?></span>
                    <?php if ($esSelf): ?>
                    <span class="badge" style="margin-left:.3rem;color:var(--gold);border-color:rgba(200,155,60,.4)">TÚ</span>
                    <?php endif; ?>
                    <div style="font-size:.72rem;color:var(--text-muted);margin-top:.15rem">
                        <?php if ($reclamada): ?>
                        <i class="fa-solid fa-lock" style="color:#68e094"></i> Cuenta reclamada
                        <?php else: ?>
                        <i class="fa-solid fa-lock-open" style="color:var(--text-muted)"></i> Sin reclamar
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Acciones -->
                <?php if (!$esSelf): ?>
                <div style="display:flex;gap:.4rem;flex-shrink:0;flex-wrap:wrap">
                    <?php if (!empty($inv['apodo'])): ?>
                    <form method="POST" onsubmit="return confirm('¿Quitar el apodo de <?= htmlspecialchars(addslashes(nombreDisplay($inv))) ?>?')">
                        <input type="hidden" name="action" value="borrar_apodo">
                        <input type="hidden" name="puuid" value="<?= htmlspecialchars($inv['puuid']) ?>">
                        <button type="submit" class="btn btn-sm btn-outline" title="Quitar apodo"
                                style="border-color:var(--text-muted);color:var(--text-muted)">
                            <i class="fa-solid fa-pen-slash"></i> Quitar apodo
                        </button>
                    </form>
                    <?php endif; ?>
                    <?php if ($reclamada): ?>
                    <form method="POST" onsubmit="return confirm('¿Resetear el PIN de <?= htmlspecialchars(addslashes(nombreDisplay($inv))) ?>? Podrá poner uno nuevo.')">
                        <input type="hidden" name="action" value="reset_pin">
                        <input type="hidden" name="puuid" value="<?= htmlspecialchars($inv['puuid']) ?>">
                        <button type="submit" class="btn btn-sm btn-outline" title="Resetear PIN"
                                style="border-color:var(--fire);color:var(--fire)">
                            <i class="fa-solid fa-key"></i> Reset PIN
                        </button>
                    </form>
                    <?php endif; ?>
                    <form method="POST" onsubmit="return confirm('¿ELIMINAR la cuenta de <?= htmlspecialchars(addslashes(nombreDisplay($inv))) ?>? Se borrarán todas sus partidas, campeones y logros. Esta acción NO se puede deshacer.')">
                        <input type="hidden" name="action" value="borrar_cuenta">
                        <input type="hidden" name="puuid" value="<?= htmlspecialchars($inv['puuid']) ?>">
                        <button type="submit" class="btn btn-sm" title="Eliminar cuenta"
                                style="background:rgba(139,0,0,.2);border:1px solid rgba(139,0,0,.5);color:#ff8080">
                            <i class="fa-solid fa-trash"></i> Eliminar
                        </button>
                    </form>
                </div>
                <?php else: ?>
                <span style="font-size:.75rem;color:var(--text-muted);padding:.3rem .6rem">admin</span>
                <?php endif; ?>

            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
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
    const sel    = document.getElementById('admin-campeon');
    const nombre = sel.options[sel.selectedIndex].dataset.nombre || '';
    const slug   = nombre.toLowerCase().replace(/[^a-z0-9]/g, '_');
    document.getElementById('admin-clave').value  = 'campeon_' + slug;
    document.getElementById('admin-nombre').value = 'Jugador de ' + nombre;
    document.getElementById('admin-icono').value  = 'fa-solid fa-star';
    adminSyncNombre('Jugador de ' + nombre);
    adminSyncIcono('fa-solid fa-star');
}

function adminSyncNombre(v) {
    document.getElementById('prev-nombre').textContent = v || 'Nombre del logro';
    // Auto-generar clave desde el nombre
    const slug = v.toLowerCase()
        .normalize('NFD').replace(/[̀-ͯ]/g, '')
        .replace(/[^a-z0-9]+/g, '_').replace(/^_|_$/g, '');
    const obj = document.getElementById('admin-objetivo');
    const sufijo = obj && !obj.closest('#group-objetivo').style.display ? '_' + obj.value : '';
    document.getElementById('admin-clave').value = slug + sufijo;
}

function adminSyncIcono(v) {
    const cls = v.trim() || 'fa-solid fa-trophy';
    document.getElementById('prev-icono').className = cls;
}

function adminSyncTitulo(v) {
    const wrap = document.getElementById('prev-titulo');
    document.getElementById('prev-titulo-text').textContent = v;
    wrap.style.display = v ? '' : 'none';
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>

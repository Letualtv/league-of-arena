<?php
session_start();

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/helpers.php';

$puuid  = $_GET['puuid']  ?? null;
$region = strtoupper($_GET['region'] ?? 'EUW');

if (!$puuid || !isset(REGIONS[$region])) {
    header('Location: ' . BASE_URL);
    exit;
}

$db  = getDB();
$stmt = $db->prepare('SELECT * FROM invocadores WHERE puuid = ?');
$stmt->execute([$puuid]);
$invocador = $stmt->fetch();

if (!$invocador) {
    header('Location: ' . BASE_URL);
    exit;
}

$yaClamado = !empty($invocador['pin_hash']);
$error     = null;
$riotId    = $invocador['game_name'] . '#' . $invocador['tag_line'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pin    = trim($_POST['pin'] ?? '');
    $accion = $_POST['accion'] ?? '';

    if (!preg_match('/^\d{4,8}$/', $pin)) {
        $error = 'El PIN debe tener entre 4 y 8 dígitos.';
    } elseif ($accion === 'reclamar' && !$yaClamado) {
        $confirmar = trim($_POST['pin_confirmar'] ?? '');
        if ($pin !== $confirmar) {
            $error = 'Los PINs no coinciden.';
        } else {
            $db->prepare('UPDATE invocadores SET pin_hash = ? WHERE puuid = ?')
               ->execute([password_hash($pin, PASSWORD_BCRYPT), $puuid]);
            $_SESSION['auth_' . $puuid] = true;
            header('Location: ' . urlPerfil($puuid, $region));
            exit;
        }
    } elseif ($accion === 'login' && $yaClamado) {
        if (password_verify($pin, $invocador['pin_hash'])) {
            $_SESSION['auth_' . $puuid] = true;
            header('Location: ' . urlPerfil($puuid, $region));
            exit;
        } else {
            $error = 'PIN incorrecto.';
        }
    }
}

$pageTitle = ($yaClamado ? 'Iniciar sesión' : 'Reclamar cuenta') . ' — ' . $riotId;
$navPuuid  = $puuid;
$navRegion = $region;

include __DIR__ . '/includes/header.php';
?>

<div class="container">
    <div class="auth-wrap">
        <div class="card auth-card">
            <div class="auth-icon"><?= $yaClamado ? '🔐' : '🏴' ?></div>
            <h1 class="auth-title">
                <?= $yaClamado ? 'Iniciar sesión' : 'Reclamar cuenta' ?>
            </h1>
            <p class="auth-subtitle">
                <span class="text-gold"><?= htmlspecialchars($riotId) ?></span>
            </p>
            <p class="text-muted" style="font-size:.88rem; margin-bottom:1.5rem">
                <?php if ($yaClamado): ?>
                    Esta cuenta ya está reclamada. Introduce tu PIN para poder editar campeones y logros.
                <?php else: ?>
                    Establece un PIN numérico para proteger tu cuenta. Solo tú podrás marcar campeones y gestionar logros.
                <?php endif; ?>
            </p>

            <?php if ($error): ?>
            <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <form method="POST" class="auth-form">
                <input type="hidden" name="accion" value="<?= $yaClamado ? 'login' : 'reclamar' ?>">

                <div class="form-group">
                    <label class="form-label">PIN (4-8 dígitos)</label>
                    <input type="password" name="pin" inputmode="numeric" pattern="\d{4,8}"
                           class="form-input" placeholder="••••" autofocus required>
                </div>

                <?php if (!$yaClamado): ?>
                <div class="form-group">
                    <label class="form-label">Confirmar PIN</label>
                    <input type="password" name="pin_confirmar" inputmode="numeric" pattern="\d{4,8}"
                           class="form-input" placeholder="••••" required>
                </div>
                <?php endif; ?>

                <button type="submit" class="btn btn-primary" style="width:100%">
                    <?= $yaClamado ? 'Entrar' : 'Reclamar cuenta' ?>
                </button>
            </form>

            <a href="<?= urlPerfil($puuid, $region) ?>" class="auth-back">← Volver al perfil</a>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>

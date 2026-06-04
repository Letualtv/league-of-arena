<?php
session_start();
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/RiotAPI.php';
require_once __DIR__ . '/includes/helpers.php';

$error    = null;
$pageTitle = 'League of Arena - Tu registro de victorias en la Arena';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input  = trim($_POST['riot_id'] ?? '');
    $region = strtoupper(trim($_POST['region'] ?? 'EUW'));

    if (!isset(REGIONS[$region])) {
        $error = 'Región no válida.';
    } elseif (!str_contains($input, '#')) {
        $error = 'Introduce tu Riot ID en formato NombreJugador#TAG';
    } else {
        [$gameName, $tagLine] = explode('#', $input, 2);
        $gameName = trim($gameName);
        $tagLine  = trim($tagLine);

        $db          = getDB();
        $apiDisponible = (RIOT_API_KEY !== 'RGAPI-XXXXXXXX-XXXX-XXXX-XXXX-XXXXXXXXXXXX');
        $account       = null;
        $apiFallida    = false;
        $apiHttpCode   = 0;

        if ($apiDisponible) {
            $api     = new RiotAPI($region);
            $account = $api->getAccountByRiotId($gameName, $tagLine);
            if (!$account) {
                $apiFallida  = true;
                $apiHttpCode = $api->lastHttpCode;
            }
        }

        if ($account) {
            $puuid    = $account['puuid'];
            $summoner = $api->getSummonerByPuuid($puuid);
            $inv      = getInvocadorOCrear($puuid, $gameName, $tagLine, $region, $db);

            if ($summoner) {
                $db->prepare('UPDATE invocadores SET summoner_id = ?, icono_id = ?, nivel = ? WHERE puuid = ?')
                   ->execute([$summoner['id'], $summoner['profileIconId'], $summoner['summonerLevel'], $puuid]);
            }

            header('Location: ' . urlPerfil($puuid, $region));
            exit;
        }

        // Fallback: API no disponible o no encontró al jugador → buscar en BD por Riot ID
        $stmt = $db->prepare('SELECT puuid FROM invocadores WHERE LOWER(game_name) = LOWER(?) AND LOWER(tag_line) = LOWER(?) AND region = ?');
        $stmt->execute([$gameName, $tagLine, $region]);
        $puuidLocal = $stmt->fetchColumn();

        if ($puuidLocal) {
            header('Location: ' . urlPerfil($puuidLocal, $region));
            exit;
        }

        // No está en BD: distinguir entre API caída y jugador inexistente
        if (!$apiDisponible) {
            $error = 'La API Key de Riot no está configurada. Solo pueden entrar jugadores ya registrados.';
        } elseif ($apiHttpCode === 401 || $apiHttpCode === 403) {
            $error = 'La API de Riot no está disponible ahora mismo (key caducada). Si ya tenías cuenta, comprueba que escribes tu Riot ID exacto. No se pueden registrar nuevas cuentas hasta que se renueve la key.';
        } else {
            $error = 'No se encontró "' . htmlspecialchars($input) . '" en ' . $region . '.';
        }
    }
}

$navPuuid  = null;
$navRegion = null;
$db = $db ?? getDB();
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
?>

<div class="hero">
    <div class="hero-bg"></div>
    <div class="hero-lines"></div>

    <div class="container">
        <div class="hero-content">

            <div class="hero-eyebrow">
                <i class="fa-solid fa-fire" style="color:var(--fire);margin-right:.4rem"></i>
                El registro definitivo de la Arena
                <i class="fa-solid fa-fire" style="color:var(--fire);margin-left:.4rem"></i>
            </div>

            <h1 class="hero-title">
                League of
                <span class="accent">Arena</span>
            </h1>

            <div class="hero-divider">
                <div class="hero-divider-icon"><i class="fa-solid fa-skull"></i></div>
            </div>

            <p class="hero-subtitle">
                Marca los campeones con los que has conquistado la Arena.<br>
                Compite con tus amigos. Desbloquea logros épicos (y graciosos).
            </p>

            <?php if ($error): ?>
            <div class="alert alert-error" style="text-align:left"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <form method="POST" class="search-form">
                <div class="search-box">
                    <i class="fa-solid fa-magnifying-glass" style="color:var(--text-muted);flex-shrink:0"></i>
                    <input
                        type="text"
                        name="riot_id"
                        placeholder="NombreJugador#EUW"
                        class="search-input"
                        value="<?= htmlspecialchars($_POST['riot_id'] ?? '') ?>"
                        required
                        autofocus
                    >
                    <select name="region" class="region-select">
                        <?php foreach (array_keys(REGIONS) as $r): ?>
                        <option value="<?= $r ?>" <?= ($r === ($_POST['region'] ?? 'EUW')) ? 'selected' : '' ?>><?= $r ?></option>
                        <?php endforeach; ?>
                    </select>
                    <!-- Desktop: botón dentro de la caja -->
                    <button type="submit" class="btn btn-primary search-btn-inline">
                        <i class="fa-solid fa-khanda"></i> Entrar
                    </button>
                </div>
                <!-- Móvil: botón debajo, ancho completo -->
                <button type="submit" class="btn btn-primary search-btn-mobile">
                    <i class="fa-solid fa-khanda"></i> Entrar a la Arena
                </button>
                <p class="search-hint">
                    Tu Riot ID completo: <em>NombreJugador#TAG</em>
                </p>
            </form>

        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>

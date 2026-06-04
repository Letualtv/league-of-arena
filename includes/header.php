<?php
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: text/html; charset=utf-8');
$esOwner = !empty($navPuuid) && !empty($_SESSION['auth_' . $navPuuid]);
$esAdmin = $esOwner && !empty($navInvocador) && isAdmin($navInvocador);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle ?? 'Leagueofarena') ?></title>
    <link rel="icon" type="image/png" href="<?= BASE_URL ?>assets/img/og.png">
    <link rel="apple-touch-icon" href="<?= BASE_URL ?>assets/img/og.png">
    <meta name="description" content="Colecciona tus victorias en Arena de League of Legends. Rastrea tus campeones ganados, logros y compite con tus amigos.">
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="League of Arena">
    <meta property="og:title" content="<?= htmlspecialchars($pageTitle ?? 'League of Arena') ?>">
    <meta property="og:description" content="Colecciona tus victorias en Arena de League of Legends. Rastrea tus campeones ganados, logros y compite con tus amigos.">
    <meta property="og:image" content="https://i.imgur.com/HN2UsIi.png">
    <meta property="og:url" content="https://leagueofarena.gamer.free/">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:image" content="https://i.imgur.com/HN2UsIi.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@400;500;600;700;900&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css">
    <script>const BASE_URL = '<?= BASE_URL ?>';</script>
</head>
<body>

<header class="site-header" id="site-header">

    <!-- ── Barra principal ── -->
    <div class="container flex items-center gap-4">

        <!-- Logo -->
        <a href="<?= BASE_URL ?>" class="logo flex-shrink-0">
            <i class="fa-solid fa-khanda logo-icon"></i>
            <span class="logo-text">League of <strong>Arena</strong></span>
        </a>

        <!-- Nav desktop (oculto en móvil) -->
        <nav class="hidden md:flex items-center gap-1">
            <a href="<?= BASE_URL ?>ranking.php"
               class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'ranking.php' ? 'active' : '' ?>">
                <i class="fa-solid fa-trophy"></i> Ranking
            </a>
        </nav>

        <?php if (!empty($navPuuid) && !empty($navRegion)): ?>
        <nav class="hidden md:flex items-center gap-1">
            <a href="<?= urlPerfil($navPuuid, $navRegion) ?>"
               class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'perfil.php' ? 'active' : '' ?>">
                Perfil
            </a>
            <a href="<?= urlCampeones($navPuuid, $navRegion) ?>"
               class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'campeones.php' ? 'active' : '' ?>">
                Campeones
            </a>
            <a href="<?= urlLogros($navPuuid, $navRegion) ?>"
               class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'logros.php' ? 'active' : '' ?>">
                Logros
            </a>
        </nav>
        <?php endif; ?>

        <!-- Auth desktop (oculto en móvil) -->
        <div class="header-auth hidden md:flex">
            <?php if ($esAdmin): ?>
                <a href="<?= BASE_URL ?>admin.php" class="btn btn-sm btn-gold" title="Admin">
                    <i class="fa-solid fa-screwdriver-wrench"></i>
                </a>
            <?php endif; ?>
            <?php if ($esOwner): ?>
                <span class="auth-badge auth-badge--on">
                    <i class="fa-solid fa-shield-halved"></i> Logeado
                </span>
                <form method="POST" action="<?= BASE_URL ?>ajax/logout.php" style="display:inline">
                    <input type="hidden" name="puuid" value="<?= htmlspecialchars($navPuuid ?? '') ?>">
                    <input type="hidden" name="region" value="<?= htmlspecialchars($navRegion ?? '') ?>">
                    <button type="submit" class="btn btn-sm btn-outline">Salir</button>
                </form>
            <?php elseif (!empty($navPuuid)): ?>
                <a href="<?= BASE_URL ?>reclamar.php?puuid=<?= urlencode($navPuuid) ?>&region=<?= urlencode($navRegion ?? 'EUW') ?>"
                   class="btn btn-sm btn-outline">
                    <i class="fa-solid fa-lock"></i> Acceder
                </a>
            <?php else: ?>
                <a href="<?= BASE_URL ?>" class="btn btn-sm btn-outline">
                    <i class="fa-solid fa-magnifying-glass"></i> Buscar
                </a>
            <?php endif; ?>
        </div>

        <!-- Botón hamburguesa (solo móvil) -->
        <button id="nav-toggle"
                class="md:hidden ml-auto flex items-center justify-center w-10 h-10 rounded-lg border border-white/10 text-gray-400 hover:border-yellow-500/60 hover:text-yellow-500 transition-colors duration-150"
                aria-label="Menú" aria-expanded="false">
            <i id="nav-icon" class="fa-solid fa-bars text-base"></i>
        </button>

    </div>

    <!-- ── Menú móvil desplegable ── -->
    <div id="mobile-menu"
         class="hidden md:hidden border-t border-white/10"
         style="background:rgba(8,10,21,.97);backdrop-filter:blur(16px)">

        <!-- Links de navegación -->
        <nav class="flex flex-col px-3 py-2 gap-1">
            <a href="<?= BASE_URL ?>ranking.php"
               class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'ranking.php' ? 'active' : '' ?> flex items-center gap-2 px-4 py-3 rounded-lg">
                <i class="fa-solid fa-trophy w-4 text-center"></i> Ranking
            </a>
            <?php if (!empty($navPuuid) && !empty($navRegion)): ?>
            <a href="<?= urlPerfil($navPuuid, $navRegion) ?>"
               class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'perfil.php' ? 'active' : '' ?> flex items-center gap-2 px-4 py-3 rounded-lg">
                <i class="fa-solid fa-user w-4 text-center"></i> Perfil
            </a>
            <a href="<?= urlCampeones($navPuuid, $navRegion) ?>"
               class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'campeones.php' ? 'active' : '' ?> flex items-center gap-2 px-4 py-3 rounded-lg">
                <i class="fa-solid fa-shield w-4 text-center"></i> Campeones
            </a>
            <a href="<?= urlLogros($navPuuid, $navRegion) ?>"
               class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'logros.php' ? 'active' : '' ?> flex items-center gap-2 px-4 py-3 rounded-lg">
                <i class="fa-solid fa-medal w-4 text-center"></i> Logros
            </a>
            <?php endif; ?>
        </nav>

        <!-- Auth en el menú móvil -->
        <div class="flex flex-wrap items-center justify-center gap-3 px-4 py-4 border-t border-white/10">
            <?php if ($esAdmin): ?>
                <a href="<?= BASE_URL ?>admin.php" class="btn btn-sm btn-gold">
                    <i class="fa-solid fa-screwdriver-wrench"></i> Admin
                </a>
            <?php endif; ?>
            <?php if ($esOwner): ?>
                <span class="auth-badge auth-badge--on">
                    <i class="fa-solid fa-shield-halved"></i> Logeado
                </span>
                <form method="POST" action="<?= BASE_URL ?>ajax/logout.php">
                    <input type="hidden" name="puuid" value="<?= htmlspecialchars($navPuuid ?? '') ?>">
                    <input type="hidden" name="region" value="<?= htmlspecialchars($navRegion ?? '') ?>">
                    <button type="submit" class="btn btn-sm btn-outline">Salir</button>
                </form>
            <?php elseif (!empty($navPuuid)): ?>
                <a href="<?= BASE_URL ?>reclamar.php?puuid=<?= urlencode($navPuuid) ?>&region=<?= urlencode($navRegion ?? 'EUW') ?>"
                   class="btn btn-sm btn-outline">
                    <i class="fa-solid fa-lock"></i> Acceder
                </a>
            <?php else: ?>
                <a href="<?= BASE_URL ?>" class="btn btn-sm btn-outline">
                    <i class="fa-solid fa-magnifying-glass"></i> Buscar invocador
                </a>
            <?php endif; ?>
        </div>

    </div><!-- /mobile-menu -->

</header>

<main class="main-content">
<script>
(function () {
    var btn  = document.getElementById('nav-toggle');
    var menu = document.getElementById('mobile-menu');
    var icon = document.getElementById('nav-icon');
    if (!btn) return;

    btn.addEventListener('click', function (e) {
        e.stopPropagation();
        var open = menu.classList.toggle('hidden');
        icon.className = open ? 'fa-solid fa-bars text-base' : 'fa-solid fa-xmark text-base';
        btn.setAttribute('aria-expanded', String(!open));
    });

    document.addEventListener('click', function (e) {
        if (!document.getElementById('site-header').contains(e.target)) {
            menu.classList.add('hidden');
            icon.className = 'fa-solid fa-bars text-base';
            btn.setAttribute('aria-expanded', 'false');
        }
    });
})();
</script>

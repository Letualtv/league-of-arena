<?php
// Descarga Font Awesome 6 localmente para no depender de CDN
$baseUrl  = 'https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.5.1';
$cssDir   = __DIR__ . '/assets/fa/css/';
$webfontDir = __DIR__ . '/assets/fa/webfonts/';

@mkdir($cssDir, 0777, true);
@mkdir($webfontDir, 0777, true);

// Descargar CSS
$cssUrl = $baseUrl . '/css/all.min.css';
$css    = file_get_contents($cssUrl);
if (!$css) { die('ERROR: No se pudo descargar el CSS de Font Awesome. Comprueba la conexión.'); }

// Ajustar rutas de webfonts en el CSS para que apunten a la carpeta local
require_once __DIR__ . '/config/config.php';
$css = str_replace('../webfonts/', BASE_URL . 'assets/fa/webfonts/', $css);
file_put_contents($cssDir . 'all.min.css', $css);
echo "CSS descargado.<br>";

// Descargar webfonts necesarios
$fonts = [
    'fa-solid-900.woff2', 'fa-solid-900.ttf',
    'fa-brands-400.woff2', 'fa-brands-400.ttf',
    'fa-regular-400.woff2', 'fa-regular-400.ttf',
];

foreach ($fonts as $font) {
    $url  = $baseUrl . '/webfonts/' . $font;
    $data = file_get_contents($url);
    if ($data) {
        file_put_contents($webfontDir . $font, $data);
        echo "Descargado: $font<br>";
    } else {
        echo "ERROR: $font<br>";
    }
}

echo '<br><strong>Listo.</strong> Ahora edita includes/header.php y cambia la línea del CDN por:<br>';
echo '<code>&lt;link rel="stylesheet" href="' . BASE_URL . 'assets/fa/css/all.min.css"&gt;</code>';
echo '<br><br><a href="' . BASE_URL . '">Volver al inicio</a>';

<?php
session_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/LogrosManager.php';

header('Content-Type: application/json');

$puuid  = $_POST['puuid']  ?? '';
$titulo = $_POST['titulo'] ?? '';   // cadena vacía = quitar título

if (!$puuid || empty($_SESSION['auth_' . $puuid])) {
    echo json_encode(['ok' => false, 'error' => 'No autorizado']);
    exit;
}

$db = getDB();

// Verificar que el título proviene de un logro desbloqueado por este jugador
if ($titulo !== '') {
    $stmt = $db->prepare('
        SELECT COUNT(*) FROM logros l
        JOIN logros_desbloqueados ld ON l.id = ld.logro_id
        WHERE ld.puuid = ? AND l.titulo = ?
    ');
    $stmt->execute([$puuid, $titulo]);
    if (!$stmt->fetchColumn()) {
        echo json_encode(['ok' => false, 'error' => 'Título no desbloqueado']);
        exit;
    }
}

$db->prepare('UPDATE invocadores SET titulo_activo = ? WHERE puuid = ?')
   ->execute([$titulo ?: null, $puuid]);

echo json_encode(['ok' => true, 'titulo' => $titulo ?: null]);

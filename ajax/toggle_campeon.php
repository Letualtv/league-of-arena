<?php
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/LogrosManager.php';

$puuid        = $_POST['puuid']         ?? null;
$region       = strtoupper($_POST['region'] ?? 'EUW');
$championId   = (int)($_POST['champion_id']   ?? 0);
$championName = trim($_POST['champion_name']  ?? '');
$championClase = trim($_POST['champion_clase'] ?? '');

if (!$puuid || !$championId || !$championName || !isset(REGIONS[$region])) {
    echo json_encode(['ok' => false, 'error' => 'Parametros invalidos']);
    exit;
}

if (empty($_SESSION['auth_' . $puuid])) {
    echo json_encode(['ok' => false, 'error' => 'No tienes permiso. Inicia sesion primero.']);
    exit;
}

$db = getDB();

$stmt = $db->prepare('SELECT id FROM campeones_ganados WHERE puuid = ? AND campeon_id = ?');
$stmt->execute([$puuid, $championId]);
$existe = $stmt->fetchColumn();

if ($existe) {
    $db->prepare('DELETE FROM campeones_ganados WHERE puuid = ? AND campeon_id = ?')
       ->execute([$puuid, $championId]);
    $lm = new LogrosManager($db);
    $lm->revocarLogrosInvalidos($puuid);
    echo json_encode(['ok' => true, 'ganado' => false]);
} else {
    $db->prepare('
        INSERT INTO campeones_ganados (puuid, campeon_id, campeon_nombre, campeon_clase, veces_ganado, marcado_manual, primera_victoria, ultima_victoria)
        VALUES (?, ?, ?, ?, 1, 1, NOW(), NOW())
    ')->execute([$puuid, $championId, $championName, $championClase]);

    $lm           = new LogrosManager($db);
    $nuevosLogros = $lm->verificarYDesbloquear($puuid);

    echo json_encode([
        'ok'            => true,
        'ganado'        => true,
        'nuevos_logros' => array_map(fn($l) => $l['nombre'], $nuevosLogros),
    ]);
}

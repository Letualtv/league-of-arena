<?php
session_start();
require_once __DIR__ . '/../config/config.php';

$puuid  = $_POST['puuid']  ?? null;
$region = strtoupper($_POST['region'] ?? 'EUW');

if ($puuid) {
    unset($_SESSION['auth_' . $puuid]);
}

header('Location: ' . BASE_URL . 'perfil.php?puuid=' . urlencode($puuid) . '&region=' . urlencode($region));
exit;

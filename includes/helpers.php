<?php
function posicionLabel(int $pos): string
{
    return match ($pos) {
        1 => '1.º',
        2 => '2.º',
        3 => '3.º',
        4 => '4.º',
        5 => '5.º',
        6 => '6.º',
        7 => '7.º',
        8 => '8.º',
        default => $pos . '.º',
    };
}

function posicionClass(int $pos): string
{
    return match (true) {
        $pos === 1 => 'pos-oro',
        $pos === 2 => 'pos-plata',
        $pos <= 4  => 'pos-bronce',
        default    => 'pos-gris',
    };
}

function tiempoRelativo(string $timestamp): string
{
    $diff = time() - strtotime($timestamp);
    return match (true) {
        $diff < 60     => 'ahora',
        $diff < 3600   => round($diff / 60) . ' min',
        $diff < 86400  => round($diff / 3600) . 'h',
        $diff < 604800 => round($diff / 86400) . 'd',
        default        => date('d/m/Y', strtotime($timestamp)),
    };
}

function formatDuracion(int $segundos): string
{
    $m = intdiv($segundos, 60);
    $s = $segundos % 60;
    return sprintf('%d:%02d', $m, $s);
}

function championImgUrl(string $championName, string $version): string
{
    return DDRAGON_BASE . "/cdn/{$version}/img/champion/{$championName}.png";
}

function profileIconUrl(int $iconId, string $version): string
{
    return DDRAGON_BASE . "/cdn/{$version}/img/profileicon/{$iconId}.png";
}

function urlPerfil(string $puuid, string $region): string
{
    return 'perfil.php?puuid=' . urlencode($puuid) . '&region=' . urlencode($region);
}

function urlCampeones(string $puuid, string $region): string
{
    return 'campeones.php?puuid=' . urlencode($puuid) . '&region=' . urlencode($region);
}

function urlLogros(string $puuid, string $region): string
{
    return 'logros.php?puuid=' . urlencode($puuid) . '&region=' . urlencode($region);
}

function nombreDisplay(array $inv): string
{
    return !empty($inv['apodo']) ? $inv['apodo'] : $inv['game_name'];
}

function isAdmin(?array $invocador): bool
{
    if (!$invocador) return false;
    return strtolower($invocador['game_name']) === strtolower(ADMIN_GAME_NAME)
        && strtolower($invocador['tag_line'])  === strtolower(ADMIN_TAG_LINE);
}

function getConfig(PDO $db, string $clave, $default = null)
{
    try {
        $stmt = $db->prepare('SELECT valor FROM configuracion WHERE clave = ?');
        $stmt->execute([$clave]);
        $row = $stmt->fetchColumn();
        return $row !== false ? $row : $default;
    } catch (PDOException $e) {
        return $default;
    }
}

function setConfig(PDO $db, string $clave, $valor): void
{
    $db->exec('CREATE TABLE IF NOT EXISTS configuracion (clave VARCHAR(50) PRIMARY KEY, valor TEXT NOT NULL)');
    $db->prepare('INSERT INTO configuracion (clave, valor) VALUES (?,?) ON DUPLICATE KEY UPDATE valor = VALUES(valor)')
       ->execute([$clave, (string)$valor]);
}

function getInvocadorOCrear(string $puuid, string $gameName, string $tagLine, string $region, PDO $db): array
{
    $stmt = $db->prepare('SELECT * FROM invocadores WHERE puuid = ?');
    $stmt->execute([$puuid]);
    $inv = $stmt->fetch();
    if (!$inv) {
        $db->prepare('INSERT INTO invocadores (puuid, game_name, tag_line, region) VALUES (?, ?, ?, ?)')
           ->execute([$puuid, $gameName, $tagLine, $region]);
        $inv = ['puuid' => $puuid, 'game_name' => $gameName, 'tag_line' => $tagLine, 'region' => $region, 'icono_id' => 1, 'nivel' => 1];
    }
    return $inv;
}

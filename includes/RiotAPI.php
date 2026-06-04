<?php
require_once __DIR__ . '/../config/config.php';

class RiotAPI
{
    private string $apiKey;
    private string $platform;
    private string $regional;

    public function __construct(string $region = 'EUW')
    {
        $this->apiKey  = RIOT_API_KEY;
        $cfg           = REGIONS[$region] ?? REGIONS['EUW'];
        $this->platform  = $cfg['platform'];
        $this->regional  = $cfg['regional'];
    }

    public int $lastHttpCode = 0;

    private function get(string $url): array|null
    {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => ["X-Riot-Token: {$this->apiKey}"],
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        $body     = curl_exec($ch);
        $this->lastHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($this->lastHttpCode !== 200 || $body === false) {
            return null;
        }
        return json_decode($body, true);
    }

    // Riot Account API — obtiene PUUID por Riot ID (nombre#tag)
    public function getAccountByRiotId(string $gameName, string $tagLine): array|null
    {
        $url = sprintf(
            'https://%s.api.riotgames.com/riot/account/v1/accounts/by-riot-id/%s/%s',
            $this->regional,
            rawurlencode($gameName),
            rawurlencode($tagLine)
        );
        return $this->get($url);
    }

    // Datos del invocador (nivel, icono) por PUUID
    public function getSummonerByPuuid(string $puuid): array|null
    {
        $url = sprintf(
            'https://%s.api.riotgames.com/lol/summoner/v4/summoners/by-puuid/%s',
            $this->platform,
            rawurlencode($puuid)
        );
        return $this->get($url);
    }

    // IDs de partidas de Arena del jugador
    public function getArenaMatchIds(string $puuid, int $start = 0, int $count = 20): array
    {
        $url = sprintf(
            'https://%s.api.riotgames.com/lol/match/v5/matches/by-puuid/%s/ids?queue=%d&start=%d&count=%d',
            $this->regional,
            rawurlencode($puuid),
            QUEUE_ARENA,
            $start,
            $count
        );
        return $this->get($url) ?? [];
    }

    // Detalle completo de una partida
    public function getMatch(string $matchId): array|null
    {
        $url = sprintf(
            'https://%s.api.riotgames.com/lol/match/v5/matches/%s',
            $this->regional,
            $matchId
        );
        return $this->get($url);
    }

    // Rango en ranked por summoner ID (legacy)
    public function getRankedBySummonerId(string $summonerId, string $region): array|null
    {
        $url = sprintf(
            'https://%s.api.riotgames.com/lol/league/v4/entries/by-summoner/%s',
            $this->platform,
            rawurlencode($summonerId)
        );
        return $this->get($url);
    }

    // Rango en ranked por PUUID (nuevo endpoint)
    public function getRankedByPuuid(string $puuid): array|null
    {
        $url = sprintf(
            'https://%s.api.riotgames.com/lol/league/v4/entries/by-puuid/%s',
            $this->platform,
            rawurlencode($puuid)
        );
        return $this->get($url);
    }

    // Top 1 de maestría de campeón por PUUID
    public function getTopMastery(string $puuid, string $region): array|null
    {
        $url = sprintf(
            'https://%s.api.riotgames.com/lol/champion-mastery/v4/champion-masteries/by-puuid/%s/top?count=1',
            $this->platform,
            rawurlencode($puuid)
        );
        return $this->get($url);
    }

    // Versión actual de Data Dragon (con caché de 1 hora)
    private static function ensureCacheDir(): string
    {
        $dir = __DIR__ . '/../cache';
        if (!is_dir($dir)) @mkdir($dir, 0755, true);
        return $dir;
    }

    public static function getDDragonVersion(): string
    {
        $cacheFile = self::ensureCacheDir() . '/ddragon_version.txt';
        if (file_exists($cacheFile) && (time() - filemtime($cacheFile) < 3600)) {
            return trim(file_get_contents($cacheFile));
        }
        $versions = @json_decode(@file_get_contents(DDRAGON_BASE . '/api/versions.json'), true);
        $version  = $versions[0] ?? (file_exists($cacheFile) ? trim(file_get_contents($cacheFile)) : '14.24.1');
        @file_put_contents($cacheFile, $version);
        return $version;
    }

    // Todos los campeones en español (con caché de 24 horas)
    public static function getChampions(): array
    {
        $version   = self::getDDragonVersion();
        $cacheFile = self::ensureCacheDir() . '/champions.json';
        if (file_exists($cacheFile) && (time() - filemtime($cacheFile) < 86400)) {
            return json_decode(file_get_contents($cacheFile), true) ?: [];
        }
        $url  = DDRAGON_BASE . "/cdn/{$version}/data/es_ES/champion.json";
        $raw  = @file_get_contents($url);
        $data = $raw ? @json_decode($raw, true) : null;
        if (!empty($data['data'])) {
            file_put_contents($cacheFile, json_encode($data['data']));
            return $data['data'];
        }
        // Si el fetch falla, usar caché aunque esté caducada
        if (file_exists($cacheFile)) {
            return json_decode(file_get_contents($cacheFile), true) ?: [];
        }
        return [];
    }
}

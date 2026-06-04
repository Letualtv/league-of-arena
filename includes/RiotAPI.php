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

    // IDs de partidas de Arena del jugador (recorre todos los queues definidos en QUEUE_ARENAS)
    public function getArenaMatchIds(string $puuid, int $start = 0, int $count = 20): array
    {
        $ids = [];
        foreach (QUEUE_ARENAS as $queue) {
            $url = sprintf(
                'https://%s.api.riotgames.com/lol/match/v5/matches/by-puuid/%s/ids?queue=%d&start=%d&count=%d',
                $this->regional,
                rawurlencode($puuid),
                $queue,
                $start,
                $count
            );
            $batch = $this->get($url) ?? [];
            $ids = array_merge($ids, $batch);
            usleep(60000); // 60ms entre llamadas (Development Key: 20 req/s)
        }
        return array_values(array_unique($ids));
    }

    // IDs de TODAS las partidas de Arena del jugador (paginando)
    public function getAllArenaMatchIds(string $puuid): array
    {
        $ids       = [];
        $batchSize = 100; // máximo permitido por Riot match-v5/ids
        foreach (QUEUE_ARENAS as $queue) {
            $start = 0;
            while (true) {
                $url = sprintf(
                    'https://%s.api.riotgames.com/lol/match/v5/matches/by-puuid/%s/ids?queue=%d&start=%d&count=%d',
                    $this->regional,
                    rawurlencode($puuid),
                    $queue,
                    $start,
                    $batchSize
                );
                $batch = $this->get($url) ?? [];
                if (empty($batch)) break;
                $ids = array_merge($ids, $batch);
                if (count($batch) < $batchSize) break; // última página
                $start += $batchSize;
                usleep(60000);
            }
        }
        return array_values(array_unique($ids));
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

    // Limpia las descripciones crudas que vienen de Riot:
    //  - Elimina tags propietarios (<scaleHealth>, <keyword>, <spellName>, <rules>, etc.)
    //    pero deja el contenido visible.
    //  - Mantiene <br>, <b>, <i> y <em> para mantener el formato.
    //  - Sustituye variables @VarName@ por «—» (Riot las resuelve a runtime con el augment concreto).
    private static function limpiarDescripcionRiot(string $desc): string
    {
        if ($desc === '') return '';
        // Eliminar emoji icons tipo "%i:goldCoins%"
        $desc = preg_replace('/%i:[^%]+%/', '', $desc);
        // Sustituir @VarName@ y @VarName*100@ por «—»
        $desc = preg_replace('/@[A-Za-z][\w*\.\-]*@/', '<em>—</em>', $desc);
        // Quitar tags propietarios pero mantener el texto interno
        $desc = strip_tags($desc, '<br><b><i><em><strong>');
        // Compactar espacios y múltiples <br>
        $desc = preg_replace('#(\s*<br\s*/?>\s*){3,}#i', '<br><br>', $desc);
        return trim($desc);
    }

    // Mapa de items de DDragon por ID (con caché de 24 horas)
    // Devuelve: [itemId => ['name' => str, 'plaintext' => str, 'desc' => str_html]]
    public static function getItems(): array
    {
        $version   = self::getDDragonVersion();
        $cacheFile = self::ensureCacheDir() . '/items.json';
        if (file_exists($cacheFile) && (time() - filemtime($cacheFile) < 86400)) {
            return json_decode(file_get_contents($cacheFile), true) ?: [];
        }
        $url  = DDRAGON_BASE . "/cdn/{$version}/data/es_ES/item.json";
        $raw  = @file_get_contents($url);
        $data = $raw ? @json_decode($raw, true) : null;
        if (!empty($data['data'])) {
            $map = [];
            foreach ($data['data'] as $id => $item) {
                $map[(int)$id] = [
                    'name'      => $item['name']        ?? '',
                    'plaintext' => $item['plaintext']   ?? '',
                    'desc'      => self::limpiarDescripcionRiot($item['description'] ?? ''),
                ];
            }
            // Añadir ítems específicos de Arena que no están en DDragon estándar (prismáticos)
            $arenaUrl = 'https://raw.communitydragon.org/latest/cdragon/arena/es_es.json';
            $arenaRaw = @file_get_contents($arenaUrl);
            $arena    = $arenaRaw ? @json_decode($arenaRaw, true) : null;
            if (!empty($arena['items'])) {
                foreach ($arena['items'] as $it) {
                    $id = (int)($it['id'] ?? 0);
                    if (!$id || isset($map[$id])) continue;
                    $map[$id] = [
                        'name'      => $it['name']        ?? "Item $id",
                        'plaintext' => '',
                        'desc'      => self::limpiarDescripcionRiot($it['description'] ?? $it['desc'] ?? ''),
                    ];
                }
            }
            file_put_contents($cacheFile, json_encode($map, JSON_UNESCAPED_UNICODE));
            return $map;
        }
        if (file_exists($cacheFile)) {
            return json_decode(file_get_contents($cacheFile), true) ?: [];
        }
        return [];
    }

    // Mapa de augments de Arena por ID (con caché de 24 horas)
    // Devuelve: [augmentId => ['name' => str, 'icon' => url_absoluta]]
    public static function getArenaAugments(): array
    {
        $cacheFile = self::ensureCacheDir() . '/arena_augments.json';
        if (file_exists($cacheFile) && (time() - filemtime($cacheFile) < 86400)) {
            return json_decode(file_get_contents($cacheFile), true) ?: [];
        }
        $url  = 'https://raw.communitydragon.org/latest/cdragon/arena/es_es.json';
        $raw  = @file_get_contents($url);
        $data = $raw ? @json_decode($raw, true) : null;
        if (!empty($data['augments'])) {
            // CDragon sirve los iconos de Arena bajo /game/, NO bajo /plugins/rcp-be-lol-game-data/...
            $base = 'https://raw.communitydragon.org/latest/game';
            $map  = [];
            foreach ($data['augments'] as $aug) {
                $id   = (int)($aug['id'] ?? 0);
                $path = $aug['iconLarge'] ?? $aug['iconSmall'] ?? '';
                if (!$id || !$path) continue;
                // Traducción del path interno de Riot a URL de CommunityDragon (siempre lowercase y con / inicial)
                $path = strtolower($path);
                $path = preg_replace('#^/?lol-game-data/assets/#', '', $path);
                $path = '/' . ltrim($path, '/');
                $iconPrimary = $base . $path;
                // Fallback: muchos iconos vienen con sufijo de temporada (".arena_2026_s2", ".arena_2026_s2_a2")
                // que CDragon no siempre mirroriza. Generamos también la versión "base" sin sufijo.
                $iconFallback = preg_replace('#\.arena_\d+_s\d+(_a\d+)?(?=\.png$)#', '', $iconPrimary);
                if ($iconFallback === $iconPrimary) $iconFallback = '';
                $map[$id] = [
                    'name'     => $aug['name'] ?? '',
                    'desc'     => self::limpiarDescripcionRiot($aug['desc'] ?? ''),
                    'icon'     => $iconPrimary,
                    'icon_alt' => $iconFallback,
                ];
            }
            file_put_contents($cacheFile, json_encode($map, JSON_UNESCAPED_UNICODE));
            return $map;
        }
        if (file_exists($cacheFile)) {
            return json_decode(file_get_contents($cacheFile), true) ?: [];
        }
        return [];
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

<?php
class LogrosManager
{
    private PDO $db;

    public const CLASES = [
        'Fighter'  => 'Luchador',
        'Tank'     => 'Tanque',
        'Mage'     => 'Mago',
        'Assassin' => 'Asesino',
        'Support'  => 'Soporte',
        'Marksman' => 'Tirador',
    ];

    // ===== Grupos por lore/raza/región =====

    private const YORDLES = [17, 117, 78, 18, 45, 85, 42, 115, 68, 150, 74, 240, 711, 350];
    // Teemo, Lulu, Poppy, Tristana, Veigar, Kennen, Corki, Ziggs, Rumble, Gnar, Heimerdinger, Kled, Vex, Yuumi

    private const VOID = [31, 96, 121, 145, 161, 200, 421];
    // Cho'Gath, Kog'Maw, Kha'Zix, Kai'Sa, Vel'Koz, Bel'Veth, Rek'Sai

    private const NOXUS = [122, 119, 55, 91, 92, 50, 69, 8, 14, 240, 360, 526];
    // Darius, Draven, Katarina, Talon, Riven, Swain, Cassiopeia, Vladimir, Sion, Kled, Samira, Rell

    private const IONIA = [103, 84, 39, 202, 43, 85, 64, 876, 11, 497, 98, 110, 62, 498, 157, 777, 238];
    // Ahri, Akali, Irelia, Jhin, Karma, Kennen, Lee Sin, Lillia, Master Yi, Rakan, Shen, Varus, Wukong, Xayah, Yasuo, Yone, Zed

    private const SHADOW_ISLES = [30, 120, 429, 57, 82, 235, 412, 234, 83, 887, 60];
    // Karthus, Hecarim, Kalista, Maokai, Mordekaiser, Senna, Thresh, Viego, Yorick, Gwen, Elise

    private const DARKIN = [266, 110, 950, 141];
    // Aatrox, Varus, Naafiri, Kayn

    private const ARCANE = [222, 254, 51, 245, 126, 112, 164, 19];
    // Jinx, Vi, Caitlyn, Ekko, Jayce, Viktor, Camille, Warwick

    private const BESTIAS = [19, 107, 76, 77, 106, 150, 34, 102, 31, 121, 96, 33, 223, 120, 421];
    // Warwick, Rengar, Nidalee, Udyr, Volibear, Gnar, Anivia, Shyvana, Cho'Gath, Kha'Zix, Kog'Maw, Rammus, Tahm Kench, Hecarim, Rek'Sai

    private const FEMENINAS = [
        103, 84, 34, 1, 22, 51, 164, 69, 131, 28, 60, 114, 887, 420, 39, 40,
        222, 145, 429, 43, 10, 7, 89, 876, 127, 99, 21, 25, 267, 518, 76, 895,
        61, 78, 133, 421, 92, 360, 113, 147, 102, 15, 37, 16, 235, 134, 163,
        18, 67, 711, 254, 498, 221, 142, 350,
    ];

    private const DEMACIA = [86, 99, 59, 114, 3, 78, 102, 67, 133, 5, 236, 37, 517];
    // Garen, Lux, Jarvan IV, Fiora, Galio, Poppy, Shyvana, Vayne, Quinn, Xin Zhao, Lucian, Sona, Sylas

    private const FRELJORD = [22, 113, 23, 201, 127, 106, 48, 77, 34, 20, 150];
    // Ashe, Sejuani, Tryndamere, Braum, Lissandra, Volibear, Trundle, Udyr, Anivia, Nunu, Gnar

    private const SHURIMA = [268, 75, 58, 15, 163, 32, 101, 33, 69];
    // Azir, Nasus, Renekton, Sivir, Taliyah, Amumu, Xerath, Rammus, Cassiopeia

    private const BILGEWATER = [21, 41, 104, 4, 111, 555, 420, 105, 223];
    // Miss Fortune, Gangplank, Graves, Twisted Fate, Nautilus, Pyke, Illaoi, Fizz, Tahm Kench

    private const TARGON = [89, 131, 16, 44, 142, 80, 136, 523];
    // Leona, Diana, Soraka, Taric, Zoe, Pantheon, Aurelion Sol, Aphelios

    private const PILTOVER_ZAUN = [222, 254, 51, 245, 126, 112, 164, 19, 154, 61, 74, 115, 29, 27, 6, 53];
    // Jinx, Vi, Caitlyn, Ekko, Jayce, Viktor, Camille, Warwick, Zac, Orianna, Heimerdinger, Ziggs, Twitch, Singed, Urgot, Blitzcrank

    private const ASCENDIDOS = [75, 58, 268, 101];
    // Nasus, Renekton, Azir, Xerath (los 4 Ascendidos de Shurima)

    private const KINKOU = [238, 98, 84, 85];
    // Zed, Shen, Akali, Kennen (Kinkou y Orden de las Sombras)

    private const CAZADORES = [107, 121, 76, 19, 104, 133, 203];
    // Rengar, Kha'Zix, Nidalee, Warwick, Graves, Quinn, Kindred

    private const TILTING = [17, 350, 35, 142, 27];
    // Teemo, Yuumi, Shaco, Zoe, Singed — los más frustrantes de combatir

    // Parejas / hermanos / rivales (clave => [id1, id2])
    private const PAREJAS = [
        // Hermanos de sangre
        'par_yasuo_yone'      => [157, 777],   // Yasuo + Yone
        'par_darius_draven'   => [122, 119],   // Darius + Draven
        'par_garen_lux'       => [86, 99],     // Garen + Lux
        'par_nasus_renekton'  => [75, 58],     // Nasus + Renekton
        'par_kayle_morgana'   => [10, 25],     // Kayle + Morgana
        'par_kata_cass'       => [55, 69],     // Katarina + Cassiopeia
        // Parejas románticas
        'par_xayah_rakan'     => [498, 497],   // Xayah + Rakan
        'par_jinx_vi'         => [222, 254],   // Jinx + Vi (hermanas Arcane)
        'par_lucian_senna'    => [236, 235],   // Lucian + Senna
        'par_cait_vi'         => [51, 254],    // Caitlyn + Vi (Arcane)
        'par_ashe_tryndamere' => [22, 23],     // Ashe + Tryndamere (matrimonio del Freljord)
        // Rivales / enemigos eternos
        'par_rengar_khazix'   => [107, 121],   // Rengar + Kha'Zix
        'par_leona_diana'     => [89, 131],    // Leona + Diana (sol y luna)
        'par_azir_xerath'     => [268, 101],   // Azir + Xerath (traición)
        'par_zed_shen'        => [238, 98],    // Zed + Shen
        'par_thresh_lucian'   => [412, 236],   // Thresh + Lucian
        'par_viktor_jayce'    => [112, 126],   // Viktor + Jayce
        'par_kaisa_khazix'    => [145, 121],   // Kai'Sa + Kha'Zix
        // Alianzas / compañeros
        'par_ekko_jinx'       => [245, 222],   // Ekko + Jinx (Arcane infancia)
        'par_amumu_nunu'      => [32, 20],     // Amumu + Nunu
        'par_swain_leblanc'   => [50, 7],      // Swain + LeBlanc (Concilio de Noxus)
    ];

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function getEstadisticas(string $puuid): array
    {
        $stats = [];

        $stmt = $this->db->prepare('SELECT COUNT(*) FROM campeones_ganados WHERE puuid = ?');
        $stmt->execute([$puuid]);
        $stats['total'] = (int)$stmt->fetchColumn();

        $stmt = $this->db->prepare('
            SELECT campeon_clase, COUNT(*) as n
            FROM campeones_ganados WHERE puuid = ? AND campeon_clase IS NOT NULL
            GROUP BY campeon_clase
        ');
        $stmt->execute([$puuid]);
        $porClase = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

        foreach (array_keys(self::CLASES) as $clase) {
            $stats['clase_' . $clase] = (int)($porClase[$clase] ?? 0);
        }

        $stats['clases_distintas'] = count(array_filter($porClase, fn($n) => $n > 0));
        $stats['max_por_clase']    = empty($porClase) ? 0 : max($porClase);

        arsort($porClase);
        $stats['clase_favorita']   = array_key_first($porClase) ?? null;
        $stats['clase_favorita_n'] = reset($porClase) ?: 0;

        $stmt = $this->db->prepare('SELECT campeon_id FROM campeones_ganados WHERE puuid = ?');
        $stmt->execute([$puuid]);
        $stats['ids_ganados'] = array_map('intval', array_column($stmt->fetchAll(), 'campeon_id'));

        $stats['yordles'] = count(array_intersect($stats['ids_ganados'], self::YORDLES));

        return $stats;
    }

    public function verificarYDesbloquear(string $puuid): array
    {
        $stats  = $this->getEstadisticas($puuid);
        $inv    = $this->getInvocador($puuid);
        $logros = $this->db->query('SELECT * FROM logros')->fetchAll();
        $nuevos = [];

        foreach ($logros as $logro) {
            if ($this->yaDesbloqueado($puuid, $logro['id'])) continue;
            if ($this->evaluar($logro, $stats, $inv)) {
                $this->desbloquear($puuid, $logro['id']);
                $nuevos[] = $logro;
            }
        }
        return $nuevos;
    }

    public function revocarLogrosInvalidos(string $puuid): void
    {
        $stats = $this->getEstadisticas($puuid);
        $inv   = $this->getInvocador($puuid);

        $stmt = $this->db->prepare('
            SELECT l.*, ld.id AS ld_id FROM logros l
            JOIN logros_desbloqueados ld ON l.id = ld.logro_id AND ld.puuid = ?
        ');
        $stmt->execute([$puuid]);

        foreach ($stmt->fetchAll() as $logro) {
            if (!$this->evaluar($logro, $stats, $inv)) {
                $this->db->prepare('DELETE FROM logros_desbloqueados WHERE id = ?')
                         ->execute([$logro['ld_id']]);
            }
        }

        // Si el título activo ya no tiene logro desbloqueado, quitarlo
        $inv = $this->getInvocador($puuid);
        if ($inv && !empty($inv['titulo_activo'])) {
            $stmt = $this->db->prepare('
                SELECT COUNT(*) FROM logros l
                JOIN logros_desbloqueados ld ON l.id = ld.logro_id
                WHERE ld.puuid = ? AND l.titulo = ?
            ');
            $stmt->execute([$puuid, $inv['titulo_activo']]);
            if (!$stmt->fetchColumn()) {
                $this->db->prepare('UPDATE invocadores SET titulo_activo = NULL WHERE puuid = ?')
                         ->execute([$puuid]);
            }
        }
    }

    public function getTodosConProgreso(string $puuid): array
    {
        $stats = $this->getEstadisticas($puuid);
        $inv   = $this->getInvocador($puuid);

        $stmt = $this->db->prepare('
            SELECT l.*,
                   ld.desbloqueado_en,
                   CASE WHEN ld.id IS NOT NULL THEN 1 ELSE 0 END AS desbloqueado
            FROM logros l
            LEFT JOIN logros_desbloqueados ld ON l.id = ld.logro_id AND ld.puuid = ?
            ORDER BY
                CASE WHEN ld.id IS NOT NULL THEN 0 ELSE 1 END,
                l.tipo, l.valor_objetivo
        ');
        $stmt->execute([$puuid]);
        $logros = $stmt->fetchAll();

        return $logros;
    }

    private function evaluar(array $logro, array $stats, ?array $inv): bool
    {
        return $this->valorActual($logro, $stats, $inv) >= $logro['valor_objetivo'];
    }

    private function valorActual(array $logro, array $stats, ?array $inv): int
    {
        $tipo  = $logro['tipo'];
        $clave = $logro['clave'];
        $ids   = $stats['ids_ganados'];

        if ($tipo === 'total') return $stats['total'];
        if (isset(self::CLASES[$tipo])) return $stats['clase_' . $tipo] ?? 0;

        if ($tipo === 'campeon') {
            return in_array((int)$logro['valor_objetivo'], $ids) ? 1 : 0;
        }

        if ($tipo === 'especial') {
            if (isset(self::PAREJAS[$clave])) {
                [$id1, $id2] = self::PAREJAS[$clave];
                return (in_array($id1, $ids) && in_array($id2, $ids)) ? 1 : 0;
            }

            return match ($clave) {
                'todoterreno'  => $stats['clases_distintas'] >= 6 ? 1 : 0,
                'especialista' => $stats['max_por_clase'] >= 10 ? 1 : 0,
                'lagartos'     => $stats['total'] >= 1 ? 1 : 0,
                'sin_main'     => $this->evaluarSinMain($stats, $inv),

                'yordles'      => $this->contarGrupo($ids, self::YORDLES),
                'void_3'       => $this->contarGrupo($ids, self::VOID),
                'noxus_5'      => $this->contarGrupo($ids, self::NOXUS),
                'ionia_5'      => $this->contarGrupo($ids, self::IONIA),
                'shadow_4'     => $this->contarGrupo($ids, self::SHADOW_ISLES),
                'darkin'       => $this->contarGrupo($ids, self::DARKIN),
                'arcane_4'     => $this->contarGrupo($ids, self::ARCANE),
                'bestias_5'    => $this->contarGrupo($ids, self::BESTIAS),
                'femeninas_5'  => $this->contarGrupo($ids, self::FEMENINAS),
                'demacia_5'    => $this->contarGrupo($ids, self::DEMACIA),
                'freljord_5'   => $this->contarGrupo($ids, self::FRELJORD),
                'shurima_5'    => $this->contarGrupo($ids, self::SHURIMA),
                'bilgewater_4' => $this->contarGrupo($ids, self::BILGEWATER),
                'targon_4'     => $this->contarGrupo($ids, self::TARGON),
                'piltover_5'   => $this->contarGrupo($ids, self::PILTOVER_ZAUN),
                'ascendidos'   => $this->contarGrupo($ids, self::ASCENDIDOS),
                'kinkou_4'     => $this->contarGrupo($ids, self::KINKOU),
                'cazadores_4'  => $this->contarGrupo($ids, self::CAZADORES),
                'tilt_3'       => $this->contarGrupo($ids, self::TILTING),

                default => 0,
            };
        }

        return 0;
    }

    private function contarGrupo(array $ids_ganados, array $grupo): int
    {
        return count(array_intersect($ids_ganados, $grupo));
    }

    private function evaluarSinMain(array $stats, ?array $inv): int
    {
        if (!$inv || !$inv['top_campeon'] || $stats['total'] < 5) return 0;
        $stmt = $this->db->prepare('SELECT 1 FROM campeones_ganados WHERE puuid = ? AND campeon_nombre = ?');
        $stmt->execute([$inv['puuid'], $inv['top_campeon']]);
        return $stmt->fetchColumn() ? 0 : 1;
    }

    private function getInvocador(string $puuid): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM invocadores WHERE puuid = ?');
        $stmt->execute([$puuid]);
        return $stmt->fetch() ?: null;
    }

    private function yaDesbloqueado(string $puuid, int $logroId): bool
    {
        $stmt = $this->db->prepare('SELECT 1 FROM logros_desbloqueados WHERE puuid = ? AND logro_id = ?');
        $stmt->execute([$puuid, $logroId]);
        return (bool)$stmt->fetchColumn();
    }

    private function desbloquear(string $puuid, int $logroId): void
    {
        $this->db->prepare('INSERT IGNORE INTO logros_desbloqueados (puuid, logro_id) VALUES (?, ?)')
                 ->execute([$puuid, $logroId]);
    }

    public function getTitulosDesbloqueados(string $puuid): array
    {
        $stmt = $this->db->prepare('
            SELECT l.titulo FROM logros l
            JOIN logros_desbloqueados ld ON l.id = ld.logro_id
            WHERE ld.puuid = ? AND l.titulo IS NOT NULL
            ORDER BY ld.desbloqueado_en ASC
        ');
        $stmt->execute([$puuid]);
        return array_column($stmt->fetchAll(), 'titulo');
    }
}

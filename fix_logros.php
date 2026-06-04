<?php
session_start();
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/helpers.php';

$db = getDB();
requireAdmin($db);
// Upsert por clave: actualiza los logros oficiales sin tocar los custom del admin.
// Set curado: hitos basados en partidas + lo más divertido del set antiguo.
// Solo un ~30% de logros tienen título (los más memorables).

// Columnas: clave, nombre, descripcion, titulo, icono, tipo, valor_objetivo
// titulo = null → no desbloquea título
// tipo 'campeon' → valor_objetivo = ID del campeón en Data Dragon

$logros = [

    // =========================================================
    // CAMPEONES DISTINTOS GANADOS (#1)
    // =========================================================
    ['camp_1',   'Primer Paso',        'Marca tu primer campeón ganado en Arena',         null,                    'fa-solid fa-flag',          'total',   1],
    ['camp_5',   'Explorador',         'Gana con 5 campeones distintos',                  null,                    'fa-solid fa-map',           'total',   5],
    ['camp_15',  'Coleccionista',      'Gana con 15 campeones distintos',                 'Coleccionista',         'fa-solid fa-gem',           'total',  15],
    ['camp_30',  'Habitual',           'Gana con 30 campeones distintos',                 null,                    'fa-solid fa-chess-pawn',    'total',  30],
    ['camp_60',  'Leyenda',            'Gana con 60 campeones distintos',                 'Leyenda de la Arena',   'fa-solid fa-crown',         'total',  60],
    ['camp_100', 'Maestro del Roster', 'Gana con 100 campeones distintos',                'Maestro del Roster',    'fa-solid fa-chess-king',    'total', 100],

    // =========================================================
    // PARTIDAS JUGADAS (volumen)
    // =========================================================
    ['pj_10',   'Calentando Motores', 'Juega 10 partidas de Arena',                          null,                  'fa-solid fa-gamepad',           'partidas_jugadas',  10],
    ['pj_50',   'Habitual del Modo',  'Juega 50 partidas de Arena',                          null,                  'fa-solid fa-dice',              'partidas_jugadas',  50],
    ['pj_100',  'Centenar de Arena',  'Juega 100 partidas',                                   'Centurión',          'fa-solid fa-shield-halved',     'partidas_jugadas', 100],
    ['pj_250',  'Adicción Confirmada','Juega 250 partidas. La vida real puede esperar',      null,                  'fa-solid fa-skull-crossbones',  'partidas_jugadas', 250],
    ['pj_500',  'No Touch Grass',     'Juega 500 partidas. ¿Has visto el sol últimamente?',  'No Touch Grass',      'fa-solid fa-cannabis',          'partidas_jugadas', 500],
    ['pj_1000', 'Eternidad en Arena', '1.000 partidas. Eres parte del modo',                 'El Eterno',           'fa-solid fa-infinity',          'partidas_jugadas', 1000],

    // =========================================================
    // VICTORIAS (#1) ACUMULADAS
    // =========================================================
    ['vt_1',   'Primer Trono',       'Tu primera victoria (1º) en Arena',                    null,                  'fa-solid fa-crown',             'victorias_total',    1],
    ['vt_10',  'Coronado',           '10 victorias',                                          null,                  'fa-solid fa-trophy',            'victorias_total',   10],
    ['vt_25',  'Rey de la Arena',    '25 victorias',                                          'Rey de la Arena',     'fa-solid fa-chess-king',        'victorias_total',   25],
    ['vt_50',  'Verdugo',            '50 victorias',                                          null,                  'fa-solid fa-skull',             'victorias_total',   50],
    ['vt_100', 'Emperador',          '100 victorias',                                         'Emperador',           'fa-solid fa-chess',             'victorias_total',  100],
    ['vt_250', 'Dinastía',           '250 victorias. La Arena tiene un nuevo señor',          'Dinastía',            'fa-solid fa-monument',          'victorias_total',  250],

    // =========================================================
    // TOP 2 Y TOP 4
    // =========================================================
    ['t2_25',  'Finalista Crónico',  '25 veces en el top 2',                                  null,                  'fa-solid fa-award',             'top2_total',   25],
    ['t2_100', 'Eternamente Segundo','Top 2 cien veces. Mejor que el bronce, peor que oro',   'Eterno Segundo',      'fa-solid fa-2',                 'top2_total',  100],

    ['t4_25',  'En el Podio',        'Llega 25 veces al top 4',                               null,                  'fa-solid fa-ranking-star',      'top4_total',   25],
    ['t4_100', 'Consistente',        '100 top 4',                                              null,                  'fa-solid fa-chart-line',        'top4_total',  100],
    ['t4_250', 'Élite del Top 4',    '250 top 4. Las derrotas son anécdota',                  'Élite',               'fa-solid fa-gem',               'top4_total',  250],

    // =========================================================
    // KILLS ACUMULADAS
    // =========================================================
    ['kt_500',  'Sed de Sangre',     '500 kills entre todas tus partidas',                    null,                  'fa-solid fa-droplet',           'kills_total',   500],
    ['kt_2000', 'Carnicero',         '2.000 kills',                                            'Carnicero',           'fa-solid fa-skull',             'kills_total',  2000],
    ['kt_5000', 'Aniquilador',       '5.000 kills',                                            'Aniquilador',         'fa-solid fa-meteor',            'kills_total',  5000],

    // =========================================================
    // RÉCORDS EN UNA SOLA PARTIDA
    // =========================================================
    ['rp_dano_30k',  'Tormenta de Daño',  '30.000 de daño a campeones en una partida',        null,                  'fa-solid fa-bolt-lightning',    'dano_max_partida',   30000],
    ['rp_dano_50k',  'Demolición',         '50.000 de daño en una partida',                    null,                  'fa-solid fa-explosion',         'dano_max_partida',   50000],
    ['rp_dano_100k', 'Apocalipsis',        '100.000 de daño en una partida. Récord brutal',    'Apocalipsis',         'fa-solid fa-radiation',         'dano_max_partida',  100000],

    ['rp_kills_10',  'Diez en Punto',     '10 kills en una sola partida',                      null,                  'fa-solid fa-bullseye',          'kills_max_partida',     10],
    ['rp_kills_20',  'Veinte en Punto',    '20 kills en una partida. Estabas inspirado',       'Cazador',             'fa-solid fa-crosshairs',        'kills_max_partida',     20],
    ['rp_kda_5',     'KDA Limpio',         'Consigue un KDA de 5 o más en una partida',        null,                  'fa-solid fa-stethoscope',       'kda_max_partida',        5],
    ['rp_kda_10',    'Perfeccionista',     'KDA de 10 o más. Casi divino',                     'Perfeccionista',      'fa-solid fa-star-of-life',      'kda_max_partida',       10],

    // =========================================================
    // RACHAS
    // =========================================================
    ['racha_3',  'Triple Corona',     'Gana 3 partidas seguidas',                              null,                  'fa-solid fa-fire',              'racha_victorias',    3],
    ['racha_5',  'En Llamas',         'Gana 5 partidas seguidas',                              'En Llamas',           'fa-solid fa-fire-flame-simple', 'racha_victorias',    5],
    ['racha_10', 'Endiosado',         '10 victorias seguidas. La rampa final',                 'Endiosado',           'fa-solid fa-fire-flame-curved', 'racha_victorias',   10],
    ['rt4_10',   'Constante',         '10 top 4 seguidos sin caída',                           null,                  'fa-solid fa-chart-line',        'racha_top4',        10],
    ['rt4_25',   'Inmovible',         '25 top 4 seguidos. Las derrotas no existen',            'Inmovible',           'fa-solid fa-mountain',          'racha_top4',        25],

    // =========================================================
    // SESIONES Y VARIEDAD
    // =========================================================
    ['dia_5',     'Sesión Tarde',      '5 partidas en un mismo día',                            null,                  'fa-solid fa-calendar-day',      'partidas_mismo_dia',  5],
    ['dia_10',    'Maratón',           '10 partidas en un mismo día',                           'Maratoniano',         'fa-solid fa-person-running',    'partidas_mismo_dia', 10],
    ['dia_20',    'Sin Salir de Casa', '20 partidas en un día. Récord WTF',                     'Sin Salir de Casa',   'fa-solid fa-house-lock',        'partidas_mismo_dia', 20],

    ['var_25',    'Probador',          'Juega con 25 campeones distintos (no hace falta ganar)', null,                'fa-solid fa-flask',             'variedad_campeones', 25],
    ['var_60',    'Rotador',           'Juega con 60 campeones distintos',                      null,                  'fa-solid fa-arrows-rotate',     'variedad_campeones', 60],
    ['var_100',   'Universalista',     'Juega con 100 campeones distintos',                     'Universalista',       'fa-solid fa-globe',             'variedad_campeones', 100],

    ['horas_10',  'Diez Horas',        'Acumula 10 horas en Arena',                              null,                  'fa-solid fa-clock',             'horas_jugadas',      10],
    ['horas_50',  'Fin de Semana',     '50 horas. Eso es un fin de semana entero',              null,                  'fa-solid fa-hourglass-half',    'horas_jugadas',      50],
    ['horas_100', 'Cien Horas',        '100 horas. Te quedaste pegado al modo',                  'Cien Horas',          'fa-solid fa-hourglass',         'horas_jugadas',     100],

    // =========================================================
    // CLASES (solo 2 hitos por clase para no saturar)
    // =========================================================
    ['fighter_5',   'Puños de Hierro',     'Gana con 5 Luchadores',     null,                'fa-solid fa-hand-fist',     'Fighter',   5],
    ['fighter_20',  'Rey del Combate',     'Gana con 20 Luchadores',    'El Bruto',          'fa-solid fa-khanda',        'Fighter',  20],

    ['tank_5',      'Muro Viviente',       'Gana con 5 Tanques',        null,                'fa-solid fa-shield',        'Tank',      5],
    ['tank_20',     'Inamovible',          'Gana con 20 Tanques',       'El Muro',           'fa-solid fa-shield-heart',  'Tank',     20],

    ['mage_5',      'Aprendiz de Magia',   'Gana con 5 Magos',          null,                'fa-solid fa-hat-wizard',    'Mage',      5],
    ['mage_20',     'Señor del Caos',      'Gana con 20 Magos',         'El Archimago',      'fa-solid fa-bolt-lightning','Mage',     20],

    ['assassin_5',  'En las Sombras',      'Gana con 5 Asesinos',       null,                'fa-solid fa-user-ninja',    'Assassin',  5],
    ['assassin_20', 'Muerte Silenciosa',   'Gana con 20 Asesinos',      'Muerte Silenciosa', 'fa-solid fa-ghost',         'Assassin', 20],

    ['support_5',   'Mano Amiga',          'Gana con 5 Soportes',       null,                'fa-solid fa-heart',         'Support',   5],
    ['support_20',  'La Mamá del Equipo',  'Gana con 20 Soportes',      'La Mamá',           'fa-solid fa-people-group',  'Support',  20],

    ['marksman_5',  'Francotirador',       'Gana con 5 Tiradores',      null,                'fa-solid fa-crosshairs',    'Marksman',  5],
    ['marksman_20', 'Precisión Letal',     'Gana con 20 Tiradores',     'Precisión Letal',   'fa-solid fa-person-rifle',  'Marksman', 20],

    // =========================================================
    // ESPECIALES SERIOS
    // =========================================================
    ['todoterreno',  'Todoterreno',  'Gana con al menos 1 campeón de cada clase',    'Todoterreno',     'fa-solid fa-infinity', 'especial', 1],
    ['especialista', 'Especialista', 'Gana con 10 campeones de la misma clase',      null,              'fa-solid fa-award',    'especial', 1],
    ['lagartos',     'Lagarto del Sol', 'Bienvenido a los Lagartos del Sol',         'Lagarto del Sol', 'fa-solid fa-dragon',   'especial', 1],

    // =========================================================
    // GRUPOS DE LORE (los más icónicos)
    // =========================================================
    ['yordles',     'Los Amiguitos',           'Gana con 5 Yordles distintos. Los más adorables y peligrosos',        null,                       'fa-solid fa-children',     'especial', 5],
    ['void_3',      'Del Vacío Somos',         'Gana con 3 criaturas del Vacío',                                       'Engendro del Vacío',       'fa-solid fa-eye',          'especial', 3],
    ['darkin',      'Los Cuatro Jinetes',      'Gana con los 4 Darkin: Aatrox, Varus, Naafiri y Kayn',                 'Jinete del Apocalipsis',   'fa-solid fa-droplet',      'especial', 4],
    ['ascendidos',  'Los Ascendidos',          'Gana con los 4 Ascendidos: Nasus, Renekton, Azir y Xerath',            'El Ascendido',             'fa-solid fa-ankh',         'especial', 4],
    ['kinkou_4',    'Orden de las Sombras',    'Gana con Zed, Shen, Akali y Kennen',                                   null,                       'fa-solid fa-eye-low-vision','especial', 4],
    ['tilt_3',      'El Equipo Tóxico',        'Gana con 3 de: Teemo, Yuumi, Shaco, Zoe, Singed',                      'El Tóxico',                'fa-solid fa-biohazard',    'especial', 3],

    // =========================================================
    // PAREJAS / RIVALES ICÓNICOS (solo los más fuertes de lore)
    // =========================================================
    ['par_yasuo_yone',     'Hermanos de Sangre',  'Gana con Yasuo Y Yone',                                                'Hermano de Sangre',     'fa-solid fa-wind',             'especial', 1],
    ['par_garen_lux',      'Familia Crownguard',  'Gana con Garen Y Lux. Uno grita DEMACIA, la otra usa magia ilegal',    null,                    'fa-solid fa-sun',              'especial', 1],
    ['par_jinx_vi',        'Hermanas de Arcane',  'Gana con Jinx Y Vi',                                                   null,                    'fa-solid fa-people-arrows',    'especial', 1],
    ['par_rengar_khazix',  'La Caza Eterna',      'Gana con Rengar Y Kha\'Zix. La rivalidad más icónica',                 'El Rival Eterno',       'fa-solid fa-skull-crossbones', 'especial', 1],
    ['par_leona_diana',    'Sol y Luna',          'Gana con Leona Y Diana',                                               null,                    'fa-solid fa-circle-half-stroke','especial', 1],
    ['par_lucian_senna',   'Hasta la Muerte',     'Gana con Lucian Y Senna',                                              'Unidos por el Alma',    'fa-solid fa-infinity',         'especial', 1],
    ['par_zed_shen',       'El Kinkou Roto',      'Gana con Zed Y Shen',                                                  null,                    'fa-solid fa-yin-yang',         'especial', 1],

    // =========================================================
    // CAMPEONES INDIVIDUALES (los 5 más memorables)
    // =========================================================
    ['teemo',      'El Champiñón de Satanás', 'Gana con Teemo. Eres esa persona',                       'El Champiñón',  'fa-solid fa-biohazard',     'campeon',  17],
    ['yuumi',      'La Garrapata',            'Gana con Yuumi. Tu dúo te lo hizo todo',                 'La Garrapata',  'fa-solid fa-cat',           'campeon', 350],
    ['shaco',      'El Payaso del Terror',    'Gana con Shaco. Nadie se alegra cuando ganas con él',    null,            'fa-solid fa-masks-theater', 'campeon',  35],
    ['rammus',     'Ok',                       'Gana con Rammus. Ok',                                    'Ok.',           'fa-solid fa-thumbs-up',     'campeon',  33],
    ['tryndamere', 'Sin Morir No Vale',       'Gana con Tryndamere. El R no cuenta como skill',         null,            'fa-solid fa-rotate-left',   'campeon',  23],

    // =========================================================
    // META: LOGROS POR DESBLOQUEAR LOGROS (gamificación)
    // =========================================================
    ['veterano',    'Veterano de la Arena', 'Desbloquea 10 logros distintos',  'Veterano',     'fa-solid fa-medal',     'total', 10],
    ['completista', 'Completista',          'Desbloquea 25 logros',             null,           'fa-solid fa-list-check','total', 25],
    ['obsesionado', 'Obsesionado',          'Desbloquea 50 logros',             'Obsesionado',  'fa-solid fa-brain',     'total', 50],

];

$stmt = $db->prepare("INSERT INTO logros (clave, nombre, descripcion, titulo, icono, tipo, valor_objetivo) VALUES (?,?,?,?,?,?,?)
    ON DUPLICATE KEY UPDATE
        nombre         = VALUES(nombre),
        descripcion    = VALUES(descripcion),
        titulo         = VALUES(titulo),
        icono          = VALUES(icono),
        tipo           = VALUES(tipo),
        valor_objetivo = VALUES(valor_objetivo)");
foreach ($logros as $l) {
    $stmt->execute($l);
}

// Eliminar las definiciones obsoletas (logros que estaban en el set viejo y ya no en el nuevo)
$obsoletas = [
    'camp_10','camp_25','camp_50','camp_75','camp_150',
    'fighter_3','fighter_10','tank_3','tank_10','mage_3','mage_10',
    'assassin_3','assassin_10','support_3','support_10','marksman_3','marksman_10',
    'sin_main',
    'demacia_5','freljord_5','shurima_5','bilgewater_4','targon_4','piltover_5','noxus_5','ionia_5',
    'shadow_4','arcane_4','bestias_5','femeninas_5','cazadores_4',
    'par_darius_draven','par_nasus_renekton','par_kayle_morgana','par_kata_cass',
    'par_xayah_rakan','par_cait_vi','par_ashe_tryndamere',
    'par_azir_xerath','par_thresh_lucian','par_viktor_jayce','par_kaisa_khazix',
    'par_ekko_jinx','par_amumu_nunu','par_swain_leblanc',
    'singed','nasus','ryze','zilean','heimerdinger','kalista','seraphine','akali',
    'yasuo','yone','zed','pyke','blitzcrank','jax','malphite','soraka','amumu','kled',
    'illaoi','zac','sett','urgot','fizz','leblanc','gangplank','bard','veigar',
    'morgana','aurelion','aphelios','miss_fortune','kindred','jhin',
    'arena_baptismo','arena_veterano','arena_completista','arena_obsesionado','arena_dios',
];
if (!empty($obsoletas)) {
    $ph = implode(',', array_fill(0, count($obsoletas), '?'));
    $db->prepare("DELETE FROM logros_desbloqueados WHERE logro_id IN (SELECT id FROM logros WHERE clave IN ($ph))")->execute($obsoletas);
    $db->prepare("DELETE FROM logros WHERE clave IN ($ph)")->execute($obsoletas);
}

echo "<h2>Set actualizado</h2>";
echo "<p>Logros activos: <strong>" . count($logros) . "</strong></p>";
echo "<p>Logros antiguos eliminados: <strong>" . count($obsoletas) . "</strong></p>";

$cats = [
    'Campeones distintos' => 0, 'Partidas jugadas' => 0, 'Victorias' => 0,
    'Top 2/4' => 0, 'Kills' => 0, 'Récords' => 0, 'Rachas' => 0,
    'Sesiones/Variedad' => 0, 'Clases' => 0, 'Especiales' => 0, 'Grupos lore' => 0,
    'Parejas' => 0, 'Campeones' => 0, 'Meta' => 0,
];
foreach ($logros as $l) {
    [$clave, , , , , $tipo] = $l;
    if (str_starts_with($clave, 'camp_'))                  $cats['Campeones distintos']++;
    elseif (str_starts_with($clave, 'pj_'))                $cats['Partidas jugadas']++;
    elseif (str_starts_with($clave, 'vt_'))                $cats['Victorias']++;
    elseif (str_starts_with($clave, 't2_') || str_starts_with($clave, 't4_')) $cats['Top 2/4']++;
    elseif (str_starts_with($clave, 'kt_'))                $cats['Kills']++;
    elseif (str_starts_with($clave, 'rp_'))                $cats['Récords']++;
    elseif (str_starts_with($clave, 'racha_') || str_starts_with($clave, 'rt4_')) $cats['Rachas']++;
    elseif (str_starts_with($clave, 'dia_') || str_starts_with($clave, 'var_') || str_starts_with($clave, 'horas_')) $cats['Sesiones/Variedad']++;
    elseif (in_array($tipo, ['Fighter','Tank','Mage','Assassin','Support','Marksman'])) $cats['Clases']++;
    elseif (in_array($clave, ['todoterreno','especialista','lagartos']))                $cats['Especiales']++;
    elseif (str_starts_with($clave, 'par_'))               $cats['Parejas']++;
    elseif ($tipo === 'campeon')                            $cats['Campeones']++;
    elseif (in_array($clave, ['veterano','completista','obsesionado'])) $cats['Meta']++;
    else                                                    $cats['Grupos lore']++;
}
echo "<ul style='font-family:monospace;font-size:.85rem'>";
foreach ($cats as $k => $v) echo "<li>$k: <strong>$v</strong></li>";
echo "</ul>";
echo "<p><a href='" . BASE_URL . "logros.php?puuid=" . urlencode($_SESSION['admin_puuid'] ?? '') . "'>← Volver a logros</a></p>";

<?php
session_start();
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/helpers.php';

$db = getDB();
requireAdmin($db);
$db->exec("SET FOREIGN_KEY_CHECKS = 0");
$db->exec("TRUNCATE TABLE logros_desbloqueados");
$db->exec("TRUNCATE TABLE logros");
$db->exec("SET FOREIGN_KEY_CHECKS = 1");

// Columnas: clave, nombre, descripcion, titulo, icono, tipo, valor_objetivo
// titulo = null → el logro no desbloquea ningún título
// tipo campeon → valor_objetivo = ID del campeón en Data Dragon

$logros = [

    // =========================================================
    // CANTIDAD TOTAL DE CAMPEONES
    // =========================================================
    ['camp_1',   'Primer Paso',        'Marca tu primer campeón ganado en Arena',             'Novato de la Arena',    'fa-solid fa-flag',          'total',   1],
    ['camp_5',   'Explorador',         'Gana con 5 campeones distintos',                      'El Explorador',         'fa-solid fa-map',           'total',   5],
    ['camp_10',  'Coleccionista',      'Gana con 10 campeones distintos',                     'El Coleccionista',      'fa-solid fa-gem',           'total',  10],
    ['camp_25',  'Gran Coleccionista', 'Gana con 25 campeones distintos',                     'Gran Coleccionista',    'fa-solid fa-star',          'total',  25],
    ['camp_30',  'Habitual',           'Gana con 30 campeones distintos',                     'El Habitual',           'fa-solid fa-chess-pawn',    'total',  30],
    ['camp_50',  'Leyenda',            'Gana con 50 campeones distintos',                     'Leyenda de la Arena',   'fa-solid fa-crown',         'total',  50],
    ['camp_75',  'Tres Cuartos',       'Gana con 75 campeones distintos. Ya falta menos',     'Deidad de la Arena',    'fa-solid fa-hourglass-half','total',  75],
    ['camp_100', 'Maestro del Roster', 'Gana con 100 campeones distintos',                    'Maestro del Roster',    'fa-solid fa-chess-king',    'total', 100],
    ['camp_150', 'El Que No Para',     'Gana con 150 campeones. Considera dormir',            'El Que No Para',        'fa-solid fa-infinity',      'total', 150],

    // =========================================================
    // POR CLASE
    // =========================================================
    ['fighter_3',  'Puños de Hierro',         'Gana con 3 Luchadores',   'Guerrero',           'fa-solid fa-hand-fist',           'Fighter',   3],
    ['fighter_10', 'Maestro Luchador',         'Gana con 10 Luchadores',  'Maestro Luchador',   'fa-solid fa-dumbbell',            'Fighter',  10],
    ['fighter_20', 'Rey del Cuerpo a Cuerpo',  'Gana con 20 Luchadores',  'El Bruto',           'fa-solid fa-khanda',              'Fighter',  20],

    ['tank_3',  'Muro Viviente', 'Gana con 3 Tanques',   'El Escudo',       'fa-solid fa-shield',         'Tank',  3],
    ['tank_10', 'Fortaleza',     'Gana con 10 Tanques',  'La Fortaleza',    'fa-solid fa-shield-halved',  'Tank', 10],
    ['tank_20', 'Inamovible',    'Gana con 20 Tanques',  'El Muro',         'fa-solid fa-shield-heart',   'Tank', 20],

    ['mage_3',  'Aprendiz de Magia', 'Gana con 3 Magos',   'Aprendiz de Magia', 'fa-solid fa-hat-wizard',          'Mage',  3],
    ['mage_10', 'Archimago',         'Gana con 10 Magos',  'El Archimago',      'fa-solid fa-wand-magic-sparkles', 'Mage', 10],
    ['mage_20', 'Señor del Caos',    'Gana con 20 Magos',  'El Archimago',      'fa-solid fa-bolt-lightning',      'Mage', 20],

    ['assassin_3',  'En las Sombras',       'Gana con 3 Asesinos',   'En las Sombras',       'fa-solid fa-user-ninja', 'Assassin',  3],
    ['assassin_10', 'Maestro Asesino',      'Gana con 10 Asesinos',  'Maestro Asesino',      'fa-solid fa-skull',      'Assassin', 10],
    ['assassin_20', 'La Muerte Silenciosa', 'Gana con 20 Asesinos',  'La Muerte Silenciosa', 'fa-solid fa-ghost',      'Assassin', 20],

    ['support_3',  'Mano Amiga',         'Gana con 3 Soportes',   'El Guardián',           'fa-solid fa-heart',           'Support',  3],
    ['support_10', 'Guardián',           'Gana con 10 Soportes',  'Guardián de la Arena',  'fa-solid fa-handshake-angle', 'Support', 10],
    ['support_20', 'La Mamá del Equipo', 'Gana con 20 Soportes',  'La Mamá del Equipo',    'fa-solid fa-people-group',    'Support', 20],

    ['marksman_3',  'Francotirador',   'Gana con 3 Tiradores',   'El Francotirador',  'fa-solid fa-crosshairs',   'Marksman',  3],
    ['marksman_10', 'Artillero',       'Gana con 10 Tiradores',  'El Artillero',      'fa-solid fa-bullseye',     'Marksman', 10],
    ['marksman_20', 'Precisión Letal', 'Gana con 20 Tiradores',  'Precisión Letal',   'fa-solid fa-person-rifle', 'Marksman', 20],

    // =========================================================
    // ESPECIALES SERIOS
    // =========================================================
    ['todoterreno',  'Todoterreno',  'Gana con al menos 1 campeón de cada clase',    'El Todoterreno',  'fa-solid fa-infinity',      'especial', 1],
    ['especialista', 'Especialista', 'Gana con 10 campeones de la misma clase',      'El Especialista', 'fa-solid fa-award',         'especial', 1],
    ['sin_main',     'Sin Tu Main',  'Gana con un campeón que no es tu top mastery', 'Sin Excusas',     'fa-solid fa-face-surprise', 'especial', 1],

    // Easter egg
    ['lagartos', 'Lagarto del Sol', 'Eres uno de los Lagartos del Sol. Bienvenido a la Arena.', 'Lagarto del Sol', 'fa-solid fa-dragon', 'especial', 1],

    // =========================================================
    // REGIONES
    // =========================================================
    ['demacia_5',    'DEMACIA!',               'Gana con 5 campeones de Demacia. La gloria os precede',         'Campeón de Demacia',    'fa-solid fa-shield-halved', 'especial', 5],
    ['freljord_5',   'La Tundra',              'Gana con 5 campeones del Freljord. El frío no perdona',         'Hijo del Freljord',     'fa-solid fa-snowflake',     'especial', 5],
    ['shurima_5',    'Shurima!',               'Gana con 5 campeones de Shurima. Las arenas os reclaman',       'Hijo del Desierto',     'fa-solid fa-sun',           'especial', 5],
    ['bilgewater_4', 'Los Piratas',            'Gana con 4 campeones de Bilgewater. La ley es opcional aquí',   'Pirata de Bilgewater',  'fa-solid fa-anchor',        'especial', 4],
    ['targon_4',     'Celestiales',            'Gana con 4 campeones de Targon. Dios entre mortales',           'Celestial',             'fa-solid fa-star',          'especial', 4],
    ['piltover_5',   'La Ciudad del Progreso', 'Gana con 5 campeones de Piltover o Zaun. Progreso imparable',   'Del Progreso',          'fa-solid fa-gear',          'especial', 5],
    ['noxus_5',      'Noxus o Nada',           'Gana con 5 campeones de Noxus',                                 'Gloria a Noxus',        'fa-solid fa-hand-fist',     'especial', 5],
    ['ionia_5',      'Filosofía de Pelea',     'Gana con 5 campeones de Ionia',                                 'Discípulo de Ionia',    'fa-solid fa-yin-yang',      'especial', 5],

    // =========================================================
    // GRUPOS DE LORE / RAZA
    // =========================================================
    ['yordles',     'Los Amiguitos',            'Gana con 5 Yordles distintos. Los más adorables y peligrosos',       'El Amiguito',             'fa-solid fa-children',     'especial', 5],
    ['void_3',      'Del Vacío Somos',          'Gana con 3 criaturas del Vacío',                                      'Engendro del Vacío',      'fa-solid fa-eye',          'especial', 3],
    ['shadow_4',    'Los Muertos no Descansan', 'Gana con 4 campeones de las Islas de las Sombras',                    'Del Más Allá',            'fa-solid fa-skull',        'especial', 4],
    ['darkin',      'Los Cuatro Jinetes',       'Gana con los 4 Darkin: Aatrox, Varus, Naafiri y Kayn',                'Jinete del Apocalipsis',  'fa-solid fa-droplet',      'especial', 4],
    ['arcane_4',    'Como en la Serie',         'Gana con 4 personajes de Arcane (Netflix)',                            'Como en la Serie',        'fa-solid fa-tv',           'especial', 4],
    ['bestias_5',   'El Reino Animal',          'Gana con 5 campeones que son bestias o criaturas',                    'El Señor de las Bestias', 'fa-solid fa-paw',          'especial', 5],
    ['femeninas_5', 'Club de Chicas',           'Gana con 5 campeonas femeninas distintas',                            'Del Club de Chicas',      'fa-solid fa-venus',        'especial', 5],
    ['ascendidos',  'Los Ascendidos',           'Gana con los 4 Ascendidos de Shurima: Nasus, Renekton, Azir y Xerath','El Ascendido',            'fa-solid fa-ankh',         'especial', 4],
    ['kinkou_4',    'El Orden de las Sombras',  'Gana con Zed, Shen, Akali y Kennen. Honor o traición, tú eliges',    'Maestro del Orden',       'fa-solid fa-eye-low-vision','especial', 4],
    ['cazadores_4', 'La Cacería',               'Gana con 4 campeones cuyo lore es la caza (Rengar, Kha, Nidalee...)', 'El Cazador',              'fa-solid fa-crosshairs',   'especial', 4],
    ['tilt_3',      'El Equipo Tóxico',         'Gana con 3 de los campeones más frustrantes: Teemo, Yuumi, Shaco, Zoe, Singed', 'El Tóxico',  'fa-solid fa-biohazard',    'especial', 3],

    // =========================================================
    // PAREJAS / HERMANOS / RIVALES DE LORE
    // =========================================================

    // Hermanos de sangre
    ['par_yasuo_yone',    'Hermanos de Sangre',      'Gana con Yasuo Y Yone. Al final se reconciliaron... más o menos',         'Hermano de Sangre',      'fa-solid fa-wind',             'especial', 1],
    ['par_darius_draven', 'Orgullo de Noxus',        'Gana con Darius Y Draven. El hermano serio y el payaso narcisista',       'Orgullo de Noxus',       'fa-solid fa-helmet-safety',    'especial', 1],
    ['par_garen_lux',     'La Familia Crownguard',   'Gana con Garen Y Lux. Uno grita DEMACIA, la otra usa magia ilegalmente',  'De los Crownguard',      'fa-solid fa-sun',              'especial', 1],
    ['par_nasus_renekton','Pleito Familiar',          'Gana con Nasus Y Renekton. Siguen sin hablarse tras 3000 años',           'Problema Familiar',      'fa-solid fa-scale-unbalanced', 'especial', 1],
    ['par_kayle_morgana', 'Ángeles Caídos',          'Gana con Kayle Y Morgana. La familia más disfuncional del multiverso',    'Ángel Caído',            'fa-solid fa-feather',          'especial', 1],
    ['par_kata_cass',     'Las du Couteau',          'Gana con Katarina Y Cassiopeia. Una asesina, la otra maldecida en Shurima','Noble de Noxus',         'fa-solid fa-masks-theater',    'especial', 1],

    // Parejas románticas / confirmadas
    ['par_xayah_rakan',    'Amor en la Arena',        'Gana con Xayah Y Rakan. La pareja más romántica y más irritante del juego','Enamorado de la Arena',  'fa-solid fa-heart',            'especial', 1],
    ['par_jinx_vi',        'Hermanas de Arcane',      'Gana con Jinx Y Vi. Como en la serie pero sin llorar tanto',              'Hermana de Arcane',      'fa-solid fa-people-arrows',    'especial', 1],
    ['par_lucian_senna',   'Hasta la Muerte y Más',   'Gana con Lucian Y Senna. Lucian lleva años persiguiendo el alma de su esposa','Unidos por el Alma', 'fa-solid fa-infinity',         'especial', 1],
    ['par_cait_vi',        'Piltover Finest',         'Gana con Caitlyn Y Vi. La sheriff y la ex-presa. Cánon en Arcane',       'Piltover Finest',        'fa-solid fa-handcuffs',        'especial', 1],
    ['par_ashe_tryndamere','El Matrimonio del Freljord','Gana con Ashe Y Tryndamere. Se casaron por política. Qué romántico',   'Casado por Conveniencia','fa-solid fa-rings-wedding',    'especial', 1],

    // Rivales eternos
    ['par_rengar_khazix',  'La Caza Eterna',          'Gana con Rengar Y Kha\'Zix. La rivalidad más icónica del juego',         'El Rival Eterno',        'fa-solid fa-skull-crossbones', 'especial', 1],
    ['par_leona_diana',    'Sol y Luna',              'Gana con Leona Y Diana. Dos caras de la misma orden, enfrentadas',       'Entre el Sol y la Luna', 'fa-solid fa-circle-half-stroke','especial', 1],
    ['par_azir_xerath',    'La Traición de Shurima',  'Gana con Azir Y Xerath. El faraón traicionado que perdió su ascensión', 'Traicionado',            'fa-solid fa-ankh',             'especial', 1],
    ['par_zed_shen',       'Hermanos del Orden',      'Gana con Zed Y Shen. Antes hermanos del Kinkou, ahora némesis',          'Renegado del Orden',     'fa-solid fa-yin-yang',         'especial', 1],
    ['par_thresh_lucian',  'El Ladrón de Almas',      'Gana con Thresh Y Lucian. Thresh tiene el alma de la esposa de Lucian', 'La Linterna',            'fa-solid fa-lantern',          'especial', 1],
    ['par_viktor_jayce',   'Dos Filosofías',          'Gana con Viktor Y Jayce. Dos genios de Piltover con visiones opuestas', 'El Ingeniero',           'fa-solid fa-gear',             'especial', 1],
    ['par_kaisa_khazix',   'La Superviviente',        'Gana con Kai\'Sa Y Kha\'Zix. Ella sobrevivió al Vacío, él la persigue', 'Superviviente del Vacío','fa-solid fa-eye',              'especial', 1],

    // Aliados / amigos
    ['par_ekko_jinx',      'Amigos Perdidos',         'Gana con Ekko Y Jinx. Fueron amigos en Zaun antes de que todo se torciese','Amigo Perdido',         'fa-solid fa-clock-rotate-left','especial', 1],
    ['par_amumu_nunu',     'Amigos para Siempre',     'Gana con Amumu Y Nunu. Los dos más tristes del roster intentando ser felices','El Más Triste',      'fa-solid fa-face-sad-cry',    'especial', 1],
    ['par_swain_leblanc',  'El Concilio de Noxus',    'Gana con Swain Y LeBlanc. Llevan décadas en guerra de poder dentro de Noxus','Del Concilio',       'fa-solid fa-chess-queen',      'especial', 1],

    // =========================================================
    // CAMPEONES INDIVIDUALES — LOS CLÁSICOS
    // =========================================================
    ['teemo',        'El Champiñón de Satanás', 'Gana con Teemo. Enhorabuena, eres esa persona',                       'El Champiñón de Satanás', 'fa-solid fa-biohazard',         'campeon',  17],
    ['yuumi',        'La Garrapata',            'Gana con Yuumi. Tu dúo te lo hizo todo, admítelo',                    'La Garrapata',            'fa-solid fa-cat',               'campeon', 350],
    ['shaco',        'El Payaso del Terror',    'Gana con Shaco. Nadie se alegra cuando ganas con él',                 'El Payaso del Terror',    'fa-solid fa-masks-theater',     'campeon',  35],
    ['singed',       'Ni Mirándote',            'Gana con Singed. Literalmente solo corriste todo el rato',            'El Corredor',             'fa-solid fa-person-running',    'campeon',  27],
    ['tryndamere',   'Sin Morir No Vale',       'Gana con Tryndamere. El R no cuenta como skill',                     'El Inmortal',             'fa-solid fa-rotate-left',       'campeon',  23],
    ['nasus',        'Las 10000 Stackes',       'Gana con Nasus. Cuántas horas de farming para este momento',          'Las 10000 Stackes',       'fa-solid fa-dog',               'campeon',  75],
    ['ryze',         'Esperando el Rework',     'Gana con Ryze. Lo reworkean antes de que domines la curva de aprendizaje','En Espera de Rework','fa-solid fa-wrench',            'campeon',  13],
    ['zilean',       'El Abuelo',               'Gana con Zilean. Más viejo que el propio juego',                     'El Anciano',              'fa-solid fa-clock',             'campeon',  26],
    ['heimerdinger', 'El Nerd',                 'Gana con Heimerdinger. Seguro que lo explicas todo al equipo',       'El Inventor',             'fa-solid fa-flask',             'campeon',  74],
    ['kalista',      'Jugador Veterano',        'Gana con Kalista. Ya ni recuerdas cuándo fue meta',                  'Del Tiempo Pasado',       'fa-solid fa-bone',              'campeon', 429],
    ['seraphine',    'La Influencer',           'Gana con Seraphine. Muchos followers, pocas victorias',              'La Influencer',           'fa-brands fa-instagram',        'campeon', 147],
    ['akali',        'Por Fin',                 'Gana con Akali. Una partida donde no te tiltaste a la tercera muerte','Ninja sin Clan',         'fa-solid fa-mask',              'campeon',  84],
    ['yasuo',        'Yasuo Sin Excusas',       'Gana con Yasuo. A ver si repites sin echarle la culpa al equipo',    'Main de Yasuo',           'fa-solid fa-wind',              'campeon', 157],
    ['yone',         'El Hermano Favorito',     'Gana con Yone. Literalmente Yasuo pero con más lore y más daño',     'El Hermano Muerto',       'fa-solid fa-yin-yang',          'campeon', 777],
    ['zed',          'El Main de Zed',          'Gana con Zed. Lo juegas porque la skin mola, admítelo',              'La Sombra',               'fa-solid fa-sun',               'campeon', 238],
    ['rammus',       'Ok',                      'Gana con Rammus. Ok',                                                'Ok.',                     'fa-solid fa-thumbs-up',         'campeon',  33],

    // =========================================================
    // CAMPEONES INDIVIDUALES — LOS NUEVOS
    // =========================================================
    ['pyke',         'El Manda Callar al ADC',  'Gana con Pyke. El soporte que ejecuta a todos y no da el oro',       'Verdugo de Piltover',     'fa-solid fa-dagger',            'campeon', 555],
    ['blitzcrank',   'El Gancho o Nada',        'Gana con Blitzcrank. Si fallas el Q eres inútil. No hay término medio','El Gancho',             'fa-solid fa-magnet',            'campeon',  53],
    ['jax',          'Con una Farola',           'Gana con Jax. Armado con el arma más temible del universo: una farola','El de la Farola',       'fa-solid fa-street-view',       'campeon',  24],
    ['malphite',     'Solo el R',               'Gana con Malphite. Esperaste 5 minutos el teamfight perfecto para el R','La Roca Voladora',      'fa-solid fa-meteor',            'campeon',  54],
    ['soraka',       'La Madre Teresa',         'Gana con Soraka. Curas, te insultan, curas más. Sin honor reconocido','La Madre Teresa',        'fa-solid fa-star-of-life',      'campeon',  16],
    ['amumu',        'Sin Amigos',              'Gana con Amumu. Sigue buscando un amigo. Spoiler: hoy tampoco',      'El Solitario',            'fa-solid fa-face-sad-cry',      'campeon',  32],
    ['kled',         'El Más Tóxico',           'Gana con Kled. Oficialmente el campeón más irritable del juego',     'El Irritable',            'fa-solid fa-face-angry',        'campeon', 240],
    ['illaoi',       'Los Tentáculos',          'Gana con Illaoi. Quien entra a su calle muere solo. Sin discusión',  'Tentacular',              'fa-solid fa-water',             'campeon', 420],
    ['zac',          'El Chicle',               'Gana con Zac. La única cosa que no se puede limpiar del suelo',     'La Mancha Verde',         'fa-solid fa-circle',            'campeon', 154],
    ['sett',         'El Jefe',                 'Gana con Sett. Hijo de un Vastaya que montó su propia arena. Un pro','El Jefe',                 'fa-solid fa-fist-raised',       'campeon', 875],
    ['urgot',        'Las Patas',               'Gana con Urgot. Nadie sabe cuántas patas tiene ni por qué',          'El Arácnido',             'fa-solid fa-spider',            'campeon',   6],
    ['fizz',         'El Escurridizo',          'Gana con Fizz. Imposible pillarlo, matarlo o entenderlo',            'Escurridizo',             'fa-solid fa-fish',              'campeon', 105],
    ['leblanc',      '¿Cuál Es la Real?',       'Gana con LeBlanc. Ni tú sabes cuál de los clones eres ya',          'El Engaño',               'fa-solid fa-question',          'campeon',   7],
    ['gangplank',    'El Naranja',              'Gana con Gangplank. Come una naranja y cancela cualquier CC. Lógica pirata','El Pirata Mayor',  'fa-solid fa-lemon',             'campeon',  41],
    ['bard',         'El Rarísimo',             'Gana con Bard. Nadie sabe qué es, de dónde viene ni por qué recoge campanillas','El Viajero Cósmico','fa-solid fa-music',        'campeon', 432],
    ['veigar',       'El Gran Villano',         'Gana con Veigar. El mago más malvado del mundo... que resulta ser un Yordle adorable','El Gran Villano','fa-solid fa-hat-wizard','campeon',  45],
    ['morgana',      'El Bind Eterno',          'Gana con Morgana. Su Q es el spell más frustrante de todo el juego. Punto','La Encadenadora',  'fa-solid fa-link',              'campeon',  25],
    ['aurelion',     'El Dragón Cósmico',       'Gana con Aurelion Sol. Creó las estrellas y obedece a Targon por culpa de una corona','El Dragón Cósmico','fa-solid fa-dragon', 'campeon', 136],
    ['aphelios',     'Las 5 Armas',             'Gana con Aphelios. Cinco armas distintas y nadie sabe cómo funciona','El Arma Viviente',       'fa-solid fa-moon',              'campeon', 523],
    ['miss_fortune', 'La Fortuna Sonríe',       'Gana con Miss Fortune. Reina de Bilgewater, la más popular del juego','La Fortuna Sonríe',     'fa-solid fa-hat-cowboy',        'campeon',  21],
    ['kindred',      'El Último Baile',         'Gana con Kindred. La muerte misma jugando en tu equipo. Irónico',   'El Último Bailarín',      'fa-solid fa-hand-holding-heart','campeon', 203],
    ['jhin',         'El Artista',              'Gana con Jhin. Todo tiene que salir en conjuntos de 4. Todo',       'El Artista',              'fa-solid fa-palette',           'campeon', 202],

    // =========================================================
    // LOGROS DE ARENA — TÍTULOS ÉPICOS
    // =========================================================
    ['arena_baptismo',   'Bautismo de Fuego',    'Gana con un campeón que nunca habías jugado antes. Primera vez sin red',   'El Temerario',        'fa-solid fa-fire',      'especial', 1],
    ['arena_veterano',   'Veterano de la Arena', 'Desbloquea 10 logros distintos. Ya sabes lo que haces aquí',               'Veterano de la Arena','fa-solid fa-medal',     'total',    10],
    ['arena_completista','El Completista',        'Desbloquea 25 logros distintos. A este ritmo acabas el roster',            'El Completista',      'fa-solid fa-list-check','total',    25],
    ['arena_obsesionado','Obsesionado',           'Desbloquea 50 logros distintos. Necesitas ayuda profesional. De la buena', 'Obsesionado',         'fa-solid fa-brain',     'total',    50],
    ['arena_dios',       'Dios de la Arena',     'Desbloquea todos los logros de campeón individual. El roster entero es tuyo','Dios de la Arena',   'fa-solid fa-bolt',      'total',    30],
];

$stmt = $db->prepare("INSERT INTO logros (clave, nombre, descripcion, titulo, icono, tipo, valor_objetivo) VALUES (?,?,?,?,?,?,?)");
foreach ($logros as $l) {
    $stmt->execute($l);
}

echo "<h2>Logros insertados: " . count($logros) . "</h2>";
echo "<ul style='font-family:monospace;font-size:.8rem'>";
$cats = ['Cantidad' => 0, 'Clases' => 0, 'Especiales' => 0, 'Regiones' => 0, 'Grupos lore' => 0, 'Parejas' => 0, 'Campeones' => 0];
foreach ($logros as $l) {
    if (str_starts_with($l[0], 'camp_'))         $cats['Cantidad']++;
    elseif (in_array($l[5], ['Fighter','Tank','Mage','Assassin','Support','Marksman'])) $cats['Clases']++;
    elseif (in_array($l[0], ['todoterreno','especialista','sin_main','lagartos']))      $cats['Especiales']++;
    elseif (in_array($l[0], ['demacia_5','freljord_5','shurima_5','bilgewater_4','targon_4','piltover_5','noxus_5','ionia_5'])) $cats['Regiones']++;
    elseif (str_starts_with($l[0], 'par_'))      $cats['Parejas']++;
    elseif ($l[5] === 'campeon')                 $cats['Campeones']++;
    else                                         $cats['Grupos lore']++;
}
foreach ($cats as $k => $v) echo "<li>$k: $v</li>";
echo "</ul>";
echo "<p><strong>Recuerda:</strong> Primero ejecuta <a href='" . BASE_URL . "database/migrate.php'>migrate.php</a> si no lo has hecho.</p>";
echo "<p><a href='" . BASE_URL . "'>Volver al inicio</a></p>";

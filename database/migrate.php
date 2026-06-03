<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/helpers.php';

$db = getDB();
$ok = [];
$err = [];

function runAlter(PDO $db, string $sql, string $desc): void {
    global $ok, $err;
    try {
        $db->exec($sql);
        $ok[] = "OK: $desc";
    } catch (PDOException $e) {
        // 1060 = column already exists, ignorar
        if (str_contains($e->getMessage(), 'Duplicate column name') || str_contains($e->getMessage(), '1060')) {
            $ok[] = "YA EXISTE: $desc";
        } else {
            $err[] = "ERROR ($desc): " . $e->getMessage();
        }
    }
}

// logros: tipo como VARCHAR(30) si era ENUM
runAlter($db,
    "ALTER TABLE logros MODIFY COLUMN tipo VARCHAR(30) NOT NULL",
    "logros.tipo → VARCHAR(30)"
);

// logros: icono como VARCHAR(100) para clases FA
runAlter($db,
    "ALTER TABLE logros MODIFY COLUMN icono VARCHAR(100) NOT NULL DEFAULT 'fa-solid fa-trophy'",
    "logros.icono → VARCHAR(100)"
);

// logros: titulo que se desbloquea con este logro
runAlter($db,
    "ALTER TABLE logros ADD COLUMN titulo VARCHAR(100) NULL DEFAULT NULL AFTER descripcion",
    "logros.titulo"
);

// logros: timestamp de creación para badge NUEVO
runAlter($db,
    "ALTER TABLE logros ADD COLUMN creado_en DATETIME DEFAULT CURRENT_TIMESTAMP AFTER valor_objetivo",
    "logros.creado_en"
);

// invocadores: campos añadidos por evolución del proyecto
runAlter($db,
    "ALTER TABLE invocadores ADD COLUMN pin_hash VARCHAR(255) NULL DEFAULT NULL",
    "invocadores.pin_hash"
);
runAlter($db,
    "ALTER TABLE invocadores ADD COLUMN ranked_solo VARCHAR(50) NULL DEFAULT NULL",
    "invocadores.ranked_solo"
);
runAlter($db,
    "ALTER TABLE invocadores ADD COLUMN top_campeon VARCHAR(50) NULL DEFAULT NULL",
    "invocadores.top_campeon"
);

// invocadores: título activo elegido por el jugador
runAlter($db,
    "ALTER TABLE invocadores ADD COLUMN titulo_activo VARCHAR(100) NULL DEFAULT NULL",
    "invocadores.titulo_activo"
);

// campeones_ganados: clase del campeón
runAlter($db,
    "ALTER TABLE campeones_ganados ADD COLUMN campeon_clase VARCHAR(20) NULL DEFAULT NULL AFTER campeon_nombre",
    "campeones_ganados.campeon_clase"
);

// invocadores: apodo personalizado (distinto del Riot ID)
runAlter($db,
    "ALTER TABLE invocadores ADD COLUMN apodo VARCHAR(50) NULL DEFAULT NULL AFTER tag_line",
    "invocadores.apodo"
);

echo "<h2>Resultado de la migración</h2>";
foreach ($ok  as $msg) echo "<p style='color:green'>$msg</p>";
foreach ($err as $msg) echo "<p style='color:red'>$msg</p>";
echo "<br><strong>Listo.</strong> Ahora ejecuta <a href='" . BASE_URL . "fix_logros.php'>fix_logros.php</a> para recargar los logros.";
echo "<br><a href='" . BASE_URL . "'>Volver al inicio</a>";

# League of Arena

> Tracker de victorias para el modo **Arena** de League of Legends.  
> Marca los campeones con los que has ganado, desbloquea logros, compite en el ranking y lleva el historial completo de tus partidas.

> [!WARNING]
> **La API de Riot no soporta Arena de forma fiable.**  
> El historial de partidas de Arena tiene cobertura limitada e inconsistente en la API oficial — hay partidas que directamente no aparecen. Este proyecto es una **solución básica y manual** para llevar el registro mientras Riot se pone las pilas y se toma en serio el mejor modo de juego. Por eso existe la opción de **marcar campeones manualmente**: para compensar lo que la API no detecta.

**Proyecto open source — úsalo, instálalo para tu comunidad, forkéalo y mejóralo.**

Creado por [Antonio Pulido DEV](https://github.com/Letualtv)

---

## Screenshots

| Inicio | Perfil |
|--------|--------|
| ![Inicio](screenshots/inicio.png) | ![Perfil](screenshots/perfil.png) |

| Campeones | Logros |
|-----------|--------|
| ![Campeones](screenshots/campeones.png) | ![Logros](screenshots/logros.png) |

| Ranking | Móvil |
|---------|-------|
| ![Ranking](screenshots/ranking.png) | ![Móvil](screenshots/movil.png) |

---

## Tabla de contenidos

- [Tecnologías](#tecnologías)
- [Instalación local (XAMPP)](#instalación-local-xampp)
- [Conseguir la API Key de Riot](#conseguir-la-api-key-de-riot)
- [Base de datos](#base-de-datos)
- [Migración](#migración)
- [Sistema de PIN y cuentas](#sistema-de-pin-y-cuentas)
- [Apodo personalizado](#apodo-personalizado)
- [Panel de administración y logros](#panel-de-administración-y-logros)
- [Recargar logros (fix\_logros)](#recargar-logros)
- [Estructura del proyecto](#estructura-del-proyecto)
- [Subir a producción](#subir-a-producción)
- [Cómo compartir con tu comunidad](#cómo-compartir-con-tu-comunidad)
- [Contribuir](#contribuir)

---

## Tecnologías

| Capa | Tecnología |
|------|-----------|
| Backend | PHP 8+ (vanilla, sin framework) |
| Base de datos | MySQL / MariaDB |
| Frontend | CSS custom (tema oscuro dorado) + Tailwind CSS CDN |
| Iconos | Font Awesome 6 |
| Tipografía | Google Fonts (Cinzel + Inter) |
| API externa | [Riot Games API](https://developer.riotgames.com) + Data Dragon |
| Servidor local | XAMPP (Apache + MySQL) |

---

## Instalación local (XAMPP)

### 1. Clonar o descargar el proyecto

```bash
git clone https://github.com/Letualtv/league-of-arena.git
```

O descarga el ZIP y descomprime en `C:\xampp\htdocs\Leagueofarena\`.

### 2. Arrancar XAMPP

Inicia los módulos **Apache** y **MySQL** desde el panel de XAMPP.

### 3. Crear el archivo de configuración

El archivo `config/config.php` está excluido del repositorio (`.gitignore`) para proteger las credenciales. Créalo manualmente copiando esta plantilla:

```php
<?php
$isProduction = ($_SERVER['HTTP_HOST'] ?? '') !== 'localhost';

if ($isProduction) {
    define('BASE_URL', '/');
    define('DB_HOST', 'tu-host-mysql');
    define('DB_NAME', 'nombre_base_de_datos');
    define('DB_USER', 'usuario_bd');
    define('DB_PASS', 'contraseña_bd');
} else {
    define('BASE_URL', '/Leagueofarena/');
    define('DB_HOST', 'localhost:3307');
    define('DB_NAME', 'league_arena');
    define('DB_USER', 'root');
    define('DB_PASS', '');
}

// Obtén tu key en https://developer.riotgames.com (caduca cada 24h)
define('RIOT_API_KEY', 'RGAPI-XXXXXXXX-XXXX-XXXX-XXXX-XXXXXXXXXXXX');

define('REGIONS', [
    'EUW'  => ['platform' => 'euw1',  'regional' => 'europe'],
    'EUNE' => ['platform' => 'eun1',  'regional' => 'europe'],
    'TR'   => ['platform' => 'tr1',   'regional' => 'europe'],
    'RU'   => ['platform' => 'ru',    'regional' => 'europe'],
    'NA'   => ['platform' => 'na1',   'regional' => 'americas'],
    'BR'   => ['platform' => 'br1',   'regional' => 'americas'],
    'LAN'  => ['platform' => 'la1',   'regional' => 'americas'],
    'LAS'  => ['platform' => 'la2',   'regional' => 'americas'],
    'KR'   => ['platform' => 'kr',    'regional' => 'asia'],
    'JP'   => ['platform' => 'jp1',   'regional' => 'asia'],
    'OCE'  => ['platform' => 'oc1',   'regional' => 'sea'],
]);

define('QUEUE_ARENA', 1700);
define('DDRAGON_BASE', 'https://ddragon.leagueoflegends.com');
define('MATCHES_PER_SYNC', 20);

// Pon aquí tu propio Riot ID — será el administrador del sitio
define('ADMIN_GAME_NAME', 'TuNombreAqui');
define('ADMIN_TAG_LINE',  'EUW');

define('FEED_MAX_DEFAULT', 10);
```

### 4. Crear la base de datos

Abre [http://localhost/phpmyadmin](http://localhost/phpmyadmin), ve a la pestaña **SQL** y pega el contenido de `database/schema.sql`.

### 5. Acceder a la aplicación

```
http://localhost/Leagueofarena/
```

---

## Conseguir la API Key de Riot

1. Ve a [developer.riotgames.com](https://developer.riotgames.com) e inicia sesión con tu cuenta de LoL.
2. En el dashboard encontrarás tu **Development Key** generada automáticamente.
3. Cópiala y pégala en `config/config.php` → constante `RIOT_API_KEY`.

### Límites de la Development Key

| Límite | Valor |
|--------|-------|
| Peticiones por segundo | 20 |
| Peticiones por 2 minutos | 100 |
| Caducidad | **Cada 24 horas** (hay que regenerarla) |

La aplicación ya incluye pausas entre peticiones durante el sync para mantenerse dentro del límite.

Si necesitas una key permanente, solicita una **Personal API Key** en el mismo portal (requiere formulario de solicitud a Riot).

### Limitaciones de la API con Arena

La Riot API no trata Arena como un modo de primera clase. Problemas conocidos:

- Partidas recientes pueden no aparecer en el historial durante horas o directamente no aparecer nunca.
- El endpoint de partidas por queue (`queue=1700`) a veces devuelve resultados incompletos.
- No hay endpoint dedicado para stats de Arena (posición, rondas, dúo…); hay que parsear el JSON genérico de Match V5.

**Por eso existe el marcado manual de campeones**: si la API no detecta una victoria, el jugador puede marcarla a mano desde la página de campeones. Es la solución práctica mientras Riot decide tomarse en serio el mejor modo de juego.

---

## Base de datos

El schema completo está en `database/schema.sql`. Estas son las tablas:

### `invocadores`
Caché de datos del jugador obtenidos de la API.

| Campo | Descripción |
|-------|-------------|
| `puuid` | Identificador único de Riot (clave principal lógica) |
| `game_name` / `tag_line` | Nombre y tag del Riot ID |
| `apodo` | Nombre personalizado elegido por el jugador (opcional, máx. 50 chars) |
| `region` | Región del jugador |
| `icono_id` / `nivel` | Icono de perfil y nivel de cuenta |
| `pin_hash` | Hash bcrypt del PIN de autenticación |
| `ranked_solo` | Rango actual en Ranked Solo |
| `titulo_activo` | Título desbloqueado y seleccionado por el jugador |

### `partidas_arena`
Una fila por partida por jugador.

| Campo | Descripción |
|-------|-------------|
| `match_id` | ID de la partida en Riot |
| `puuid` | Jugador |
| `campeon_id` / `campeon_nombre` | Campeón usado |
| `posicion` | Posición final: **1 = victoria**, 8 = último |
| `kills / muertes / asistencias` | KDA |
| `dano_total` | Daño total al final de la partida |

### `campeones_ganados`
Campeones con los que el jugador ha conseguido un #1.

| Campo | Descripción |
|-------|-------------|
| `campeon_id` / `campeon_nombre` | Campeón |
| `campeon_clase` | Clase (Fighter, Mage, Tank…) |
| `veces_ganado` | Número de victorias con ese campeón |
| `marcado_manual` | `1` si el usuario lo marcó a mano (se puede desmarcar), `0` si lo detectó la API (no se puede desmarcar) |

### `logros` y `logros_desbloqueados`
Definición de logros y qué logros tiene desbloqueado cada jugador.

---

## Migración

Si ya tienes la base de datos creada y el proyecto ha evolucionado con nuevas columnas, ejecuta la migración desde el navegador:

```
http://localhost/Leagueofarena/database/migrate.php
```

Aplica los `ALTER TABLE` de forma segura (ignora columnas que ya existan). Columnas que añade sobre el schema base:

- `logros.tipo` → VARCHAR(30) en lugar de ENUM
- `logros.icono` → VARCHAR(100) para clases de Font Awesome
- `logros.titulo` → título que desbloquea el logro
- `logros.creado_en` → timestamp para el badge "NUEVO"
- `invocadores.pin_hash` → autenticación por PIN
- `invocadores.ranked_solo` → rango actual
- `invocadores.top_campeon` → campeón más jugado
- `invocadores.titulo_activo` → título seleccionado por el jugador
- `campeones_ganados.campeon_clase` → clase del campeón
- `invocadores.apodo` → nombre personalizado del jugador (opcional)

---

## Sistema de PIN y cuentas

### Cómo funciona

Cualquier jugador puede buscar su nombre en la app y ver su perfil público. Para **editar** (marcar campeones manualmente, elegir título) necesita **reclamar** su cuenta con un PIN.

### Reclamar una cuenta

1. Busca tu Riot ID en la página de inicio.
2. En tu perfil, pulsa **Acceder**.
3. Si nadie ha reclamado esa cuenta todavía, se te pedirá que establezcas un PIN de 4-8 dígitos. **El primero en hacerlo se convierte en propietario.**
4. En visitas siguientes, introduces el PIN para iniciar sesión.

> El PIN se guarda como hash bcrypt en la base de datos. No se puede recuperar si se olvida (habría que resetear el campo `pin_hash` en la BD directamente).

---

## Apodo personalizado

Cada jugador que haya reclamado su cuenta puede establecer un **apodo** distinto a su Riot ID. Aparecerá en su perfil, en el ranking y en el feed de actividad.

### Cómo cambiarlo (jugador)

1. Inicia sesión en tu perfil.
2. Verás un campo **Apodo** bajo el selector de título, con un contador de caracteres en tiempo real.
3. Escribe el nombre que quieras (**máx. 50 caracteres**) y pulsa **Guardar**.
4. Para eliminarlo y volver a usar tu Riot ID, pulsa **Quitar apodo** (pide confirmación).

> Si un jugador tiene apodo, su Riot ID original se sigue mostrando en pequeño como referencia en su perfil y en el ranking.

### Gestión de apodos (admin)

El administrador puede **quitar el apodo** de cualquier jugador desde el panel de administración → sección Cuentas. Aparece un botón gris **Quitar apodo** en la fila de cada jugador que tenga uno puesto.

---

## Panel de administración y logros

### Quién es el administrador

El administrador se define en `config/config.php`:

```php
define('ADMIN_GAME_NAME', 'TuNombreAqui');
define('ADMIN_TAG_LINE',  'EUW');
```

El jugador con ese Riot ID, una vez que ha **reclamado su cuenta** con PIN e iniciado sesión, verá un botón de llave inglesa 🔧 en el header que da acceso al panel de administración.

### Qué puede hacer el administrador

- **Crear logros** personalizados con nombre, descripción, icono, tipo y objetivo — el formulario incluye una previsualización en vivo de cómo quedará el logro antes de crearlo.
- Ver y **eliminar logros** existentes.
- **Resetear el PIN** de cualquier cuenta (el jugador puede volver a reclamarla).
- **Borrar una cuenta** por completo (elimina partidas, campeones, logros e invocador).
- **Quitar el apodo** de cualquier jugador.
- Configurar el número máximo de eventos en el feed de actividad del ranking.

### Crear logros para tu comunidad

Si instalas esto para un grupo de amigos, **la persona que configure el servidor** (la que pone su nombre en `ADMIN_GAME_NAME`) puede crear logros personalizados para toda la comunidad: logros de lore, logros de parejas de campeones, logros graciosos, lo que quieras.

El resto de jugadores solo necesitan reclamar su cuenta con PIN para que sus victorias y logros queden guardados.

### Gestión de cuentas (admin)

Desde el panel de administración, en la sección **Cuentas**, el admin ve la lista de todos los invocadores registrados con su estado de PIN:

- Si un jugador olvidó su PIN o lo reclamó por error, el admin puede **resetear el PIN** (botón naranja). La cuenta queda libre para ser reclamada de nuevo.
- Si hay una cuenta errónea, duplicada o de un troll, el admin puede **borrar la cuenta** (botón rojo). Se eliminan permanentemente todos sus datos: partidas, campeones ganados y logros desbloqueados.

---

## Recargar logros

El archivo `fix_logros.php` vacía la tabla `logros` y la rellena con el set completo actualizado:

```
http://localhost/Leagueofarena/fix_logros.php
```

> **Atención:** borra y recrea todos los logros y los desbloqueos (`logros_desbloqueados`). Úsalo solo en desarrollo o cuando hagas un reset intencionado de los logros.

**Orden correcto al instalar desde cero:**

```
1. database/schema.sql    →  crea todas las tablas (schema actualizado, no necesita migrate)
2. fix_logros.php         →  carga el set completo de logros (~95 logros)
```

Si ya tienes la BD creada con un schema antiguo, ejecuta primero `database/migrate.php` para añadir las columnas que faltan sin perder datos.

---

## Estructura del proyecto

```
Leagueofarena/
├── config/
│   ├── config.php          → API Key, credenciales BD, regiones (NO subir a Git)
│   └── db.php              → Función getDB() para PDO
├── database/
│   ├── schema.sql          → Crear BD desde cero
│   └── migrate.php         → Añadir columnas nuevas sin perder datos
├── includes/
│   ├── RiotAPI.php         → Wrapper de la Riot API y Data Dragon
│   ├── LogrosManager.php   → Cálculo y desbloqueo de logros
│   ├── helpers.php         → Funciones auxiliares (URLs, formato…)
│   ├── header.php          → <head>, header sticky, menú responsive
│   └── footer.php          → Footer + script app.js
├── ajax/
│   ├── sync_matches.php    → Fetch de partidas desde Riot API → BD
│   ├── toggle_campeon.php  → Marcar/desmarcar campeón manualmente
│   └── logout.php          → Cierre de sesión
├── assets/
│   ├── css/style.css       → Tema oscuro completo + responsive
│   └── js/app.js           → Toast, sync, toggle campeón, títulos
├── cache/                  → Caché de Data Dragon (auto-generada, no subir)
├── index.php               → Búsqueda por Riot ID
├── perfil.php              → Estadísticas y historial del jugador
├── campeones.php           → Grid de campeones con filtros por clase
├── logros.php              → Página de logros con progreso
├── ranking.php             → Ranking de la comunidad
├── reclamar.php            → Autenticación por PIN
├── admin.php               → Panel de administración
└── fix_logros.php          → Recarga completa de logros
```

### Flujo de datos

```
index.php
  → Riot API: getAccountByRiotId() → PUUID
  → BD: guarda/actualiza invocador
  → redirige a perfil.php

perfil.php
  → Lee stats desde BD
  → Botón "Actualizar" → ajax/sync_matches.php
      → Riot API: últimas 20 partidas (queue=1700)
      → Guarda en partidas_arena
      → Si posicion=1 → actualiza campeones_ganados
      → LogrosManager::verificarYDesbloquear()

campeones.php
  → Data Dragon: todos los campeones (caché 24h en cache/)
  → BD: campeones_ganados del jugador
  → Click en campeón → ajax/toggle_campeon.php
      → marcado_manual=1, vuelve a verificar logros
```

---

## Subir a producción

El archivo `config/config.php` detecta automáticamente si está en producción:

```php
$isProduction = ($_SERVER['HTTP_HOST'] ?? '') !== 'localhost';
```

Edita el bloque `if ($isProduction)` con los datos de tu hosting. Los archivos a subir por FTP son todos los del repo excepto `cache/` (se genera sola).

---

## Cómo compartir con tu comunidad

1. **Sube la app a un hosting gratuito** como [InfinityFree](https://infinityfree.net) — tiene PHP + MySQL y es gratis.
2. **Comparte el enlace** en tu grupo de Discord o WhatsApp: *"Entra, busca tu nombre y verás tus victorias en Arena. Si quieres marcar campeones, reclama tu cuenta con un PIN."*
3. El primero de cada cuenta que pulse "Acceder" y establezca un PIN la reclama para sí.

---

## Contribuir

1. Haz fork del repositorio
2. Crea una rama: `git checkout -b feature/mi-mejora`
3. Commitea tus cambios: `git commit -m "Añade X"`
4. Abre un Pull Request

Ideas de mejoras bienvenidas: nuevos logros, soporte para más modos de juego, mejoras de UI, sistema de notificaciones, traducciones a otros idiomas, etc.

---

*League of Arena no está respaldado por Riot Games. Datos obtenidos mediante la [Riot Games API](https://developer.riotgames.com).*

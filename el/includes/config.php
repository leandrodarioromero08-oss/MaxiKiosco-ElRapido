<?php
/* ===== CONFIG.PHP - EL RÁPIDO ===== */

// ── Configuración local (opcional) ────────────────────────
// Si existe includes/config.local.php, se carga PRIMERO y puede definir
// ENV, DB_HOST, DB_NAME, DB_USER y DB_PASS sin tocar este archivo.
// Así las credenciales reales del servidor no quedan en el código.
// (Copiá config.local.example.php como config.local.php para usarlo.)
if (file_exists(__DIR__ . '/config.local.php')) {
    require_once __DIR__ . '/config.local.php';
}

// ── Entorno ───────────────────────────────────────────────
// 'development' muestra errores en pantalla; 'production' los oculta.
// Por defecto: development SOLO si se entra desde localhost; en cualquier
// otro dominio queda en production automáticamente.
if (!defined('ENV')) {
    // SERVER_NAME puede venir del encabezado "Host" (lo controla quien hace el pedido), así que
    // por sí solo NO alcanza: alguien de internet podría mandar "Host: localhost" y activar el modo
    // desarrollo (errores en pantalla y enlace de recuperación de contraseña visible).
    // Por eso se exige además que la conexión venga realmente del propio equipo (REMOTE_ADDR
    // local) y que no pase por un proxy (sin X-Forwarded-For / Forwarded).
    $hostActual  = strtolower((string) ($_SERVER['SERVER_NAME'] ?? 'localhost'));
    $esCli       = PHP_SAPI === 'cli';
    $ipCliente   = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
    $esLocal     = $esCli || in_array($ipCliente, ['127.0.0.1', '::1'], true);
    $conProxy    = !empty($_SERVER['HTTP_X_FORWARDED_FOR']) || !empty($_SERVER['HTTP_FORWARDED']);
    $hostLocal   = in_array($hostActual, ['localhost', '127.0.0.1', '::1'], true);
    define('ENV', ($esCli || ($hostLocal && $esLocal && !$conProxy)) ? 'development' : 'production');
}

// ── Base de datos ─────────────────────────────────────────
if (!defined('DB_HOST')) define('DB_HOST',    'localhost');
if (!defined('DB_NAME')) define('DB_NAME',    'el_rapido');
if (!defined('DB_USER')) define('DB_USER',    'root');
if (!defined('DB_PASS')) define('DB_PASS',    '');

// ── Sitio ─────────────────────────────────────────────────
define('SITE_NAME',  'El Rápido');
if (!defined('SITE_URL')) define('SITE_URL', 'http://localhost/el-rapido'); // en el servidor real: definilo en config.local.php (https://tudominio.com)
define('SITE_EMAIL', 'contacto@elrapido.com');

// ── Delivery ──────────────────────────────────────────────
// Recargo fijo de envío en pesos. ÚNICO lugar donde se define: lo usan el checkout
// (carrito.php), el texto de la opción "Delivery" y el cálculo en pantalla.
if (!defined('RECARGO_DELIVERY')) define('RECARGO_DELIVERY', 500);

// ── WhatsApp ──────────────────────────────────────────────
define('WP_NUMBER',  '5493XXXXXXXXX'); // Reemplazar con número real
define('WP_MSG_DEFAULT', '¡Hola! Quiero hacer un pedido en El Rápido 🛒');
define('WP_MSG_TRABAJO', 'Hola, quiero dejarles mi CV para trabajar en El Rápido.');

// ── Redes sociales ────────────────────────────────────────
define('INSTAGRAM_URL', 'https://instagram.com/elrapido'); // Reemplazar con la cuenta real
define('FACEBOOK_URL',  'https://facebook.com/elrapido');  // Reemplazar con la página real

// ── Negocio ───────────────────────────────────────────────
define('NEGOCIO_NOMBRE',    'Maxi Kiosco El Rápido');
define('NEGOCIO_DIRECCION', 'Calle 25 de Mayo 456'); // SIN la ciudad: las páginas ya agregan "Monte Caseros, Corrientes"
define('NEGOCIO_HORARIO',   '08:00 a 01:00 hs');
define('NEGOCIO_TELEFONO',  '11 1234 5678');
define('NEGOCIO_LAT',       '-30.2506');   // Coordenadas aprox. de Monte Caseros
define('NEGOCIO_LNG',       '-57.6426');
define('NEGOCIO_MAPS_URL',  'https://www.google.com/maps/search/?api=1&query=' . urlencode(NEGOCIO_DIRECCION . ', Monte Caseros, Corrientes, Argentina'));

// ── Sesión ────────────────────────────────────────────────
define('SESSION_NAME',     'elrapido_sess');
define('SESSION_LIFETIME', 3600 * 24 * 7); // 7 días

// ── Rutas ─────────────────────────────────────────────────
define('ROOT_PATH',     dirname(__DIR__));
define('UPLOADS_PATH',  ROOT_PATH . '/assets/img/productos');

// ── Errores ───────────────────────────────────────────────
// En producción NO se muestran en pantalla, pero SÍ se registran en el log de PHP
// (antes error_reporting(0) los apagaba del todo y un fallo de base de datos
// pasaba inadvertido).
error_reporting(E_ALL);
ini_set('log_errors', 1);
if (ENV === 'development') {
    ini_set('display_errors', 1);
} else {
    ini_set('display_errors', 0);
}

// ── Red de seguridad global (Paso 6) ──────────────────────
// Búfer de salida + manejadores de excepciones/errores fatales + página de error limpia
// (ver includes/errores.php). Va antes de iniciar la sesión y de cualquier consulta.
require_once __DIR__ . '/errores.php';
registrarManejadoresDeError();

// ── Zona horaria ──────────────────────────────────────────
date_default_timezone_set('America/Argentina/Buenos_Aires');
// Misma zona para la sesión MySQL (se aplica en includes/db.php): Argentina no usa horario de verano.
define('DB_TIMEZONE', '-03:00');

// ── Iniciar sesión ────────────────────────────────────────
if (session_status() === PHP_SESSION_NONE) {
    // Las sesiones se guardan FUERA de la carpeta pública del sitio (en la carpeta
    // temporal del sistema). Si no se puede escribir ahí, se usa storage/sessions,
    // que queda protegida con storage/.htaccess (se crea sola si falta).
    $sessionPath = null;
    $candidatos = [
        rtrim(str_replace('\\', '/', sys_get_temp_dir()), '/') . '/el-rapido-sessions',
        ROOT_PATH . '/storage/sessions',
    ];
    foreach ($candidatos as $candidato) {
        if (!is_dir($candidato)) {
            @mkdir($candidato, 0700, true);
        }
        if (is_dir($candidato) && is_writable($candidato)) {
            $sessionPath = $candidato;
            break;
        }
    }
    if ($sessionPath !== null) {
        session_save_path($sessionPath);
        if ($sessionPath === ROOT_PATH . '/storage/sessions' && !file_exists(ROOT_PATH . '/storage/.htaccess')) {
            @file_put_contents(
                ROOT_PATH . '/storage/.htaccess',
                "<IfModule mod_authz_core.c>\n  Require all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\n  Order allow,deny\n  Deny from all\n</IfModule>\n"
            );
        }
    }

    // Limpieza automática: le decimos a PHP que borre solo los archivos
    // de sesión más viejos que SESSION_LIFETIME, y que chequee esto en
    // cada request con una probabilidad de 1% (evita que se acumulen
    // sin que haya que correr nada manual).
    ini_set('session.gc_maxlifetime', SESSION_LIFETIME);
    ini_set('session.gc_probability', 1);
    ini_set('session.gc_divisor', 100);

    session_name(SESSION_NAME);
    session_start([
        'cookie_lifetime' => SESSION_LIFETIME,
        'cookie_httponly' => true,
        'cookie_samesite' => 'Lax',
        'cookie_secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'use_strict_mode' => true,
    ]);
}

// ── Seguridad compartida (CSRF, cabeceras, límite de login) ──
require_once __DIR__ . '/seguridad.php';

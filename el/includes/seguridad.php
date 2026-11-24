<?php
/**
 * SEGURIDAD COMPARTIDA (sitio público + panel admin)
 * ------------------------------------------------------------
 * Se carga automáticamente desde includes/config.php, así que
 * cualquier página que incluya config.php (directa o por db.php)
 * ya tiene disponibles estas funciones.
 *
 *  - Cabeceras HTTP de seguridad.
 *  - Protección CSRF: csrfCampo() en cada formulario POST y
 *    csrfExigir() al principio de cada script que procesa un POST.
 *  - Límite de intentos de login (fuerza bruta).
 *  - Validación de rutas locales para redirecciones ("volver").
 */

/* ═══════════════════════════════════════════════════════════
   CABECERAS HTTP
   ═══════════════════════════════════════════════════════════ */
if (!headers_sent() && PHP_SAPI !== 'cli') {
  header('X-Content-Type-Options: nosniff');
  header('X-Frame-Options: SAMEORIGIN');
  header('Referrer-Policy: strict-origin-when-cross-origin');
}

/* ═══════════════════════════════════════════════════════════
   CSRF
   ═══════════════════════════════════════════════════════════ */

/** Token CSRF de esta sesión (se crea la primera vez que se pide) */
function csrfToken(): string {
  if (empty($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
  }
  return $_SESSION['csrf_token'];
}

/** <input hidden> listo para pegar dentro de cualquier <form method="POST"> */
function csrfCampo(): string {
  return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrfToken(), ENT_QUOTES) . '">';
}

/** true si el POST actual trae un token válido */
function csrfValido(): bool {
  $recibido = $_POST['csrf_token'] ?? '';
  return is_string($recibido)
    && $recibido !== ''
    && isset($_SESSION['csrf_token'])
    && is_string($_SESSION['csrf_token'])
    && hash_equals($_SESSION['csrf_token'], $recibido);
}

/**
 * Corta con 403 si la petición es POST y el token no es válido.
 * Se llama UNA vez, al principio de cada script que procesa formularios.
 */
function csrfExigir(): void {
  if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST' || csrfValido()) {
    return;
  }
  http_response_code(403);
  header('Content-Type: text/html; charset=utf-8');
  exit('<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><meta name="robots" content="noindex"><title>Solicitud no válida</title></head>'
     . '<body style="font-family:sans-serif;max-width:480px;margin:15vh auto;padding:0 20px;">'
     . '<h1 style="font-size:20px;">No pudimos validar tu solicitud</h1>'
     . '<p>Tu sesión pudo haber expirado o el formulario es inválido. '
     . 'Volvé a la página anterior, recargala e intentá de nuevo.</p></body></html>');
}

/* ═══════════════════════════════════════════════════════════
   ENTRADAS
   ═══════════════════════════════════════════════════════════ */

/**
 * Valor de $_POST[$clave] como texto recortado. Si falta, o si alguien
 * manda un array (campo[]=x) en vez de un texto, devuelve '' en lugar de
 * provocar un error de PHP.
 */
function postTexto(string $clave): string {
  $v = $_POST[$clave] ?? '';
  return is_string($v) ? trim($v) : '';
}

/**
 * Valor de $_GET[$clave] como texto recortado y con largo acotado. Si falta, está
 * vacío, o si alguien manda un array (?cat[]=x) en vez de un texto, devuelve
 * $porDefecto en lugar de provocar un TypeError (es el equivalente de postTexto()
 * para la URL).
 */
function getTexto(string $clave, string $porDefecto = '', int $max = 200): string {
  $v = $_GET[$clave] ?? null;
  if (!is_string($v)) {
    return $porDefecto;
  }
  $v = trim($v);
  return $v === '' ? $porDefecto : mb_substr($v, 0, $max);
}

/** Cantidad máxima de unidades de un mismo producto por línea (carrito / listas) */
const CANTIDAD_MAXIMA_POR_LINEA = 99;

/** Fuerza una cantidad al rango 0..CANTIDAD_MAXIMA_POR_LINEA */
function limitarCantidad(int $cantidad): int {
  return max(0, min(CANTIDAD_MAXIMA_POR_LINEA, $cantidad));
}

/* ═══════════════════════════════════════════════════════════
   REDIRECCIONES SEGURAS
   ═══════════════════════════════════════════════════════════ */

/**
 * true solo para rutas locales simples del propio sitio:
 *   "index.php", "productos.php?cat=bebidas&pagina=2", "mi-cuenta.php?seccion=pedidos"
 * Rechaza URLs absolutas (http://, HTTP://, //, /\, javascript:), rutas con
 * "/" o "\" iniciales, saltos de línea y cualquier cosa que no sea
 * "archivo.php" + query string simple.
 */
function esRutaLocalSegura($ruta): bool {
  if (!is_string($ruta) || $ruta === '' || strlen($ruta) > 300) {
    return false;
  }
  return preg_match('#^[A-Za-z0-9_\-]+\.php(\?[A-Za-z0-9_\-=&%.\[\]+,]*)?\z#', $ruta) === 1;
}

/* ═══════════════════════════════════════════════════════════
   LÍMITE DE INTENTOS DE LOGIN
   Requiere la tabla intentos_login (database/el_rapido.sql).
   Si la tabla todavía no existe, NO se rompe el login: simplemente
   no se aplica el límite (y se deja el aviso en el log de PHP).
   ═══════════════════════════════════════════════════════════ */
const LOGIN_MAX_POR_EMAIL  = 5;   // intentos fallidos por correo...
const LOGIN_MAX_POR_IP     = 15;  // ...y por IP...
const LOGIN_VENTANA_MIN    = 15;  // ...dentro de esta ventana (minutos)

function ipCliente(): string {
  // Solo REMOTE_ADDR: X-Forwarded-For lo puede falsificar cualquiera.
  return substr((string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'), 0, 45);
}

/** true si este correo o esta IP superaron los intentos fallidos permitidos */
function loginBloqueado(PDO $pdo, string $ambito, string $email): bool {
  try {
    $stmt = $pdo->prepare('
      SELECT
        SUM(email = :email) AS por_email,
        COUNT(*)            AS por_ip
      FROM intentos_login
      WHERE ambito = :ambito AND ip = :ip
        AND creado_en > (NOW() - INTERVAL ' . LOGIN_VENTANA_MIN . ' MINUTE)
    ');
    $stmt->execute([':email' => strtolower($email), ':ambito' => $ambito, ':ip' => ipCliente()]);
    $fila = $stmt->fetch();
    if ((int) ($fila['por_ip'] ?? 0) >= LOGIN_MAX_POR_IP) {
      return true;
    }

    // El conteo por correo cuenta intentos desde CUALQUIER IP (protege la cuenta
    // aunque el atacante rote de IP).
    $stmt = $pdo->prepare('
      SELECT COUNT(*) FROM intentos_login
      WHERE ambito = :ambito AND email = :email
        AND creado_en > (NOW() - INTERVAL ' . LOGIN_VENTANA_MIN . ' MINUTE)
    ');
    $stmt->execute([':ambito' => $ambito, ':email' => strtolower($email)]);
    return (int) $stmt->fetchColumn() >= LOGIN_MAX_POR_EMAIL;
  } catch (Throwable $e) {
    error_log('[seguridad] intentos_login no disponible: ' . $e->getMessage());
    return false;
  }
}

function registrarLoginFallido(PDO $pdo, string $ambito, string $email): void {
  try {
    $stmt = $pdo->prepare('INSERT INTO intentos_login (ambito, ip, email) VALUES (:ambito, :ip, :email)');
    $stmt->execute([':ambito' => $ambito, ':ip' => ipCliente(), ':email' => strtolower(substr($email, 0, 150))]);
    if (random_int(1, 50) === 1) { // limpieza ocasional de registros viejos
      $pdo->exec('DELETE FROM intentos_login WHERE creado_en < (NOW() - INTERVAL 1 DAY)');
    }
  } catch (Throwable $e) {
    error_log('[seguridad] no se pudo registrar el intento: ' . $e->getMessage());
  }
}

function limpiarLoginFallidos(PDO $pdo, string $ambito, string $email): void {
  try {
    $stmt = $pdo->prepare('DELETE FROM intentos_login WHERE ambito = :ambito AND email = :email');
    $stmt->execute([':ambito' => $ambito, ':email' => strtolower($email)]);
  } catch (Throwable $e) {
    error_log('[seguridad] no se pudo limpiar intentos: ' . $e->getMessage());
  }
}

/**
 * Compara una contraseña con un hash guardado, tardando lo mismo exista o no
 * el usuario. Si no hay hash (el correo no está registrado) se calcula un
 * password_hash() descartable: cuesta lo mismo que un password_verify() con
 * el costo actual de PASSWORD_DEFAULT, así el tiempo de respuesta no delata
 * qué correos existen (un hash falso fijo se desfasaría si cambia el costo).
 */
function verificarPassword(string $password, ?string $hash): bool {
  if ($hash === null || $hash === '') {
    password_hash($password, PASSWORD_DEFAULT);
    return false;
  }
  return password_verify($password, $hash);
}

/* ═══════════════════════════════════════════════════════════
   LÍMITE POR IP PARA OTRAS ACCIONES (registro, recuperar clave)
   Usa la misma tabla intentos_login con otros "ámbitos"
   (el ENUM está definido en database/el_rapido.sql).
   Si la tabla o el ámbito no existen, NO se bloquea nada.
   ═══════════════════════════════════════════════════════════ */

/** true si esta IP ya hizo $max acciones del ámbito en los últimos $ventanaMin minutos */
function ipLimiteExcedido(PDO $pdo, string $ambito, int $max, int $ventanaMin): bool {
  try {
    $stmt = $pdo->prepare('
      SELECT COUNT(*) FROM intentos_login
      WHERE ambito = :ambito AND ip = :ip
        AND creado_en > (NOW() - INTERVAL ' . (int) $ventanaMin . ' MINUTE)
    ');
    $stmt->execute([':ambito' => $ambito, ':ip' => ipCliente()]);
    return (int) $stmt->fetchColumn() >= $max;
  } catch (Throwable $e) {
    error_log('[seguridad] límite por IP no disponible: ' . $e->getMessage());
    return false;
  }
}

function ipRegistrarAccion(PDO $pdo, string $ambito): void {
  try {
    $stmt = $pdo->prepare("INSERT INTO intentos_login (ambito, ip, email) VALUES (:ambito, :ip, '')");
    $stmt->execute([':ambito' => $ambito, ':ip' => ipCliente()]);
  } catch (Throwable $e) {
    error_log('[seguridad] no se pudo registrar la acción: ' . $e->getMessage());
  }
}

/* ═══════════════════════════════════════════════════════════
   CONTRASEÑAS
   ═══════════════════════════════════════════════════════════ */

const PASSWORD_MIN_CLIENTE = 10;

/**
 * Valor de $_POST[$clave] SIN recortar espacios. Las contraseñas no deben
 * pasar por trim(): si se guardan de una forma y se comparan de otra, la
 * persona queda sin poder entrar.
 */
function postRaw(string $clave): string {
  $v = $_POST[$clave] ?? '';
  return is_string($v) ? $v : '';
}

/**
 * Valida una contraseña NUEVA de cliente. Devuelve el mensaje de error, o
 * null si es aceptable.
 */
function validarPasswordNueva(string $password, string $email = ''): ?string {
  if (strlen($password) < PASSWORD_MIN_CLIENTE) {
    return 'La contraseña debe tener al menos ' . PASSWORD_MIN_CLIENTE . ' caracteres.';
  }
  if (strlen($password) > 72) {
    return 'La contraseña es demasiado larga (máximo 72 caracteres).';
  }
  $comun = ['1234567890', '0123456789', '12345678910', '1234567891', 'password', 'password1', 'password12',
            'password123', 'contraseña', 'contrasena', 'contrasena1', 'contraseña1', 'qwertyuiop', 'qwerty1234',
            'qwerty12345', 'abcdefghij', 'abc1234567', 'iloveyou12', 'elrapido', 'elrapido123', 'elrapido1234',
            '1q2w3e4r5t', 'asdfghjkl1', 'asdfghjklñ', '0000000000', '1111111111', 'maxikiosco'];
  $minus = mb_strtolower($password);
  if (in_array($minus, $comun, true) || count(array_unique(str_split($minus))) < 4) {
    return 'Esa contraseña es demasiado fácil de adivinar. Elegí otra.';
  }
  if ($email !== '' && $minus === mb_strtolower($email)) {
    return 'La contraseña no puede ser igual a tu correo electrónico.';
  }
  return null;
}

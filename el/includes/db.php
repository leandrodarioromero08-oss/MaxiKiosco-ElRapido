<?php
/**
 * Conexión a la base de datos con PDO.
 * Valores por defecto de XAMPP / WAMP / Laragon:
 *   host: localhost
 *   usuario: root
 *   contraseña: '' (vacía)
 * Si usás Laragon con MySQL y le pusiste contraseña, o cambiaste
 * el puerto, ajustá las constantes de abajo.
 */

require_once __DIR__ . '/config.php';

function obtenerConexion(): PDO {
  static $pdo = null;

  if ($pdo === null) {
    $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
    try {
      $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
        // La sesión MySQL usa la MISMA zona horaria que PHP (Argentina, UTC-3) y el modo
        // estricto: así CURDATE()/NOW() coinciden con date() de PHP sin importar cómo esté
        // configurado el servidor, y MySQL rechaza datos que no entran en vez de truncarlos.
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET time_zone = '" . DB_TIMEZONE . "', "
          . "sql_mode = 'STRICT_ALL_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION'",
      ]);
    } catch (PDOException $e) {
      // El detalle técnico (host, usuario, etc.) va solo al log del servidor.
      error_log('[db] No se pudo conectar: ' . $e->getMessage());
      if (!headers_sent()) {
        header('Retry-After: 60');
      }
      // Solo en desarrollo (localhost) se muestra una pista para el programador.
      $pista = ENV === 'development'
        ? 'No se pudo conectar a la base de datos. Revisá que MySQL esté corriendo en XAMPP/Laragon '
          . 'y que la base "el_rapido" exista. Detalle técnico: ' . $e->getMessage()
        : null;
      responderError(
        503,
        'Estamos con problemas técnicos',
        'El sitio no está disponible en este momento. Intentá de nuevo en unos minutos.',
        $pista
      );
      exit;
    }
  }

  return $pdo;
}

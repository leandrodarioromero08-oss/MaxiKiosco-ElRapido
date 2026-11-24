<?php
/**
 * AUTENTICACIÓN DEL PANEL DE ADMINISTRACIÓN
 * ------------------------------------------------------------
 * Antes de este archivo, admin/ no pedía ninguna contraseña: con
 * conocer la URL alcanzaba para editar productos, pedidos y promos.
 *
 * Guarda al admin logueado en $_SESSION['admin'] (una clave
 * distinta de $_SESSION['usuario'], que es el cliente logueado en
 * el sitio público, así una sesión no pisa a la otra aunque se
 * abran en el mismo navegador).
 *
 * Requiere conexion.php (que ya arranca la sesión PHP a través de
 * includes/config.php), así no hace falta llamar session_start()
 * de nuevo acá.
 */

require_once __DIR__ . '/conexion.php';

/** Segundos sin actividad después de los cuales se cierra la sesión del admin (2 horas) */
const ADMIN_INACTIVIDAD_MAX = 7200;

/** Guarda en sesión los datos mínimos del admin logueado */
function iniciarSesionAdmin(array $admin): void {
  // Nuevo id de sesión al loguearse: evita la "fijación de sesión".
  session_regenerate_id(true);
  unset($_SESSION['csrf_token']);
  $_SESSION['admin'] = [
    'id'     => $admin['id'],
    'nombre' => $admin['nombre'],
    'email'  => $admin['email'],
    'ultimo_acceso' => time(),
    'verificado_en' => time(),
  ];
}

/** true si hay un admin logueado en esta sesión */
function adminLogueado(): bool {
  return isset($_SESSION['admin']);
}

/** Datos del admin logueado (id/nombre/email), o null si no hay ninguno */
function adminActual(): ?array {
  return $_SESSION['admin'] ?? null;
}

/** Borra al admin de la sesión (logout) */
function cerrarSesionAdmin(): void {
  unset($_SESSION['admin'], $_SESSION['csrf_token']);
  session_regenerate_id(true);
}

/**
 * true si hay un admin logueado Y su sesión no venció por inactividad, pero SIN renovar
 * "ultimo_acceso". Es para los endpoints que consulta el navegador solo (como el aviso de
 * pedidos nuevos, que pregunta cada ~20 s): si usaran requerirAdmin(), cada consulta contaría
 * como actividad y la sesión nunca se cerraría por inactividad.
 */
function adminSesionVigenteSinRenovar(): bool {
  if (!adminLogueado()) {
    return false;
  }
  $ultimo = (int) ($_SESSION['admin']['ultimo_acceso'] ?? 0);
  return $ultimo > 0 && (time() - $ultimo) <= ADMIN_INACTIVIDAD_MAX;
}

/**
 * Corta la ejecución si no hay un admin logueado. Se llama como
 * primera línea de CADA archivo de admin/Seccion/ (antes de tocar
 * la base de datos o imprimir cualquier HTML).
 *
 * $esApi: true para los endpoints que responden JSON (por ejemplo
 * promociones_buscar_productos.php, llamado por fetch() y no por
 * navegación directa). En ese caso, en vez de redirigir a
 * login.php, devuelve un 401 en JSON para que el fetch() del
 * navegador lo pueda manejar.
 */
function requerirAdmin(bool $esApi = false): void {
  if (adminLogueado()) {
    $ahora = time();

    if ($ahora - (int) ($_SESSION['admin']['ultimo_acceso'] ?? $ahora) > ADMIN_INACTIVIDAD_MAX) {
      cerrarSesionAdmin(); // sesión inactiva demasiado tiempo: sigue más abajo hacia el login
    } else {
      $_SESSION['admin']['ultimo_acceso'] = $ahora;

      // Cada 60 s confirmamos que el administrador siga existiendo en la base.
      if ((int) ($_SESSION['admin']['verificado_en'] ?? 0) <= $ahora - 60) {
        $_SESSION['admin']['verificado_en'] = $ahora;
        try {
          $stmt = obtenerConexion()->prepare('SELECT 1 FROM administradores WHERE id = :id');
          $stmt->execute([':id' => (int) ($_SESSION['admin']['id'] ?? 0)]);
          if (!$stmt->fetchColumn()) {
            cerrarSesionAdmin();
          }
        } catch (Throwable $e) {
          error_log('[el-rapido] revalidación admin: ' . $e->getMessage());
        }
      }

      if (adminLogueado()) {
        return;
      }
    }
  }

  if ($esApi) {
    http_response_code(401);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'No autorizado. Iniciá sesión en el panel de administración.']);
    exit;
  }

  // Guardamos a dónde quería ir (ej: "productos.php?cat=3") para
  // volver ahí después de loguearse, en vez de mandarlo siempre a Inicio.
  $actual = $_SERVER['REQUEST_URI'] ?? '';
  $pos    = strpos($actual, '/Seccion/');
  $volver = $pos !== false ? substr($actual, $pos + 9) : ''; // +9 = largo de "/Seccion/"

  header('Location: ../login.php' . ($volver !== '' ? ('?volver=' . urlencode($volver)) : ''));
  exit;
}

<?php
/**
 * BARRA DE ADMINISTRACIÓN EN EL SITIO PÚBLICO
 * ------------------------------------------------------------
 * Si quien navega el sitio tiene la sesión del panel abierta ($_SESSION['admin']),
 * header.php muestra arriba una franja con accesos rápidos al panel.
 * Los clientes nunca la ven. Es solo una comodidad: la protección real sigue
 * estando en requerirAdmin(), que cada archivo de admin/Seccion/ llama al empezar.
 */

/** Mismo límite de inactividad que usa el panel (admin/Config/auth.php) */
const ADMIN_BARRA_INACTIVIDAD_MAX = 7200;

/**
 * Devuelve los datos para dibujar la barra, o null si no hay que mostrarla
 * (sin sesión de admin, o sesión inactiva hace más de 2 horas).
 *
 * @return array{nombre:string,pendientes:int}|null
 */
function barraAdminDatos(PDO $pdo): ?array {
  $admin = $_SESSION['admin'] ?? null;
  if (!is_array($admin)) {
    return null;
  }
  if (time() - (int) ($admin['ultimo_acceso'] ?? 0) > ADMIN_BARRA_INACTIVIDAD_MAX) {
    return null;
  }

  $pendientes = 0;
  try {
    $pendientes = (int) $pdo->query("SELECT COUNT(*) FROM pedidos WHERE estado = 'pendiente'")->fetchColumn();
  } catch (Throwable $e) {
    error_log('[el-rapido] barra admin: ' . $e->getMessage());
  }

  return [
    'nombre'     => (string) ($admin['nombre'] ?? 'Admin'),
    'pendientes' => $pendientes,
  ];
}

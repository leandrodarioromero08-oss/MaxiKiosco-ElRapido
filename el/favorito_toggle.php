<?php
/**
 * Marca o desmarca un producto como favorito. Lo llama productos.js por fetch
 * cuando se toca el corazón ♡ de una tarjeta. Responde siempre JSON.
 *
 * POST: producto_id, favorito (1 = marcar, 0 = quitar), csrf_token
 */
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/funciones.php';
require_once 'includes/favoritos.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function responderJsonFavorito(int $codigo, array $datos): void {
  http_response_code($codigo);
  echo json_encode($datos);
  exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
  header('Allow: POST');
  responderJsonFavorito(405, ['ok' => false, 'error' => 'Método no permitido.']);
}
if (!csrfValido()) {
  responderJsonFavorito(403, ['ok' => false, 'error' => 'Tu sesión expiró. Recargá la página e intentá de nuevo.']);
}
if (!usuarioLogueado()) {
  responderJsonFavorito(401, ['ok' => false, 'login' => true, 'error' => 'Iniciá sesión para guardar favoritos.']);
}

$productoId = (int) ($_POST['producto_id'] ?? 0);
$activo     = ($_POST['favorito'] ?? '1') === '1';

try {
  $ok = establecerFavorito(obtenerConexion(), (int) $_SESSION['usuario']['id'], $productoId, $activo);
} catch (Throwable $e) {
  error_log('[el-rapido] favorito_toggle: ' . $e->getMessage());
  responderJsonFavorito(500, ['ok' => false, 'error' => 'No pudimos guardar el favorito. Intentá de nuevo.']);
}

if (!$ok) {
  responderJsonFavorito(404, ['ok' => false, 'error' => 'Ese producto ya no está disponible.']);
}
responderJsonFavorito(200, ['ok' => true, 'favorito' => $activo, 'producto_id' => $productoId]);

<?php
// ════════════════════════════════════════════════════════════════
//  notificaciones_api.php — AVISOS AL CLIENTE (JSON)
// ════════════════════════════════════════════════════════════════
//  Lo usa assets/js/notificaciones.js (solo si el cliente tiene sesión iniciada).
//
//  GET  (sin parámetros)       → { ok, logueado, items:[{id,pedido_id,tipo,titulo,mensaje}] }
//                                 avisos todavía no mostrados (máx. 5, de los últimos 2 días)
//  POST accion=vistas          → marca como vistos los avisos de ids[] (solo los del propio cliente)
//        ids[] · csrf_token
//
//  Los avisos los crea admin/Config/funciones.php (crearNotificacionPedido) cuando el admin
//  cambia el estado de un pedido. Si falta la tabla (migración 006) responde vacío, sin error.
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/funciones.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function responderNotif(int $codigo, array $datos): void {
  http_response_code($codigo);
  echo json_encode($datos, JSON_UNESCAPED_UNICODE);
  exit;
}

$metodo = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($metodo !== 'GET' && $metodo !== 'POST') {
  header('Allow: GET, POST');
  responderNotif(405, ['ok' => false, 'error' => 'Método no permitido.']);
}

if (!usuarioLogueado()) {
  responderNotif(200, ['ok' => true, 'logueado' => false, 'items' => []]);
}
$usuarioId = (int) $_SESSION['usuario']['id'];
$csrfOk    = $metodo === 'POST' ? csrfValido() : true;

// Se suelta el bloqueo de la sesión YA: el navegador consulta cada ~20 s y, con la sesión
// bloqueada, frenaría otras páginas que el cliente tenga abiertas.
session_write_close();

if (!$csrfOk) {
  responderNotif(403, ['ok' => false, 'error' => 'Tu sesión expiró. Recargá la página.']);
}

try {
  $pdo = obtenerConexion();

  if ($metodo === 'POST') {
    $ids = $_POST['ids'] ?? [];
    $ids = is_array($ids) ? array_slice(array_values(array_unique(array_filter(array_map('intval', $ids), fn($i) => $i > 0))), 0, 20) : [];
    if ($ids) {
      $marcas = implode(',', array_fill(0, count($ids), '?'));
      $stmt = $pdo->prepare("UPDATE notificaciones SET vista = 1 WHERE usuario_id = ? AND id IN ($marcas)");
      $stmt->execute(array_merge([$usuarioId], $ids));
    }
    responderNotif(200, ['ok' => true, 'marcadas' => count($ids)]);
  }

  // Limpieza ocasional: avisos ya vistos con más de 30 días (1 de cada ~50 consultas)
  if (random_int(1, 50) === 1) {
    $pdo->exec('DELETE FROM notificaciones WHERE vista = 1 AND creada_en < (NOW() - INTERVAL 30 DAY)');
  }

  $stmt = $pdo->prepare(
    'SELECT id, pedido_id, tipo, titulo, mensaje
       FROM notificaciones
      WHERE usuario_id = :u AND vista = 0 AND creada_en >= (NOW() - INTERVAL 2 DAY)
      ORDER BY id ASC
      LIMIT 5'
  );
  $stmt->execute([':u' => $usuarioId]);
  $items = array_map(fn($f) => [
    'id'        => (int) $f['id'],
    'pedido_id' => $f['pedido_id'] !== null ? (int) $f['pedido_id'] : null,
    'tipo'      => (string) $f['tipo'],
    'titulo'    => (string) $f['titulo'],
    'mensaje'   => (string) $f['mensaje'],
  ], $stmt->fetchAll());

  responderNotif(200, ['ok' => true, 'logueado' => true, 'items' => $items]);
} catch (Throwable $e) {
  // Típico: falta importar database/migracion_006_notificaciones.sql. No se rompe nada en la página.
  error_log('[el-rapido] notificaciones_api: ' . $e->getMessage());
  responderNotif(200, ['ok' => true, 'logueado' => true, 'items' => []]);
}

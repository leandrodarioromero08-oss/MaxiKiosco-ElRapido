<?php
// ════════════════════════════════════════════════════════════════
//  admin/Seccion/notificaciones_api.php — AVISOS PARA EL ADMIN (JSON)
// ════════════════════════════════════════════════════════════════
//  Lo consulta admin/assets/js/notificaciones.js cada ~20 s desde cualquier página del panel.
//
//  GET  desde=-1   → { ok, ultimo_id, pendientes, nuevos:[] }   (solo fija el punto de partida)
//  GET  desde=N    → pedidos con id > N (máx. 10, del más viejo al más nuevo), más ultimo_id
//                    y la cantidad de pedidos pendientes. No hace falta una tabla nueva.
//
//  A propósito NO usa requerirAdmin(): ese renueva "ultimo_acceso" y una consulta automática
//  mantendría la sesión abierta para siempre (ver adminSesionVigenteSinRenovar en auth.php).
require_once '../Config/funciones.php';
require_once '../Config/auth.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if (!adminSesionVigenteSinRenovar()) {
  session_write_close();
  http_response_code(401);
  echo json_encode(['ok' => false, 'error' => 'Sesión vencida.']);
  exit;
}
session_write_close(); // no bloquear otras pestañas del panel mientras se consulta

try {
  $pdo   = obtenerConexion();
  $desde = isset($_GET['desde']) ? (int) $_GET['desde'] : -1;

  $ultimoId   = (int) $pdo->query('SELECT COALESCE(MAX(id), 0) FROM pedidos')->fetchColumn();
  $pendientes = (int) $pdo->query("SELECT COUNT(*) FROM pedidos WHERE estado = 'pendiente'")->fetchColumn();
  $nuevos     = [];

  if ($desde >= 0 && $ultimoId > $desde) {
    $stmt = $pdo->prepare(
      'SELECT p.id, p.total, p.tipo_entrega, u.nombre AS cliente
         FROM pedidos p
         LEFT JOIN usuarios u ON u.id = p.usuario_id
        WHERE p.id > :desde
        ORDER BY p.id ASC
        LIMIT 10'
    );
    $stmt->execute([':desde' => $desde]);
    foreach ($stmt->fetchAll() as $f) {
      $nuevos[] = [
        'id'      => (int) $f['id'],
        'cliente' => (string) ($f['cliente'] ?? 'Cliente'),
        'total'   => formatoMoneda((float) $f['total']),
        'entrega' => $f['tipo_entrega'] === 'delivery' ? 'Delivery' : 'Retiro en el local',
      ];
    }
  }

  echo json_encode(['ok' => true, 'ultimo_id' => $ultimoId, 'pendientes' => $pendientes, 'nuevos' => $nuevos], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
  error_log('[el-rapido] admin notificaciones_api: ' . $e->getMessage());
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => 'No se pudo consultar.']);
}

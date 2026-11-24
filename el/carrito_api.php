<?php
// ════════════════════════════════════════════════════════════════
//  carrito_api.php — CARRITO POR AJAX (JSON)
// ════════════════════════════════════════════════════════════════
//  Lo usa assets/js/mejoras.js para agregar/cambiar cantidades sin recargar la página
//  (botón "Agregar" del catálogo, mini-carrito y barra móvil).
//  Reutiliza las funciones de includes/carrito.php: el servidor sigue siendo el que
//  valida stock, tope por línea, precios y sesión. carrito_agregar.php queda como
//  respaldo para cuando JavaScript no está disponible.
//
//  POST: accion = agregar | cambiar | resumen
//        producto_id (agregar, cambiar) · cantidad (agregar: unidades a sumar · cambiar: cantidad final, 0 = quitar)
//        csrf_token
//  Respuesta: { ok, items:[{id,nombre,emoji,imagen,precio,cantidad,subtotal,stock,promo}], count, total, error? }
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/carrito.php';
require_once 'includes/funciones.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function responderCarrito(int $codigo, array $datos): void {
  http_response_code($codigo);
  echo json_encode($datos, JSON_UNESCAPED_UNICODE);
  exit;
}

/** Estado actual del carrito en el formato que espera mejoras.js */
function estadoCarrito(PDO $pdo): array {
  // false = calcula sin limpiar la sesión (la limpieza con avisos la hace carrito.php)
  $detalle = carritoDetalle($pdo, false);
  $items = [];
  foreach ($detalle['items'] as $i) {
    $items[] = [
      'id'       => (int) $i['id'],
      'nombre'   => $i['nombre'],
      'emoji'    => $i['emoji'] ?? '',
      'imagen'   => !empty($i['imagen']) ? $i['imagen'] : null,
      'precio'   => (float) $i['precio'],
      'cantidad' => (int) $i['cantidad'],
      'subtotal' => (float) $i['subtotal'],
      'stock'    => (int) ($i['stock'] ?? 0),
      'promo'    => !empty($i['es_promo']),  // las promos se editan en carrito.php
    ];
  }
  return ['ok' => true, 'items' => $items, 'count' => (int) $detalle['cantidad_total'], 'total' => (float) $detalle['total']];
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
  header('Allow: POST');
  responderCarrito(405, ['ok' => false, 'error' => 'Método no permitido.']);
}
if (!csrfValido()) {
  responderCarrito(403, ['ok' => false, 'error' => 'Tu sesión expiró. Recargá la página e intentá de nuevo.']);
}

$accion = (string) ($_POST['accion'] ?? 'resumen');
$pdo = obtenerConexion();

try {
  if ($accion === 'resumen') {
    responderCarrito(200, estadoCarrito($pdo));
  }
  if (!in_array($accion, ['agregar', 'cambiar'], true)) {
    responderCarrito(400, ['ok' => false, 'error' => 'Acción no válida.']);
  }
  // Misma regla que carrito_agregar.php / el catálogo: para comprar hay que tener sesión.
  if (!usuarioLogueado()) {
    responderCarrito(401, ['ok' => false, 'login' => true, 'error' => 'Iniciá sesión para agregar productos al carrito.']);
  }

  $productoId = (int) ($_POST['producto_id'] ?? 0);
  $cantidad   = (int) ($_POST['cantidad'] ?? 1);
  if ($productoId < 1) {
    responderCarrito(400, ['ok' => false, 'error' => 'Producto no válido.']);
  }

  // El stock y la existencia SIEMPRE se consultan en la base (nunca se confía en el navegador).
  $stmt = $pdo->prepare('SELECT id, nombre, stock FROM productos WHERE id = :id AND activo = 1');
  $stmt->execute([':id' => $productoId]);
  $producto = $stmt->fetch();
  if (!$producto) {
    carritoEliminar($productoId);
    responderCarrito(404, ['ok' => false, 'error' => 'Ese producto ya no está disponible.']);
  }
  $stock = (int) $producto['stock'];

  if ($accion === 'agregar') {
    if ($stock <= 0) {
      responderCarrito(409, ['ok' => false, 'error' => '"' . $producto['nombre'] . '" está sin stock.']);
    }
    $antes   = (int) ($_SESSION['carrito'][$productoId] ?? 0);
    $despues = carritoAgregar($productoId, max(1, $cantidad), $stock);
    if ($despues < min($antes + max(1, $cantidad), CANTIDAD_MAXIMA_POR_LINEA)) {
      $r = estadoCarrito($pdo);
      $r['ok'] = false;
      $r['error'] = 'Solo hay ' . $stock . ' unidades de "' . $producto['nombre'] . '" disponibles; ajustamos tu carrito a ' . $despues . '.';
      responderCarrito(409, $r);
    }
  } else { // cambiar: fija la cantidad final (0 o menos = quitar)
    carritoActualizarCantidad($productoId, max(0, $cantidad), $stock);
  }
  responderCarrito(200, estadoCarrito($pdo));
} catch (Throwable $e) {
  error_log('[el-rapido] carrito_api: ' . $e->getMessage());
  responderCarrito(500, ['ok' => false, 'error' => 'No pudimos actualizar el carrito. Intentá de nuevo.']);
}

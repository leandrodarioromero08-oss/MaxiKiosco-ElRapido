<?php
/**
 * COMPRA SEMANAL + HISTORIAL DE PEDIDOS
 * Funciones que usan mi-cuenta.php, carrito.php (para el cuadro de
 * diálogo post-compra), compra_semanal_editar.php, mi-cuenta.php
 * (acciones) y pedido_repetir.php.
 *
 * Regla de oro: en compra_semanal_items SOLO se guardan producto_id
 * y cantidad. Nunca un precio. El precio y el stock se recalculan
 * siempre desde la tabla "productos" al momento de mostrar la lista
 * o de agregarla al carrito.
 *
 * Cada usuario puede tener HASTA MAX_COMPRAS_SEMANALES listas.
 */

require_once __DIR__ . '/carrito.php';
require_once __DIR__ . '/funciones.php';

const MAX_COMPRAS_SEMANALES = 3;

/* ═══════════════════════════════════════════════════════════
   NOMBRES DE DÍA / ESTADO (para mostrar en pantalla)
   ═══════════════════════════════════════════════════════════ */

function diasSemanaValidos(): array {
  return ['lunes', 'martes', 'miercoles', 'jueves', 'viernes', 'sabado', 'domingo'];
}

function diaCompraLabel(?string $dia): string {
  $labels = [
    'lunes' => 'Lunes', 'martes' => 'Martes', 'miercoles' => 'Miércoles',
    'jueves' => 'Jueves', 'viernes' => 'Viernes', 'sabado' => 'Sábado', 'domingo' => 'Domingo',
  ];
  return $labels[$dia] ?? 'Sin definir';
}

/** Texto + clase CSS para cada estado de pedido */
function estadoPedidoInfo(string $estado): array {
  $mapa = [
    'pendiente'    => ['label' => 'Pendiente',          'clase' => 'estado-pendiente'],
    'preparando'   => ['label' => 'Preparando',         'clase' => 'estado-preparando'],
    'listo_retiro' => ['label' => 'Listo para retirar', 'clase' => 'estado-listo'],
    'en_camino'    => ['label' => 'En camino',          'clase' => 'estado-camino'],
    'entregado'    => ['label' => 'Entregado',          'clase' => 'estado-entregado'],
    'cancelado'    => ['label' => 'Cancelado',          'clase' => 'estado-cancelado'],
  ];
  return $mapa[$estado] ?? ['label' => ucfirst($estado), 'clase' => 'estado-pendiente'];
}

/** "Hace 5 días" / "Hoy" / "Ayer" a partir de una fecha de MySQL */
function tiempoRelativo(string $fechaMysql): string {
  $fecha = new DateTime($fechaMysql);
  $ahora = new DateTime();
  $dias = (int) $ahora->diff($fecha)->format('%a');

  if ($dias === 0) return 'Hoy';
  if ($dias === 1) return 'Ayer';
  return 'Hace ' . $dias . ' días';
}

/* ═══════════════════════════════════════════════════════════
   COMPRA SEMANAL: LECTURA
   ═══════════════════════════════════════════════════════════ */

/** Arma el array de items+totales de una fila de compra_semanal ya traída de la DB */
function armarDetalleCompraSemanal(PDO $pdo, array $lista): array {
  $stmtItems = $pdo->prepare('
    SELECT csi.id AS item_id, csi.cantidad, csi.producto_id,
           p.nombre, p.precio, p.descuento_pct, p.emoji, p.imagen, p.stock, p.activo
    FROM compra_semanal_items csi
    LEFT JOIN productos p ON p.id = csi.producto_id
    WHERE csi.compra_semanal_id = :id
    ORDER BY csi.id ASC
  ');
  $stmtItems->execute([':id' => $lista['id']]);
  $filas = $stmtItems->fetchAll();

  $items = [];
  foreach ($filas as $f) {
    $existe = $f['producto_id'] !== null && (int) $f['activo'] === 1;
    $items[] = [
      'item_id'     => (int) $f['item_id'],
      'producto_id' => $f['producto_id'] !== null ? (int) $f['producto_id'] : null,
      'nombre'      => $existe ? $f['nombre'] : 'Producto ya no disponible',
      'emoji'       => $existe ? $f['emoji'] : '❌',
      'imagen'      => $existe ? $f['imagen'] : null,
      'cantidad'    => (int) $f['cantidad'],
      'stock'       => $existe ? (int) $f['stock'] : 0,
      'precio'      => $existe ? precioConDescuento((float) $f['precio'], (int) $f['descuento_pct']) : 0.0,
      'disponible'  => $existe,
    ];
  }

  return [
    'id'             => (int) $lista['id'],
    'nombre'         => $lista['nombre'],
    'dia_compra'     => $lista['dia_compra'],
    'actualizado_en' => $lista['actualizado_en'],
    'items'          => $items,
    'cantidad_items' => count($items),
    'unidades_total' => array_sum(array_column($items, 'cantidad')),
  ];
}

/**
 * Trae TODAS las Compras Semanales del usuario (hasta 3), con sus
 * productos ya cruzados con la base para tener precio/stock actuales.
 * Devuelve un array vacío si todavía no guardó ninguna.
 */
function obtenerComprasSemanales(PDO $pdo, int $usuarioId): array {
  $stmt = $pdo->prepare('SELECT * FROM compra_semanal WHERE usuario_id = :uid ORDER BY creado_en ASC');
  $stmt->execute([':uid' => $usuarioId]);
  $listas = $stmt->fetchAll();

  $resultado = [];
  foreach ($listas as $lista) {
    $resultado[] = armarDetalleCompraSemanal($pdo, $lista);
  }
  return $resultado;
}

/** Trae UNA Compra Semanal puntual, verificando que sea del usuario. Null si no existe o no es suya. */
function obtenerCompraSemanalPorId(PDO $pdo, int $usuarioId, int $compraId): ?array {
  $stmt = $pdo->prepare('SELECT * FROM compra_semanal WHERE id = :id AND usuario_id = :uid LIMIT 1');
  $stmt->execute([':id' => $compraId, ':uid' => $usuarioId]);
  $lista = $stmt->fetch();
  return $lista ? armarDetalleCompraSemanal($pdo, $lista) : null;
}

/** Cuántas Compras Semanales tiene guardadas el usuario */
function contarComprasSemanales(PDO $pdo, int $usuarioId): int {
  $stmt = $pdo->prepare('SELECT COUNT(*) FROM compra_semanal WHERE usuario_id = :uid');
  $stmt->execute([':uid' => $usuarioId]);
  return (int) $stmt->fetchColumn();
}

/* ═══════════════════════════════════════════════════════════
   COMPRA SEMANAL: ESCRITURA
   ═══════════════════════════════════════════════════════════ */

/**
 * Crea o actualiza una Compra Semanal reemplazando sus productos por
 * la lista de $items recibida: [producto_id => cantidad]. Nunca
 * guarda precios.
 *
 * - Si $compraId viene con un valor (y es del usuario), ACTUALIZA esa lista.
 * - Si $compraId es null, CREA una lista nueva, siempre y cuando el
 *   usuario todavía no llegó al máximo de MAX_COMPRAS_SEMANALES.
 *
 * Devuelve el id de la lista guardada, o false si no se pudo crear
 * una lista nueva por haber alcanzado el máximo.
 */
function guardarCompraSemanal(PDO $pdo, int $usuarioId, string $nombre, ?string $diaCompra, array $items, ?int $compraId = null) {
  $nombre = trim($nombre) !== '' ? trim($nombre) : 'Compra semanal';
  $nombre = mb_substr($nombre, 0, 100); // compra_semanal.nombre es VARCHAR(100)
  if ($diaCompra !== null && !in_array($diaCompra, diasSemanaValidos(), true)) {
    $diaCompra = null;
  }

  // Solo productos que existen y están activos, con cantidades razonables.
  // Antes se insertaba cualquier producto_id del formulario: un id inválido
  // rompía la clave foránea y terminaba en un error 500.
  $pedidos = [];
  foreach ($items as $productoId => $cantidad) {
    $productoId = (int) $productoId;
    $cantidad = min((int) $cantidad, 999);
    if ($productoId > 0 && $cantidad > 0) {
      $pedidos[$productoId] = $cantidad;
    }
  }
  if (!empty($pedidos)) {
    $ids = array_keys($pedidos);
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmtExisten = $pdo->prepare("SELECT id FROM productos WHERE id IN ($placeholders) AND activo = 1");
    $stmtExisten->execute($ids);
    $validos = array_flip(array_map('intval', $stmtExisten->fetchAll(PDO::FETCH_COLUMN)));
    $pedidos = array_intersect_key($pedidos, $validos);
  }

  if (empty($pedidos)) {
    throw new InvalidArgumentException('La lista no tiene productos válidos.');
  }

  $pdo->beginTransaction();
  try {
    if ($compraId !== null) {
      // Verificamos que esa lista sea del usuario antes de tocarla
      $stmt = $pdo->prepare('SELECT id FROM compra_semanal WHERE id = :id AND usuario_id = :uid LIMIT 1 FOR UPDATE');
      $stmt->execute([':id' => $compraId, ':uid' => $usuarioId]);
      if (!$stmt->fetchColumn()) {
        $compraId = null; // no es suya: la tratamos como creación nueva más abajo
      }
    }

    if ($compraId !== null) {
      $upd = $pdo->prepare('UPDATE compra_semanal SET nombre = :nombre, dia_compra = :dia, actualizado_en = NOW() WHERE id = :id');
      $upd->execute([':nombre' => $nombre, ':dia' => $diaCompra, ':id' => $compraId]);
      $pdo->prepare('DELETE FROM compra_semanal_items WHERE compra_semanal_id = :id')->execute([':id' => $compraId]);
    } else {
      // Bloqueamos la fila del usuario: dos pedidos simultáneos no pueden pasar del máximo de listas
      $pdo->prepare('SELECT id FROM usuarios WHERE id = :uid FOR UPDATE')->execute([':uid' => $usuarioId]);
      $actuales = contarComprasSemanales($pdo, $usuarioId);
      if ($actuales >= MAX_COMPRAS_SEMANALES) {
        $pdo->rollBack();
        return false;
      }
      $ins = $pdo->prepare('INSERT INTO compra_semanal (usuario_id, nombre, dia_compra) VALUES (:uid, :nombre, :dia)');
      $ins->execute([':uid' => $usuarioId, ':nombre' => $nombre, ':dia' => $diaCompra]);
      $compraId = (int) $pdo->lastInsertId();
    }

    $insItem = $pdo->prepare('INSERT INTO compra_semanal_items (compra_semanal_id, producto_id, cantidad) VALUES (:cs, :pid, :cant)');
    foreach ($pedidos as $productoId => $cantidad) {
      $insItem->execute([':cs' => $compraId, ':pid' => $productoId, ':cant' => $cantidad]);
    }

    $pdo->commit();
    return $compraId;
  } catch (Throwable $e) {
    if ($pdo->inTransaction()) {
      $pdo->rollBack();
    }
    throw $e;
  }
}

/**
 * Cantidad total comprada de cada producto en un pedido: [producto_id => cantidad].
 * SUMA las filas que repiten producto (una línea normal + una promo del mismo
 * producto); antes la última fila pisaba a las anteriores y la cantidad quedaba mal.
 */
function itemsDePedidoPorProducto(PDO $pdo, int $pedidoId): array {
  $stmt = $pdo->prepare('
    SELECT producto_id, SUM(cantidad) AS cantidad
    FROM pedido_items
    WHERE pedido_id = :pid AND producto_id IS NOT NULL
    GROUP BY producto_id
  ');
  $stmt->execute([':pid' => $pedidoId]);
  $items = [];
  foreach ($stmt->fetchAll() as $fila) {
    $items[(int) $fila['producto_id']] = (int) $fila['cantidad'];
  }
  return $items;
}

/**
 * Guarda la Compra Semanal a partir de los ítems de un pedido ya
 * confirmado (se usa desde el cuadro de diálogo post-compra).
 * $compraId: null para crear una lista nueva, o el id de una lista
 * existente para reemplazar su contenido con este pedido.
 * Devuelve el id guardado, o false si no se pudo (límite alcanzado).
 */
function guardarCompraSemanalDesdePedido(PDO $pdo, int $usuarioId, int $pedidoId, string $nombre, ?string $diaCompra, ?int $compraId = null) {
  $items = itemsDePedidoPorProducto($pdo, $pedidoId);
  return guardarCompraSemanal($pdo, $usuarioId, $nombre, $diaCompra, $items, $compraId);
}

/** Elimina una Compra Semanal puntual (verificando que sea del usuario) */
function eliminarCompraSemanal(PDO $pdo, int $usuarioId, int $compraId): void {
  $stmt = $pdo->prepare('DELETE FROM compra_semanal WHERE id = :id AND usuario_id = :uid');
  $stmt->execute([':id' => $compraId, ':uid' => $usuarioId]);
}

/* ═══════════════════════════════════════════════════════════
   AGREGAR AL CARRITO (una Compra Semanal o un pedido viejo)
   ═══════════════════════════════════════════════════════════ */

/**
 * Agrega al carrito una lista de [producto_id => cantidad] usando
 * SIEMPRE precio y stock actuales (los recalcula carritoDetalle()).
 * Devuelve qué se pudo agregar y qué no (producto eliminado, sin
 * stock o inactivo), para poder avisarle al usuario con claridad.
 */
function agregarItemsAlCarrito(PDO $pdo, array $items): array {
  $agregados = [];
  $noDisponibles = [];

  if (empty($items)) {
    return ['agregados' => $agregados, 'no_disponibles' => $noDisponibles];
  }

  $ids = array_map('intval', array_keys($items));
  $placeholders = implode(',', array_fill(0, count($ids), '?'));
  $stmt = $pdo->prepare("SELECT id, nombre, stock FROM productos WHERE id IN ($placeholders) AND activo = 1");
  $stmt->execute($ids);
  $productosDb = $stmt->fetchAll(PDO::FETCH_ASSOC | PDO::FETCH_UNIQUE);

  foreach ($items as $productoId => $cantidad) {
    $productoId = (int) $productoId;
    $cantidad = (int) $cantidad;

    if (!isset($productosDb[$productoId])) {
      $noDisponibles[] = 'Producto no disponible';
      continue;
    }
    $p = $productosDb[$productoId];
    if ((int) $p['stock'] <= 0) {
      $noDisponibles[] = $p['nombre'] . ' (sin stock)';
      continue;
    }
    // El tope de stock se aplica al TOTAL que queda en el carrito, no solo a lo que se suma
    $yaEnCarrito = (int) ($_SESSION['carrito'][$productoId] ?? 0);
    if ($yaEnCarrito >= (int) $p['stock']) {
      $noDisponibles[] = $p['nombre'] . ' (ya tenés todo el stock disponible en el carrito)';
      continue;
    }
    $cantidadFinal = min($cantidad, (int) $p['stock'] - $yaEnCarrito);
    carritoAgregar($productoId, $cantidadFinal, (int) $p['stock']);
    $agregados[] = $p['nombre'];
  }

  return ['agregados' => $agregados, 'no_disponibles' => $noDisponibles];
}

/** Agrega al carrito TODOS los productos de una Compra Semanal puntual. */
function agregarCompraSemanalAlCarrito(PDO $pdo, int $usuarioId, int $compraId): array {
  $lista = obtenerCompraSemanalPorId($pdo, $usuarioId, $compraId);
  if (!$lista) {
    return ['agregados' => [], 'no_disponibles' => []];
  }
  $items = [];
  foreach ($lista['items'] as $it) {
    if ($it['producto_id'] !== null) {
      $items[$it['producto_id']] = $it['cantidad'];
    }
  }
  return agregarItemsAlCarrito($pdo, $items);
}

/**
 * Separa las filas de un pedido en productos sueltos y líneas de promoción:
 * ['productos' => [producto_id => cantidad], 'promos' => [promocion_id => cantidad]].
 * Las filas de una misma promo comparten promocion_id y cantidad.
 */
function itemsDePedidoParaRepetir(PDO $pdo, int $pedidoId): array {
  $stmt = $pdo->prepare('SELECT producto_id, promocion_id, cantidad FROM pedido_items WHERE pedido_id = :pid');
  $stmt->execute([':pid' => $pedidoId]);
  $productos = [];
  $promos = [];
  foreach ($stmt->fetchAll() as $fila) {
    if ($fila['promocion_id'] !== null) {
      $promos[(int) $fila['promocion_id']] = max($promos[(int) $fila['promocion_id']] ?? 0, (int) $fila['cantidad']);
    } elseif ($fila['producto_id'] !== null) {
      $productos[(int) $fila['producto_id']] = ($productos[(int) $fila['producto_id']] ?? 0) + (int) $fila['cantidad'];
    }
  }
  return ['productos' => $productos, 'promos' => $promos];
}

/**
 * "Volver a comprar": agrega al carrito los productos de un pedido pasado y
 * vuelve a agregar sus promociones si siguen vigentes (con precio y stock actuales).
 */
function repetirPedidoAlCarrito(PDO $pdo, int $usuarioId, int $pedidoId): ?array {
  $stmt = $pdo->prepare('SELECT id FROM pedidos WHERE id = :id AND usuario_id = :uid');
  $stmt->execute([':id' => $pedidoId, ':uid' => $usuarioId]);
  if (!$stmt->fetch()) {
    return null; // no es un pedido de este usuario
  }

  $partes = itemsDePedidoParaRepetir($pdo, $pedidoId);
  $resultado = agregarItemsAlCarrito($pdo, $partes['productos']);

  foreach ($partes['promos'] as $promoId => $cantidad) {
    $promo = obtenerPromocionActivaPorId($pdo, $promoId);
    if (!$promo) {
      $t = $pdo->prepare('SELECT titulo FROM promociones WHERE id = :id');
      $t->execute([':id' => $promoId]);
      $resultado['no_disponibles'][] = 'Promoción "' . ($t->fetchColumn() ?: 'ya no existe') . '" (ya no está vigente)';
      continue;
    }
    $paso = pasoCantidadPromo($promo['tipo']);
    $max = cantidadMaximaPromo($promo, $promo['productos']);
    $cantidadFinal = intdiv(min($cantidad, $max), $paso) * $paso;
    if ($cantidadFinal < 1) {
      $resultado['no_disponibles'][] = $promo['titulo'] . ' (sin stock)';
      continue;
    }
    carritoAgregarPromo($promoId, $cantidadFinal);
    $resultado['agregados'][] = $promo['titulo'];
  }

  return $resultado;
}

/* ═══════════════════════════════════════════════════════════
   HISTORIAL DE PEDIDOS
   ═══════════════════════════════════════════════════════════ */

/** Historial de pedidos del usuario, más recientes primero, paginado. */
function obtenerHistorialPedidos(PDO $pdo, int $usuarioId, int $pagina = 1, int $porPagina = 5): array {
  $offset = max(0, ($pagina - 1) * $porPagina);

  $total = $pdo->prepare('SELECT COUNT(*) FROM pedidos WHERE usuario_id = :uid');
  $total->execute([':uid' => $usuarioId]);
  $totalPedidos = (int) $total->fetchColumn();

  $stmt = $pdo->prepare("
    SELECT id, total, estado, tipo_entrega, creado_en,
           (SELECT COUNT(*) FROM pedido_items WHERE pedido_id = pedidos.id) AS cant_items
    FROM pedidos
    WHERE usuario_id = :uid
    ORDER BY creado_en DESC, id DESC
    LIMIT :limite OFFSET :offset
  ");
  $stmt->bindValue(':uid', $usuarioId, PDO::PARAM_INT);
  $stmt->bindValue(':limite', $porPagina, PDO::PARAM_INT);
  $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
  $stmt->execute();

  return [
    'pedidos'       => $stmt->fetchAll(),
    'total'         => $totalPedidos,
    'total_paginas' => max(1, (int) ceil($totalPedidos / $porPagina)),
    'pagina'        => $pagina,
  ];
}

/** Detalle completo de un pedido puntual (para "Ver detalle") */
function obtenerDetallePedido(PDO $pdo, int $usuarioId, int $pedidoId): ?array {
  $stmt = $pdo->prepare('SELECT * FROM pedidos WHERE id = :id AND usuario_id = :uid');
  $stmt->execute([':id' => $pedidoId, ':uid' => $usuarioId]);
  $pedido = $stmt->fetch();
  if (!$pedido) {
    return null;
  }
  $stmtItems = $pdo->prepare('SELECT * FROM pedido_items WHERE pedido_id = :pid ORDER BY id ASC');
  $stmtItems->execute([':pid' => $pedidoId]);
  $pedido['items'] = $stmtItems->fetchAll();
  return $pedido;
}

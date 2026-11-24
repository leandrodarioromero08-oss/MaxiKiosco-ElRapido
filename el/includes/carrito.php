<?php
// ════════════════════════════════════════════════════════════════
//  includes/carrito.php — EL "MOTOR" DEL CARRITO
// ════════════════════════════════════════════════════════════════
//  Funciones que usan carrito.php, carrito_agregar.php, el header, etc.
//
//  El carrito vive en la SESIÓN de PHP en dos arreglos separados:
//    $_SESSION['carrito']        = [ producto_id   => cantidad ]
//    $_SESSION['carrito_promos'] = [ promocion_id  => cantidad ]
//  Solo se guardan ids y cantidades: nunca precios ni nombres.
/**
 * Helpers del carrito de compras.
 * El carrito se guarda en $_SESSION['carrito'] como:
 *   [ producto_id => cantidad, producto_id => cantidad, ... ]
 * Los precios y nombres NUNCA se guardan en sesión: siempre se
 * recalculan desde la base de datos para evitar que alguien
 * manipule el precio desde el navegador.
 */

// Dependencias: funciones.php (precioConDescuento) y
// promociones_funciones.php (promos). limitarCantidad(), que se usa más
// abajo, viene de seguridad.php, que config.php carga automáticamente.
require_once __DIR__ . '/funciones.php'; // necesitamos precioConDescuento()
require_once __DIR__ . '/promociones_funciones.php';

// Garantiza que la sesión esté iniciada (si no, $_SESSION no existe).
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

// Si el carrito no existe todavía (o fue alterado y no es un array),
// se crea vacío. Lo mismo para el carrito de promociones.
if (!isset($_SESSION['carrito']) || !is_array($_SESSION['carrito'])) {
  $_SESSION['carrito'] = [];
}
if (!isset($_SESSION['carrito_promos']) || !is_array($_SESSION['carrito_promos'])) {
  $_SESSION['carrito_promos'] = [];
}

/**
 * Suma unidades de un producto al carrito. Si se pasa $stock, la cantidad total
 * del producto en el carrito nunca supera ese stock (el servidor no confía en
 * que el navegador haya respetado el tope). Devuelve la cantidad que quedó en el carrito.
 */
function carritoAgregar(int $productoId, int $cantidad, ?int $stock = null): int {
  // Mínimo 1 unidad por llamada.
  if ($cantidad < 1) {
    $cantidad = 1;
  }
  // limitarCantidad() aplica el tope por línea (CANTIDAD_MAXIMA_POR_LINEA).
  $cantidad = limitarCantidad($cantidad); // tope por línea (ver seguridad.php)
  // Suma lo que ya había en el carrito (?? 0 si no estaba) más lo nuevo,
  // y vuelve a aplicar el tope por línea.
  $nueva = limitarCantidad(($_SESSION['carrito'][$productoId] ?? 0) + $cantidad);
  // Si se conoce el stock, la cantidad total nunca lo supera. El servidor
  // no confía en que el navegador haya respetado el tope.
  if ($stock !== null) {
    $nueva = min($nueva, max(0, $stock));
  }
  // Si quedó en 0 (o menos), el producto se saca del carrito.
  if ($nueva < 1) {
    unset($_SESSION['carrito'][$productoId]);
    return 0;
  }
  // Guarda la cantidad final y la devuelve (carrito_agregar.php la usa
  // para avisar si hubo que recortarla).
  $_SESSION['carrito'][$productoId] = $nueva;
  return $nueva;
}

/** Fija la cantidad de una línea; con $stock, la recorta al stock disponible. */
function carritoActualizarCantidad(int $productoId, int $cantidad, ?int $stock = null): void {
  // Diferencia con carritoAgregar: acá la cantidad se FIJA, no se suma.
  if ($stock !== null) {
    $cantidad = min($cantidad, max(0, $stock));
  }
  if ($cantidad < 1) {
    unset($_SESSION['carrito'][$productoId]);
    return;
  }
  $_SESSION['carrito'][$productoId] = limitarCantidad($cantidad);
}

// Quita un producto del carrito (unset borra esa clave del arreglo).
function carritoEliminar(int $productoId): void {
  unset($_SESSION['carrito'][$productoId]);
}

// Vacía todo: productos y promociones. Se llama tras confirmar el pedido.
function carritoVaciar(): void {
  $_SESSION['carrito'] = [];
  $_SESSION['carrito_promos'] = [];
}

/* ── Promociones (2x1, 3x2, Combo, Oferta, Descuento %) ── */

// Agrega una promoción al carrito.
function carritoAgregarPromo(int $promocionId, int $cantidad): void {
  if ($cantidad < 1) {
    $cantidad = 1;
  }
  // El modal ya manda la cantidad total elegida, no se acumula: se fija.
  $_SESSION['carrito_promos'][$promocionId] = limitarCantidad($cantidad);
}

// Fija la cantidad de una promo; con menos de 1 la elimina.
function carritoActualizarCantidadPromo(int $promocionId, int $cantidad): void {
  if ($cantidad < 1) {
    unset($_SESSION['carrito_promos'][$promocionId]);
    return;
  }
  $_SESSION['carrito_promos'][$promocionId] = limitarCantidad($cantidad);
}

// Quita una promoción del carrito.
function carritoEliminarPromo(int $promocionId): void {
  unset($_SESSION['carrito_promos'][$promocionId]);
}

/**
 * Trae los productos del carrito ya cruzados con la base de datos
 * (precio y stock siempre actuales) y calcula los totales.
 * Si un producto fue eliminado o desactivado, se saca solo del carrito
 * y se devuelve un aviso en 'avisos'.
 *
 * $limpiarSesion = false calcula todo igual pero NO modifica $_SESSION
 * (se usa en el header, que se ejecuta en cada página: la limpieza con
 * avisos para el cliente la hace carrito.php, que sí puede mostrarlos).
 */
function carritoDetalle(PDO $pdo, bool $limpiarSesion = true): array {
  // $items   → líneas ya calculadas, listas para dibujar y para guardar.
  // $subtotal→ suma acumulada (decimal).
  // $avisos  → mensajes para el cliente (algo se quitó o se ajustó).
  // $carritoLinea / $carritoPromos → lo que hay en la sesión.
  $items = [];
  $subtotal = 0.0;
  $avisos = [];
  $carritoLinea = $_SESSION['carrito'] ?? [];
  $carritoPromos = $_SESSION['carrito_promos'] ?? [];

  // Si no hay productos normales se evita la consulta (además
  // WHERE id IN () con lista vacía es SQL inválido).
  if (!empty($carritoLinea)) {
    // array_keys: solo las claves del carrito = los ids de producto.
    $ids = array_keys($carritoLinea);
    // Genera tantos signos ? como productos haya. Ej.: 3 productos → '?,?,?'.
    // Son parámetros preparados: los ids NO se pegan dentro del SQL.
    $placeholders = implode(',', array_fill(0, count($ids), '?'));

    // UNA sola consulta trae todos los productos del carrito.
    //  - JOIN categorias: trae el nombre de la categoría.
    //  - 'AS categoria_nombre': alias para no chocar con p.nombre.
    //  - AND p.activo = 1: los productos desactivados no se devuelven.
    $stmt = $pdo->prepare("
      SELECT p.id, p.nombre, p.precio, p.descuento_pct, p.emoji, p.imagen, p.stock, c.nombre AS categoria_nombre
      FROM productos p
      JOIN categorias c ON c.id = p.categoria_id
      WHERE p.id IN ($placeholders) AND p.activo = 1
    ");
    $stmt->execute($ids);
    // FETCH_UNIQUE usa la PRIMERA columna del SELECT (p.id) como clave del
    // arreglo resultante: $productosDb[12] da directo el producto 12.
    $productosDb = $stmt->fetchAll(PDO::FETCH_ASSOC | PDO::FETCH_UNIQUE);

    // Se recorre lo que hay en la SESIÓN (no lo que devolvió la base):
    // así se detectan los productos que desaparecieron.
    foreach ($carritoLinea as $productoId => $cantidad) {
      // Está en el carrito pero no vino de la base → fue borrado o desactivado.
      if (!isset($productosDb[$productoId])) {
        // El producto ya no existe o está desactivado: lo sacamos del carrito
        // $limpiarSesion permite calcular sin modificar la sesión (ver docblock).
        if ($limpiarSesion) {
          unset($_SESSION['carrito'][$productoId]);
        }
        $avisos[] = 'Un producto de tu carrito ya no está disponible y se quitó automáticamente.';
        // continue: salta al siguiente producto sin calcular nada para este.
        continue;
      }
      // Datos del producto tomados de la BASE, no del navegador.
      $p = $productosDb[$productoId];
      // Cobramos el precio CON el descuento aplicado, no el precio de lista
      // precioConDescuento: ÚNICA función del sitio que calcula descuentos.
      // Se usa también en el catálogo, así el precio mostrado y el cobrado
      // nunca difieren. Los casts (float)/(int) son necesarios porque PDO suele
      // devolver los números como texto.
      $precioFinal = precioConDescuento((float) $p['precio'], (int) $p['descuento_pct']);
      // round(..., 2): evita resultados como 7500.000000001 al multiplicar decimales.
      $itemSubtotal = round($precioFinal * $cantidad, 2);
      // Cada línea se guarda con todo lo que necesita el HTML para dibujar
      // la fila. 'precio' ya tiene el descuento aplicado; 'stock' sirve para
      // mostrar "Solo quedan X disponibles".
      $items[] = [
        'id'               => (int) $productoId,
        'nombre'           => $p['nombre'],
        'categoria_nombre' => $p['categoria_nombre'],
        'emoji'            => $p['emoji'],
        'imagen'           => $p['imagen'],
        'precio'           => $precioFinal,
        'cantidad'         => $cantidad,
        'stock'            => (int) $p['stock'],
        'subtotal'         => $itemSubtotal,
      ];
      // Suma la línea al subtotal acumulado.
      $subtotal += $itemSubtotal;
    }
  }

  /* ───────────────────────────────────────────────
     LÍNEAS DE PROMOCIÓN (2x1, 3x2, Combo, Oferta, Descuento %)
     Se guardan aparte, en $_SESSION['carrito_promos'][promocion_id] = cantidad.
     Todo se recalcula acá contra la base: si la promo venció, la pausaron,
     o ya no hay stock suficiente, se ajusta o se saca sola del carrito.
     ─────────────────────────────────────────────── */
  // $ahorroTotal: cuánto se ahorra el cliente gracias a las promociones.
  $ahorroTotal = 0.0;

  if (!empty($carritoPromos)) {
    // Recorre las promociones del carrito.
    foreach ($carritoPromos as $promocionId => $cantidad) {
      // Busca la promo en la base. Devuelve null si no existe, está pausada
      // o venció (se decide por las fechas de hoy).
      $promo = obtenerPromocionActivaPorId($pdo, (int) $promocionId);

      // Promo no disponible → se quita (si corresponde) y se avisa.
      if (!$promo) {
        if ($limpiarSesion) {
          unset($_SESSION['carrito_promos'][$promocionId]);
        }
        $avisos[] = 'Una promoción de tu carrito ya no está disponible y se quitó automáticamente.';
        continue;
      }

      // Máximo que se puede vender según el stock del producto con MENOS
      // stock de la promo (y el paso: pares en 2x1, tríos en 3x2...).
      $maxCantidad = cantidadMaximaPromo($promo, $promo['productos']);
      // Si el cliente tiene más de lo que alcanza: o no hay stock (se quita)
      // o se ajusta la cantidad al máximo disponible y se avisa.
      if ($cantidad > $maxCantidad) {
        if ($maxCantidad <= 0) {
          if ($limpiarSesion) {
            unset($_SESSION['carrito_promos'][$promocionId]);
          }
          $avisos[] = 'No hay stock suficiente para "' . $promo['titulo'] . '" y se quitó de tu carrito.';
          continue;
        }
        $cantidad = $maxCantidad;
        if ($limpiarSesion) {
          $_SESSION['carrito_promos'][$promocionId] = $cantidad;
        }
        $avisos[] = 'Ajustamos la cantidad de "' . $promo['titulo'] . '" por falta de stock.';
      }

      // calcularLineaPromo calcula precio de la línea, ahorro, nota y
      // etiqueta según el tipo de promo (2x1, 3x2, combo, oferta, descuento %).
      $linea = calcularLineaPromo($promo, $promo['productos'], $cantidad);

      // Línea de promo: lleva es_promo=true para distinguirla en el HTML y
      // al guardar el pedido. 'precio' es el precio unitario efectivo
      // (subtotal ÷ cantidad) y 'paso' el salto de cantidad (2, 3 o 1).
      $items[] = [
        'es_promo'        => true,
        'id'              => (int) $promocionId,
        'nombre'          => $promo['titulo'],
        'tipo'            => $promo['tipo'],
        'color_banner'    => $promo['color_banner'],
        'color_hex'       => $promo['color_hex'],
        'productos'       => $promo['productos'],
        'cantidad'        => $cantidad,
        'precio'          => $cantidad > 0 ? round($linea['precio_linea'] / $cantidad, 2) : 0,
        'subtotal'        => $linea['precio_linea'],
        'ahorro'          => $linea['ahorro_linea'],
        'nota'            => $linea['nota_tipo'],
        'etiqueta_unidad' => $linea['etiqueta_unidad'],
        'paso'            => pasoCantidadPromo($promo['tipo']),
        'stock'           => $maxCantidad,
      ];
      // Se suma al subtotal y al ahorro total.
      $subtotal += $linea['precio_linea'];
      $ahorroTotal += $linea['ahorro_linea'];
    }
  }

  // Redondeo final del subtotal a 2 decimales.
  $subtotal = round($subtotal, 2);

  // Resultado de la función:
  //  items          → todas las líneas (productos y promos)
  //  cantidad_total → suma de cantidades (para el contador del carrito)
  //  subtotal/total → sin envío (el recargo se suma al finalizar)
  //  ahorro_total   → ahorro por promociones
  //  avisos         → mensajes para mostrar al cliente
  return [
    'items'          => $items,
    'cantidad_total' => array_sum(array_column($items, 'cantidad')),
    'subtotal'       => $subtotal,
    'envio'          => 0.0, // el recargo de delivery se suma recién al finalizar la compra
    'total'          => $subtotal,
    'ahorro_total'   => round($ahorroTotal, 2),
    'avisos'         => $avisos,
  ];
}

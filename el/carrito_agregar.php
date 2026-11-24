<?php
// ════════════════════════════════════════════════════════════════
//  carrito_agregar.php — AGREGAR UN PRODUCTO AL CARRITO
// ════════════════════════════════════════════════════════════════
//  Lo llama el botón "Agregar" del catálogo (formulario POST).
//  No dibuja nada: valida, agrega a la sesión y redirige.
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/carrito.php';
require_once 'includes/funciones.php';
csrfExigir(); // protección CSRF: rechaza cualquier POST sin token válido

// Solo se procesa si llegó un POST (un GET directo no hace nada).
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  // El id del producto viene del formulario: se fuerza a entero.
  $productoId = (int) ($_POST['producto_id'] ?? 0);
  // Cantidad mínima 1 (max evita ceros o negativos).
  $cantidad   = max(1, (int) ($_POST['cantidad'] ?? 1));

  // Un id válido es mayor que 0.
  if ($productoId > 0) {
    // Verificamos que el producto exista, esté activo y tenga stock antes de agregarlo
    $pdo = obtenerConexion();
    // Consulta preparada: trae el producto solo si existe y está activo.
    $stmt = $pdo->prepare('SELECT id, nombre, stock FROM productos WHERE id = :id AND activo = 1');
    $stmt->execute([':id' => $productoId]);
    $producto = $stmt->fetch();
    // Tres casos: no existe, sin stock, o se puede agregar.
    if (!$producto) {
      flashSet('error', 'Ese producto ya no está disponible.');
    } elseif ((int) $producto['stock'] <= 0) {
      flashSet('error', '"' . $producto['nombre'] . '" está sin stock.');
    } else {
      // Cantidad que había antes de agregar.
      $antes = (int) ($_SESSION['carrito'][$productoId] ?? 0);
      // carritoAgregar suma y recorta al stock; devuelve la cantidad final.
      $despues = carritoAgregar($productoId, $cantidad, (int) $producto['stock']);
      // Si quedó menos de lo que se pedía (por el stock o por el tope por
      // línea), se le avisa al cliente con un mensaje flash.
      if ($despues < min($antes + $cantidad, CANTIDAD_MAXIMA_POR_LINEA)) {
        flashSet('error', 'Solo hay ' . (int) $producto['stock'] . ' unidades de "' . $producto['nombre'] . '" disponibles; ajustamos tu carrito a ' . $despues . '.');
      }
    }
  }
}

// obtenerVolverSeguro valida a dónde volver (solo páginas del propio
// sitio): evita redirecciones a sitios externos.
$volver = obtenerVolverSeguro('productos.php');

// Redirige y corta: Post-Redirect-Get (no se reenvía al refrescar).
header('Location: ' . $volver);
exit;

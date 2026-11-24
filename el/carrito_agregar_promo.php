<?php
// ════════════════════════════════════════════════════════════════
//  carrito_agregar_promo.php — AGREGAR UNA PROMOCIÓN AL CARRITO
// ════════════════════════════════════════════════════════════════
//  Igual que carrito_agregar.php pero para promociones (2x1, 3x2,
//  combos, ofertas, descuento %).
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/carrito.php';
require_once 'includes/funciones.php';
require_once 'includes/promociones_funciones.php';
csrfExigir(); // protección CSRF: rechaza cualquier POST sin token válido

// Solo se procesa si llegó un POST.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  // id de la promo (entero) y cantidad (mínimo 1).
  $promocionId = (int) ($_POST['promocion_id'] ?? 0);
  $cantidad    = max(1, (int) ($_POST['cantidad'] ?? 1));

  if ($promocionId > 0) {
    // Se refrescan los estados de las promociones (vencidas, pausadas...)
    // antes de buscar la promo.
    $pdo = obtenerConexion();
    actualizarEstadosPromociones($pdo);
    // Devuelve la promo completa o null si no está disponible.
    $promo = obtenerPromocionActivaPorId($pdo, $promocionId);

    if ($promo) {
      // Nunca confiamos en la cantidad que mandó el navegador sin tope:
      // la recortamos al máximo real según stock y al múltiplo correcto
      // (pares en 2x1, tríos en 3x2, combos completos en Combo).
      // Máximo posible según stock real.
      $maxCantidad = cantidadMaximaPromo($promo, $promo['productos']);
      // Paso de la promo: 2 en 2x1, 3 en 3x2, 1 en el resto.
      $paso = pasoCantidadPromo($promo['tipo']);
      // Se recorta al máximo disponible...
      $cantidadFinal = min($cantidad, $maxCantidad);
      // ...y se redondea hacia abajo al múltiplo del paso. Ej.: en un 2x1,
      // 5 pedidos → intdiv(5, 2) * 2 = 4.
      $cantidadFinal = intdiv($cantidadFinal, $paso) * $paso;

      // Si quedó al menos un paso completo, se agrega (carritoAgregarPromo
      // FIJA la cantidad, no la suma).
      if ($cantidadFinal > 0) {
        carritoAgregarPromo($promocionId, $cantidadFinal);
      }
    }
  }
}

// Vuelve a la página de origen (validada) y corta la ejecución.
$volver = obtenerVolverSeguro('index.php');
header('Location: ' . $volver);
exit;

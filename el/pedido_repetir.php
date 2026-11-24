<?php
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/carrito.php';
require_once 'includes/funciones.php';
require_once 'includes/compra_semanal.php';
csrfExigir(); // protección CSRF: rechaza cualquier POST sin token válido

if (!usuarioLogueado()) {
  header('Location: login.php?volver=mi-cuenta.php');
  exit;
}

$pdo = obtenerConexion();
$usuarioId = (int) $_SESSION['usuario']['id'];
// Solo por POST: modifica el carrito, así que no se acepta por GET (un link no puede dispararlo).
$pedidoId = $_SERVER['REQUEST_METHOD'] === 'POST' ? (int) ($_POST['pedido_id'] ?? 0) : 0;
$volver = obtenerVolverSeguro('mi-cuenta.php?seccion=compra-semanal');

if ($pedidoId > 0) {
  $resultado = repetirPedidoAlCarrito($pdo, $usuarioId, $pedidoId);

  if ($resultado === null) {
    flashSet('error', 'No pudimos encontrar ese pedido.');
  } else {
    if (!empty($resultado['agregados'])) {
      flashSet('ok', count($resultado['agregados']) . ' producto(s) del pedido se agregaron al carrito con precio y stock actuales.');
    }
    if (!empty($resultado['no_disponibles'])) {
      flashSet('error', 'No pudimos agregar: ' . implode(', ', $resultado['no_disponibles']) . '.');
    }
    if (empty($resultado['agregados']) && empty($resultado['no_disponibles'])) {
      flashSet('error', 'Ese pedido no tiene productos para volver a agregar.');
    }
  }
}

header('Location: ' . $volver);
exit;

<?php
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/carrito.php';
require_once 'includes/funciones.php';
require_once 'includes/compra_semanal.php';
csrfExigir(); // protección CSRF: rechaza cualquier POST sin token válido

if (!usuarioLogueado()) {
  header('Location: login.php');
  exit;
}

$pdo = obtenerConexion();
$usuarioId = (int) $_SESSION['usuario']['id'];
$pedidoId = (int) ($_POST['pedido_id'] ?? 0);
$decision = postTexto('decision') ?: 'omitir'; // 'guardar' | 'omitir'

// Verificamos que el pedido sea realmente de este usuario
$stmt = $pdo->prepare('SELECT id FROM pedidos WHERE id = :id AND usuario_id = :uid');
$stmt->execute([':id' => $pedidoId, ':uid' => $usuarioId]);

if ($pedidoId > 0 && $stmt->fetch()) {
  if ($decision === 'guardar') {
    $compraIdDestino = !empty($_POST['compra_id']) ? (int) $_POST['compra_id'] : null;
    $nombre = mb_substr(postTexto('nombre'), 0, 100);

    if ($nombre === '' && $compraIdDestino !== null) {
      // Reemplazando una lista existente sin escribir un nombre nuevo: conservamos el que ya tenía
      $listaActual = obtenerCompraSemanalPorId($pdo, $usuarioId, $compraIdDestino);
      $nombre = $listaActual['nombre'] ?? 'Compra semanal';
    } elseif ($nombre === '') {
      $nombre = 'Compra semanal';
    }

    // Si el usuario eligió reemplazar una lista existente, usamos su nombre actual
    // salvo que haya escrito uno nuevo (el campo nombre queda oculto en ese caso).
    $dia = postTexto('dia_compra');
    $dia = $dia !== '' ? $dia : null;

    try {
      $resultado = guardarCompraSemanalDesdePedido($pdo, $usuarioId, $pedidoId, mb_substr($nombre, 0, 100), $dia, $compraIdDestino);
    } catch (Throwable $e) {
      error_log('[el-rapido] guardar_compra_semanal: ' . $e->getMessage());
      $resultado = null;
    }

    if ($resultado === null) {
      flashSet('error', 'No pudimos guardar tu Compra Semanal en este momento. Probá de nuevo desde Mi cuenta.');
    } elseif ($resultado === false) {
      flashSet('error', 'Ya tenés ' . MAX_COMPRAS_SEMANALES . ' Compras Semanales guardadas (el máximo). No pudimos crear una nueva.');
    } else {
      flashSet('ok', 'Guardamos "' . $nombre . '" como Compra Semanal. La vas a encontrar en Mi cuenta y en Inicio.');
    }
  }
  // Recordamos que a este pedido ya se le preguntó, para no repetir el cuadro de diálogo
  $_SESSION['cs_preguntado_' . $pedidoId] = true;
}

header('Location: carrito.php?confirmado=' . $pedidoId);
exit;

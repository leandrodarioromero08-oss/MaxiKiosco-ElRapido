<?php
$pageCss = 'carrito.css';      // tabla, resumen y botones compartidos con el carrito
$pageCssExtra = ['cuenta.css']; // va después para que sus reglas tengan prioridad
// La redirección a login va ANTES de header.php (que ya imprime HTML).
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/funciones.php';
require_once 'includes/compra_semanal.php';

if (!usuarioLogueado()) {
  header('Location: login.php?volver=mi-cuenta.php');
  exit;
}

$pdo = obtenerConexion();
$usuarioId = (int) $_SESSION['usuario']['id'];
$pedidoId = (int) ($_GET['id'] ?? 0);

$pedido = obtenerDetallePedido($pdo, $usuarioId, $pedidoId);

$pageTitle = $pedido ? 'Pedido #' . (int) $pedido['id'] : 'Pedido no encontrado';
$pageDescription = 'Detalle de tu pedido en El Rápido.';
$pageRobots = 'noindex,follow'; // área privada
include 'includes/header.php';

if (!$pedido) {
  echo '<div class="cuenta-wrap"><div class="historial-vacio"><p>No encontramos ese pedido.</p>
        <a href="mi-cuenta.php?seccion=compra-semanal" class="carrito-seguir-comprando">Volver a Mi cuenta</a></div></div>';
  include 'includes/footer.php';
  exit;
}

$info = estadoPedidoInfo($pedido['estado']);
$fecha = new DateTime($pedido['creado_en']);
?>

<div class="cuenta-wrap">
  <?= flashHtml() /* avisos pendientes (ej: resultado de "Volver a comprar") */ ?>
  <div class="editor-lista-header">
    <a href="mi-cuenta.php?seccion=compra-semanal" class="editor-volver"><?= icono('flecha-izq', '', 14) ?> Volver al historial</a>
    <h1 class="cuenta-seccion-titulo">Pedido #<?= (int) $pedido['id'] ?></h1>
    <p class="cuenta-seccion-sub"><?= $fecha->format('d/m/Y') ?> • <?= $fecha->format('H:i') ?> hs — <span class="estado-badge <?= $info['clase'] ?>">● <?= htmlspecialchars($info['label']) ?></span></p>
  </div>

  <div class="compra-semanal-card" style="margin-bottom:16px;">
    <?php $nombreEntregaDet = trim(($pedido['entrega_nombre'] ?? '') . ' ' . ($pedido['entrega_apellido'] ?? '')); ?>
    <?php if ($nombreEntregaDet !== ''): ?>
      <p><strong>A nombre de:</strong> <?= htmlspecialchars($nombreEntregaDet) ?></p>
    <?php endif; ?>
    <?php if (!empty($pedido['telefono_contacto'])): ?>
      <p><strong>Teléfono:</strong> <?= htmlspecialchars($pedido['telefono_contacto']) ?></p>
    <?php endif; ?>
    <?php if ($pedido['tipo_entrega'] === 'delivery'): ?>
      <p><strong>Entrega:</strong> Delivery — <?= htmlspecialchars($pedido['direccion'] ?? '') ?></p>
      <?php if (!empty($pedido['entrega_referencia'])): ?>
        <p><strong>Referencia:</strong> <?= htmlspecialchars($pedido['entrega_referencia']) ?></p>
      <?php endif; ?>
      <?php if (!empty($pedido['entrega_calle'])): ?>
        <p style="margin-top:8px;">
          <a href="<?= htmlspecialchars(generarUrlMapsDireccion($pedido['entrega_calle'], $pedido['entrega_numero'] ?? '', $pedido['entrega_barrio'] ?? '')) ?>" target="_blank" rel="noopener" class="btn-ver-detalle"><?= icono('ubicacion', '', 14) ?> Ver en Google Maps</a>
        </p>
      <?php endif; ?>
    <?php else: ?>
      <p><strong>Entrega:</strong> Retiro en el local</p>
    <?php endif; ?>
  </div>

  <div class="compra-semanal-card">
    <table class="tabla-carrito">
      <caption class="sr-only">Productos del pedido #<?= (int) $pedido['id'] ?></caption>
      <thead>
        <tr><th scope="col">Producto</th><th scope="col">Precio</th><th scope="col">Cantidad</th><th scope="col">Subtotal</th></tr>
      </thead>
      <tbody>
        <?php foreach ($pedido['items'] as $item): ?>
          <tr>
            <td>
              <div class="item-producto-info">
                <div class="item-img"><?= htmlspecialchars($item['emoji']) ?></div>
                <p class="item-nombre"><?= htmlspecialchars($item['nombre_producto']) ?></p>
              </div>
            </td>
            <td class="item-precio" data-label="Precio"><?= formatoPrecio((float) $item['precio_unitario']) ?></td>
            <td data-label="Cantidad"><?= (int) $item['cantidad'] ?></td>
            <td class="item-subtotal" data-label="Subtotal"><?= formatoPrecio((float) $item['subtotal']) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>

    <?php $recargoDet = (float) ($pedido['recargo_envio'] ?? 0); ?>
    <div class="resumen-fila" style="margin-top:16px;">
      <span>Subtotal</span>
      <span><?= formatoPrecio((float) $pedido['total'] - $recargoDet) ?></span>
    </div>
    <?php if ($pedido['tipo_entrega'] === 'delivery'): ?>
    <div class="resumen-fila">
      <span>Envío (delivery)</span>
      <span><?= formatoPrecio($recargoDet) ?></span>
    </div>
    <?php endif; ?>
    <div class="resumen-total-fila">
      <span>Total</span>
      <span><?= formatoPrecio((float) $pedido['total']) ?></span>
    </div>
    <p style="font-size:13px;margin-top:8px;"><strong>Método de pago:</strong> <?= htmlspecialchars(etiquetaMetodoPagoPublico($pedido['metodo_pago'] ?? null)) ?></p>

    <?php if ($pedido['estado'] !== 'cancelado'): ?>
      <form method="POST" action="pedido_repetir.php" style="margin-top:20px;">
<?= csrfCampo() ?>
        <input type="hidden" name="pedido_id" value="<?= (int) $pedido['id'] ?>">
        <input type="hidden" name="volver" value="pedido_detalle.php?id=<?= (int) $pedido['id'] ?>">
        <button type="submit" class="btn-volver-comprar"><?= icono('repetir', '', 14) ?> Volver a comprar</button>
      </form>
    <?php endif; ?>
  </div>
</div>

<?php include 'includes/footer.php'; ?>

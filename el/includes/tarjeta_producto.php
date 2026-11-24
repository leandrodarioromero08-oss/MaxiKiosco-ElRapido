<?php
// ════════════════════════════════════════════════════════════════
//  includes/tarjeta_producto.php — TARJETA DE PRODUCTO REUTILIZABLE
// ════════════════════════════════════════════════════════════════
//  La usan productos.php (catálogo) e index.php ("Lo más pedido"), así las dos
//  tarjetas son idénticas. Agregar sin recargar lo resuelve assets/js/mejoras.js
//  (carrito_api.php); sin JavaScript el formulario sigue enviando carrito_agregar.php.
//
//  $p      fila de productos (id, nombre, precio, descuento_pct, stock, emoji, imagen)
//  $ctx    'favoritos' => [id => true], 'promos' => mapa de productos en promo,
//          'volver' => URL a la que vuelve carrito_agregar.php, 'titulo' => 'h2'|'h3',
//          'mostrarPromo' => bool (etiqueta "En promo")
function renderTarjetaProducto(array $p, array $ctx = []): string {
  $favoritos    = $ctx['favoritos'] ?? [];
  $promos       = $ctx['promos'] ?? [];
  $volver       = $ctx['volver'] ?? 'productos.php';
  $mostrarPromo = $ctx['mostrarPromo'] ?? true;
  $tag          = ($ctx['titulo'] ?? 'h3') === 'h2' ? 'h2' : 'h3';

  $id             = (int) $p['id'];
  $nombre         = htmlspecialchars($p['nombre']);
  $precioOriginal = (float) $p['precio'];
  $tieneDescuento = (int) $p['descuento_pct'] > 0;
  $precioFinal    = precioConDescuento($precioOriginal, (int) $p['descuento_pct']);
  $stock          = (int) $p['stock'];
  $sinStock       = $stock <= 0;
  $esFavorito     = isset($favoritos[$id]);
  $enCarrito      = (int) ($_SESSION['carrito'][$id] ?? 0);   // para que el botón nazca ya como selector de cantidad

  ob_start(); ?>
  <div class="product-card <?= $sinStock ? 'product-card-sin-stock' : '' ?><?= $esFavorito ? ' product-card-favorito' : '' ?>"
       data-stock="<?= $stock ?>" data-producto-id="<?= $id ?>" data-reveal>
    <div class="card-top">
      <?php if ($tieneDescuento): ?>
        <span class="badge-desc">-<?= (int) $p['descuento_pct'] ?>%</span>
      <?php endif; ?>
      <button class="fav-btn" type="button" aria-label="<?= $esFavorito ? 'Quitar' : 'Marcar' ?> <?= $nombre ?> <?= $esFavorito ? 'de favoritos' : 'como favorito' ?>" aria-pressed="<?= $esFavorito ? 'true' : 'false' ?>"><?= icono('corazon', '', 20) ?></button>
    </div>
    <div class="product-img"<?= empty($p['imagen']) ? ' role="img" aria-label="' . $nombre . '"' : '' ?>>
      <?php if (!empty($p['imagen'])): ?>
        <img src="assets/img/productos/<?= htmlspecialchars($p['imagen']) ?>" alt="<?= $nombre ?>" width="160" height="120" loading="lazy" decoding="async">
      <?php else: ?>
        <?= htmlspecialchars($p['emoji']) ?>
      <?php endif; ?>
    </div>
    <<?= $tag ?> class="product-name"><?= $nombre ?></<?= $tag ?>>
    <div class="product-price-row">
      <p class="product-price"><?= formatoPrecio($precioFinal) ?></p>
      <?php if ($tieneDescuento): ?>
        <p class="product-price-original"><?= formatoPrecio($precioOriginal) ?></p>
      <?php endif; ?>
    </div>

    <?php if ($mostrarPromo && isset($promos[$p['id']])): ?>
      <button type="button" class="tag-en-promo" data-abrir-promo="<?= (int) $promos[$p['id']]['promocion_id'] ?>">
        <?= icono('etiqueta', '', 12) ?> En promo
      </button>
    <?php endif; ?>

    <?php if ($sinStock): ?>
      <div class="card-actions">
        <button class="btn-agregar" type="button" disabled aria-label="<?= $nombre ?>: sin stock">Sin stock</button>
      </div>
    <?php else: ?>
      <!-- Un solo botón: "Agregar" suma 1 y se transforma en −/N/+ (mejoras.js). Sin JS: formulario clásico. -->
      <form method="POST" action="carrito_agregar.php" class="card-actions card-actions--ajax<?= $enCarrito ? ' en-carrito' : '' ?>" data-stock="<?= $stock ?>">
        <?= csrfCampo() ?>
        <input type="hidden" name="producto_id" value="<?= $id ?>">
        <input type="hidden" name="cantidad" value="1" class="qty-input">
        <input type="hidden" name="volver" value="<?= htmlspecialchars($volver) ?>">
        <button class="btn-agregar" type="submit" aria-label="Agregar <?= $nombre ?> al carrito"><?= icono('carrito', '', 16) ?><span class="btn-agregar-txt">Agregar</span></button>
        <div class="qty-control" role="group" aria-label="Cantidad de <?= $nombre ?> en el carrito">
          <button class="qty-btn qty-menos" type="button" aria-label="Quitar una unidad de <?= $nombre ?>">−</button>
          <span class="qty-num" aria-live="polite"><?= $enCarrito ?: 1 ?></span>
          <button class="qty-btn qty-mas" type="button" aria-label="Sumar una unidad de <?= $nombre ?>"<?= $enCarrito >= $stock ? ' disabled' : '' ?>>+</button>
        </div>
      </form>
    <?php endif; ?>
  </div>
  <?php
  return ob_get_clean();
}

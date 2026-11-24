<?php
$pageCss = 'estatica.css';
$pageTitle = 'Términos y condiciones';
$pageDescription = 'Términos y condiciones de uso del sitio y de compra de El Rápido: cuenta de usuario, pedidos y precios, delivery y uso del sitio.';
include 'includes/header.php';
require_once 'includes/iconos.php';
require_once 'includes/estatica_hero.php';

renderHeroEstatica([
  'pagina' => 'terminos', 'icono' => 'documento', 'kicker' => 'LO QUE TENÉS QUE SABER',
  'titulo' => 'Términos y', 'acento' => 'condiciones',
  'subtitulo' => 'Condiciones de uso del sitio y de compra.',
  'meta' => 'Última actualización: ' . date('d/m/Y'),
]);
?>

<div class="est-wrap">
  <div class="est-layout">
    <aside class="est-toc" aria-label="En esta página">
      <p class="est-toc-titulo">En esta página</p>
      <ol class="est-toc-lista" id="est-toc"></ol>
    </aside>

    <div class="est-contenido">

      <section class="est-sec" id="sobre" data-reveal data-toc="Sobre El Rápido">
        <header><span class="est-sec-ico"><?= icono('tienda', '', 22) ?></span><h2>Sobre El Rápido</h2></header>
        <p><?= htmlspecialchars(NEGOCIO_NOMBRE) ?> es un comercio ubicado en <?= htmlspecialchars(NEGOCIO_DIRECCION) ?>, Monte Caseros, Corrientes. Este sitio te permite ver nuestro catálogo de productos y hacer pedidos para delivery o retiro en el local.</p>
      </section>

      <section class="est-sec" id="cuenta" data-reveal data-toc="Cuenta de usuario">
        <header><span class="est-sec-ico"><?= icono('usuario', '', 22) ?></span><h2>Cuenta de usuario</h2></header>
        <p>Para algunas funciones (guardar direcciones, ver tu historial de pedidos, armar tu Compra Semanal) necesitás crear una cuenta con tus datos reales. Sos responsable de mantener tu contraseña segura y de toda actividad que se haga desde tu cuenta.</p>
      </section>

      <section class="est-sec" id="pedidos" data-reveal data-toc="Pedidos y precios">
        <header><span class="est-sec-ico"><?= icono('carrito', '', 22) ?></span><h2>Pedidos y precios</h2></header>
        <p>Los precios publicados incluyen los impuestos que correspondan y pueden actualizarse sin previo aviso para reflejar cambios de costos. El precio válido es siempre el que se muestra al momento de confirmar el pedido, no el que pudiste haber visto antes.</p>
        <p>La disponibilidad de stock se actualiza en tiempo real, pero en casos excepcionales un producto puede agotarse entre que lo agregaste al carrito y confirmaste el pedido; en ese caso te lo notificamos antes de procesar el cobro.</p>
      </section>

      <section class="est-sec" id="delivery" data-reveal data-toc="Delivery y horarios">
        <header><span class="est-sec-ico"><?= icono('camion', '', 22) ?></span><h2>Delivery y horarios</h2></header>
        <p>Los pedidos se atienden dentro del horario de <?= htmlspecialchars(NEGOCIO_HORARIO) ?>. Los tiempos de entrega son estimados y pueden variar según la demanda o las condiciones climáticas. Para más detalle, ver nuestras <a href="politicas.php" class="est-enlace">Políticas de la tienda</a>.</p>
      </section>

      <section class="est-sec" id="uso" data-reveal data-toc="Uso del sitio">
        <header><span class="est-sec-ico"><?= icono('escudo-check', '', 22) ?></span><h2>Uso del sitio</h2></header>
        <p>El contenido de este sitio (textos, imágenes, marca) pertenece a <?= htmlspecialchars(NEGOCIO_NOMBRE) ?> y no puede reproducirse con fines comerciales sin autorización. Nos reservamos el derecho de suspender el acceso a usuarios que hagan un uso indebido del sitio (pedidos falsos, intentos de fraude, etc.).</p>
      </section>

      <section class="est-sec" id="cambios" data-reveal data-toc="Cambios en estos términos">
        <header><span class="est-sec-ico"><?= icono('documento', '', 22) ?></span><h2>Cambios en estos términos</h2></header>
        <p>Podemos actualizar estos términos ocasionalmente para reflejar cambios en cómo operamos. La fecha de la última actualización siempre figura arriba de esta página.</p>
      </section>

      <?php renderCtaEstatica('¿Tenés alguna duda sobre estos términos?', 'Hola, tengo una consulta sobre los términos y condiciones.'); ?>
    </div>
  </div>
</div>

<script src="<?= htmlspecialchars(assetUrl('assets/js/estatica.js')) ?>" defer></script>
<?php include 'includes/footer.php'; ?>

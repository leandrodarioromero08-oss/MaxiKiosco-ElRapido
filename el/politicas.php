<?php
$pageCss = 'estatica.css';
$pageTitle = 'Políticas de la tienda';
$pageDescription = 'Políticas de El Rápido: cambios y devoluciones, errores en el pedido, delivery, medios de pago y privacidad de tus datos.';
include 'includes/header.php';
require_once 'includes/iconos.php';
require_once 'includes/estatica_hero.php';

renderHeroEstatica([
  'pagina' => 'politicas', 'icono' => 'escudo-check', 'kicker' => 'TU COMPRA, PROTEGIDA',
  'titulo' => 'Políticas de la', 'acento' => 'tienda',
  'subtitulo' => 'Cambios, devoluciones y garantía de tus compras.',
  'meta' => 'Última actualización: ' . date('d/m/Y'),
]);
?>

<div class="est-wrap">

  <!-- Lo esencial de un vistazo (todo sale del texto de abajo) -->
  <ul class="est-resumen" aria-label="Resumen">
    <li data-reveal><span class="est-resumen-ico"><?= icono('reloj', '', 22) ?></span><strong>24 horas</strong><span>para avisarnos si algo llegó mal</span></li>
    <li data-reveal><span class="est-resumen-ico"><?= icono('camion', '', 22) ?></span><strong>Todos los días</strong><span>delivery en Monte Caseros</span></li>
    <li data-reveal><span class="est-resumen-ico"><?= icono('tarjeta', '', 22) ?></span><strong>Pagás al recibir</strong><span>efectivo o tarjeta</span></li>
  </ul>

  <div class="est-layout">
    <aside class="est-toc" aria-label="En esta página">
      <p class="est-toc-titulo">En esta página</p>
      <ol class="est-toc-lista" id="est-toc"></ol>
    </aside>

    <div class="est-contenido">

      <section class="est-sec" id="cambios" data-reveal data-toc="Cambios y devoluciones">
        <header><span class="est-sec-ico"><?= icono('repetir', '', 22) ?></span><h2>Cambios y devoluciones</h2></header>
        <p>Si recibiste un producto en mal estado, vencido o distinto al que pediste, escribinos dentro de las <strong>24 horas</strong> de recibido tu pedido y lo solucionamos sin costo: te lo cambiamos por uno en buen estado o te devolvemos el importe pagado por ese producto.</p>
        <p>Por tratarse de un kiosco de alimentos, bebidas y artículos de consumo, <strong>no aceptamos devoluciones</strong> de productos ya abiertos o consumidos, salvo que el reclamo sea por un desperfecto del producto (por ejemplo, vencimiento o mal estado).</p>
      </section>

      <section class="est-sec" id="errores" data-reveal data-toc="Errores en el pedido">
        <header><span class="est-sec-ico"><?= icono('alerta', '', 22) ?></span><h2>Errores en el pedido</h2></header>
        <p>Si el error fue nuestro (te llevamos un producto distinto al pedido, o falta algo de tu compra), lo resolvemos sin cargo: te acercamos lo que falta o te devolvemos ese importe, según prefieras.</p>
      </section>

      <section class="est-sec" id="delivery" data-reveal data-toc="Delivery">
        <header><span class="est-sec-ico"><?= icono('camion', '', 22) ?></span><h2>Delivery</h2></header>
        <p>Hacemos delivery todos los días dentro de Monte Caseros en el horario de <?= htmlspecialchars(NEGOCIO_HORARIO) ?>. El tiempo de entrega puede variar según la zona, el clima o la cantidad de pedidos en curso.</p>
        <ul>
          <li>El envío es sin cargo dentro del centro de la ciudad.</li>
          <li>Para zonas más alejadas puede aplicarse un recargo, que siempre se muestra antes de confirmar el pedido — nunca se cobra nada sin que lo veas primero.</li>
          <li>Si no hay nadie para recibir el pedido en la dirección indicada, nos contactamos por WhatsApp para coordinar una reentrega.</li>
        </ul>
      </section>

      <section class="est-sec" id="pago" data-reveal data-toc="Medios de pago">
        <header><span class="est-sec-ico"><?= icono('tarjeta', '', 22) ?></span><h2>Medios de pago</h2></header>
        <p>Podés abonar en efectivo o con tarjeta al momento de recibir tu pedido, ya sea por delivery o retirándolo en el local.</p>
      </section>

      <section class="est-sec" id="privacidad" data-reveal data-toc="Privacidad de tus datos">
        <header><span class="est-sec-ico"><?= icono('candado', '', 22) ?></span><h2>Privacidad de tus datos</h2></header>
        <p>Los datos que nos dejás al registrarte (nombre, teléfono, direcciones) se usan únicamente para gestionar tus pedidos y comunicarnos con vos. No compartimos tu información con terceros ni la usamos con fines distintos a la operación de la tienda.</p>
      </section>

      <?php renderCtaEstatica('¿Tenés un reclamo o una consulta puntual?', 'Hola, tengo una consulta sobre un pedido.'); ?>
    </div>
  </div>
</div>

<script src="<?= htmlspecialchars(assetUrl('assets/js/estatica.js')) ?>" defer></script>
<?php include 'includes/footer.php'; ?>

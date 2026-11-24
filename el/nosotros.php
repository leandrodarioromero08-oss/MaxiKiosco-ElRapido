<?php

$pageCss = 'nosotros.css';
$pageCssExtra = ['nosotros-animacion.css'];
$pageTitle = 'Nosotros: quiénes somos';
$pageDescription = 'Conocé a Maxikiosco El Rápido: más de 5 años en el centro de Monte Caseros con bebidas, golosinas, snacks, productos de almacén y delivery todos los días.';
include 'includes/header.php';
require_once 'includes/iconos.php';

?>

<!-- ══════════════════════════════════════ HERO ══ -->
<section class="section-hero-nosotros">
  <div class="hero-nosotros">
    <div class="hero-left">
      <h1>NOSOTROS</h1>
      <p>Conocé más sobre Maxikiosco El Rápido</p>

      <div class="quienes-bloque">
        <div class="quienes-icon"><?= icono('tienda', '', 28) ?></div>
        <div class="quienes-content">
          <h2>Quiénes somos</h2>
          <p>Somos Maxikiosco El Rápido, un negocio ubicado en el centro de Monte Caseros que desde hace más de 5 años ofrece bebidas, golosinas, snacks, productos de almacén y servicio de delivery.</p>
          <p>Trabajamos todos los días para brindar una atención rápida, cercana y confiable a nuestros clientes.</p>
        </div>
      </div>

      <div class="stats-tira" data-reveal>
        <div class="stat-item">
          <div class="stat-numero"><span data-contar-hasta="5">5</span>+</div>
          <div class="stat-label">Años en Monte Caseros</div>
        </div>
        <div class="stat-item">
          <div class="stat-numero" aria-hidden="true"><?= icono('bicicleta', '', 28) ?></div>
          <div class="stat-label">Delivery todos los días</div>
        </div>
        <div class="stat-item">
          <div class="stat-numero" aria-hidden="true"><?= icono('ubicacion', '', 28) ?></div>
          <div class="stat-label">Centro de la ciudad</div>
        </div>
      </div>
    </div>

    <div class="hero-imagen">
      <?php renderFotoHeroCiclo(false); // sin el recuadro oscuro detrás de la foto (igual que el hero de Inicio) ?>
    </div>
  </div>
  
</section>

<!-- ══════════════════════════════════════ CARDS ══ -->
<section class="cards-section">
  <div class="cards-section-inner">

    <!-- Nuestra forma de trabajar -->
    <div class="info-card info-card--anim" id="card-forma-de-trabajar" data-reveal>
      <div class="card-contenido">
        <div class="card-header">
          <div class="card-icon"><?= icono('rayo', '', 24) ?></div>
          <h2>Nuestra forma de trabajar</h2>
        </div>
        <p>En El Rápido nos enfocamos en que cada compra sea <span class="highlight">simple y ágil</span>. Sabemos que nuestros clientes buscan rapidez, especialmente en horarios de alta demanda y fines de semana.</p>
        <p>Actualmente atendemos tanto en el local como a través de pedidos por delivery, <span class="highlight">cubriendo la zona céntrica de la ciudad.</span></p>
        <p class="card-pista" aria-hidden="true">
          <span class="card-pista-ico"><svg viewBox="0 0 12 12" aria-hidden="true"><path d="M3 1.4 10.2 6 3 10.6z"/></svg></span>
          Tocá para ver la animación
        </p>
      </div>
      <div class="card-escenario" aria-hidden="true"></div>
      <button type="button" class="card-play" aria-label="Reproducir la animación de Nuestra forma de trabajar" hidden></button>
    </div>

    <!-- Evolución digital -->
    <div class="info-card info-card--anim" id="card-evolucion-digital" data-reveal>
      <div class="card-contenido">
        <div class="card-header">
          <div class="card-icon"><?= icono('movil', '', 24) ?></div>
          <h2>Evolución digital</h2>
        </div>
        <p>Con el crecimiento del negocio, surgió la necesidad de mejorar la forma en que gestionamos los pedidos.</p>
        <p>Antes los pedidos llegaban principalmente por <span class="highlight">WhatsApp o teléfono</span>, lo que podía generar demoras, errores o pérdidas de información.</p>
        <p>Por eso creamos esta tienda online, que les permite a nuestros clientes:</p>
        <ul class="check-list">
          <li><?= icono('check', 'check-ico', 14) ?> Ver productos y precios actualizados</li>
          <li><?= icono('check', 'check-ico', 14) ?> Realizar pedidos de forma rápida</li>
          <li><?= icono('check', 'check-ico', 14) ?> Evitar demoras y errores</li>
        </ul>
        <p class="card-pista" aria-hidden="true">
          <span class="card-pista-ico"><svg viewBox="0 0 12 12" aria-hidden="true"><path d="M3 1.4 10.2 6 3 10.6z"/></svg></span>
          Tocá para ver la animación
        </p>
      </div>
      <div class="card-escenario" aria-hidden="true"></div>
      <button type="button" class="card-play" aria-label="Reproducir la animación de Evolución digital" hidden></button>
    </div>

    <!-- Nuestro objetivo -->
    <div class="info-card info-card--anim" id="card-nuestro-objetivo" data-reveal>
      <div class="card-contenido">
        <div class="card-header">
          <div class="card-icon"><?= icono('cohete', '', 24) ?></div>
          <h2>Nuestro objetivo</h2>
        </div>
        <p>Seguimos mejorando la experiencia de compra con tecnología que nos permite:</p>
        <ul class="check-list">
          <li><?= icono('check', 'check-ico', 14) ?> Organizar mejor los pedidos</li>
          <li><?= icono('check', 'check-ico', 14) ?> Reducir tiempos de espera</li>
          <li><?= icono('check', 'check-ico', 14) ?> Brindar un servicio más eficiente</li>
        </ul>
        <p style="margin-top:14px;">Todo esto sin perder lo más importante:<br><?= icono('flecha-der', 'note-icono', 15) ?> <span class="highlight">la atención cercana de siempre.</span></p>
        <p class="card-pista" aria-hidden="true">
          <span class="card-pista-ico"><svg viewBox="0 0 12 12" aria-hidden="true"><path d="M3 1.4 10.2 6 3 10.6z"/></svg></span>
          Tocá para ver la animación
        </p>
      </div>
      <div class="card-escenario" aria-hidden="true"></div>
      <button type="button" class="card-play" aria-label="Reproducir la animación de Nuestro objetivo" hidden></button>
    </div>

    <!-- Compromiso con nuestros clientes -->
    <div class="info-card info-card--anim" id="card-compromiso-clientes" data-reveal>
      <div class="card-contenido">
        <div class="card-header">
          <div class="card-icon"><?= icono('corazon', '', 24) ?></div>
          <h2>Compromiso con nuestros clientes</h2>
        </div>
        <p>En El Rápido buscamos seguir creciendo junto a nuestros clientes, <span class="highlight">adaptándonos a sus necesidades</span> y ofreciendo soluciones prácticas para el día a día.</p>
        <p class="card-pista" aria-hidden="true">
          <span class="card-pista-ico"><svg viewBox="0 0 12 12" aria-hidden="true"><path d="M3 1.4 10.2 6 3 10.6z"/></svg></span>
          Tocá para ver la animación
        </p>
      </div>
      <div class="card-escenario" aria-hidden="true"></div>
      <button type="button" class="card-play" aria-label="Reproducir la animación de Compromiso con nuestros clientes" hidden></button>
    </div>

  </div>
</section>

<script src="<?= htmlspecialchars(assetUrl('assets/js/reveal.js')) ?>" defer></script>
<script src="<?= htmlspecialchars(assetUrl('assets/js/nosotros.js')) ?>" defer></script>
<script src="<?= htmlspecialchars(assetUrl('assets/js/evolucion-digital.js')) ?>" defer></script>
<script src="<?= htmlspecialchars(assetUrl('assets/js/forma-de-trabajar.js')) ?>" defer></script>
<script src="<?= htmlspecialchars(assetUrl('assets/js/nuestro-objetivo.js')) ?>" defer></script>
<script src="<?= htmlspecialchars(assetUrl('assets/js/compromiso-clientes.js')) ?>" defer></script>

<?php include ('includes/footer.php'); ?>
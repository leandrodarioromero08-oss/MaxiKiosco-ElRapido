<?php

$pageCss = 'index.css';
$pageCssExtra = ['promo-publico.css', 'tarjeta-producto.css'];
$pageIntro = true;     // intro cinematográfica: pantalla del logo + iframe (includes/intro_gate.php, assets/js/intro.js). Una vez por sesión.
$pageChatGuia = true; // resplandor que señala el botón del asistente (assets/js/chatbot-guia.js)
require_once 'includes/config.php'; // las constantes del negocio se usan acá abajo, antes de que header.php las cargue
$pageTitle = 'Maxikiosco en Monte Caseros: bebidas, snacks y delivery';
$pageDescription = 'Maxikiosco El Rápido, en el centro de Monte Caseros (Corrientes). Bebidas, golosinas, snacks, cigarrillos y recargas con delivery todos los días o retiro en el local. Mirá las promos de hoy.';
// Datos estructurados para buscadores (schema.org). No se publica teléfono porque en includes/config.php sigue siendo un dato de ejemplo.
$pageJsonLd = (function () {
  $datos = [
    '@context' => 'https://schema.org',
    '@type'    => 'ConvenienceStore',
    'name'     => NEGOCIO_NOMBRE,
    'url'      => rtrim(SITE_URL, '/') . '/',
    'image'    => rtrim(SITE_URL, '/') . '/assets/img/hero-foto.jpg',
    'address'  => [
      '@type'           => 'PostalAddress',
      'streetAddress'   => trim(preg_replace('/,\s*Monte Caseros\s*$/i', '', NEGOCIO_DIRECCION)),
      'addressLocality' => 'Monte Caseros',
      'addressRegion'   => 'Corrientes',
      'addressCountry'  => 'AR',
    ],
    'geo' => ['@type' => 'GeoCoordinates', 'latitude' => (float) NEGOCIO_LAT, 'longitude' => (float) NEGOCIO_LNG],
  ];
  // "08:00 a 01:00 hs" → apertura y cierre (si el formato cambia, simplemente no se publica el horario)
  if (preg_match('/(\d{1,2}:\d{2})\s*a\s*(\d{1,2}:\d{2})/', NEGOCIO_HORARIO, $h)) {
    $datos['openingHoursSpecification'] = [[
      '@type' => 'OpeningHoursSpecification',
      'dayOfWeek' => ['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'],
      'opens' => $h[1], 'closes' => $h[2],
    ]];
  }
  return $datos;
})();
include 'includes/header.php';
require_once 'includes/compra_semanal.php';
require_once 'includes/iconos.php';

require_once 'includes/promociones_funciones.php';
require_once 'includes/tarjeta_producto.php';

require_once 'includes/chatbot_logica.php'; // solo funciones: se reutiliza chatbotEstadoHorario()
$estadoHorario = function_exists('chatbotEstadoHorario') ? chatbotEstadoHorario() : null;
$estaAbierto   = $estadoHorario === null ? true : (bool) $estadoHorario['abierto'];
$textoEstado   = $estadoHorario === null ? 'Horario de atención' : ($estaAbierto ? 'Abierto ahora' : 'Cerrado ahora');

$pdo = obtenerConexion();
$categoriasHome = obtenerCategoriasPublico($pdo);

$comprasSemanalesHome = [];
if ($logueado) {
  $comprasSemanalesHome = obtenerComprasSemanales($pdo, (int) $_SESSION['usuario']['id']);
}

// Recalcula estados (Programada/Activa/Finalizada) y trae el banner activo
// de mayor prioridad para cada posición (1, 2 y 3). Si el admin no cargó
// ninguna promoción para una posición, esa posición simplemente no se muestra.
// "Lo más pedido": los 4 productos destacados con stock (mismo criterio que el orden "Más vendidos" del catálogo)
$productosDestacados = $pdo->query('SELECT id, nombre, precio, descuento_pct, stock, emoji, imagen FROM productos WHERE activo = 1 AND destacado = 1 AND stock > 0 ORDER BY id LIMIT 4')->fetchAll();

actualizarEstadosPromociones($pdo);
$bannersHome = obtenerBannersActivosPorPosicion($pdo);

// Datos para el modal de detalle (promo-publico.js): solo lo mínimo necesario,
// con la fecha ya formateada en español.
$bannersHomeJs = array_map(function ($b) {
  return [
    'id' => (int) $b['id'], 'tipo' => $b['tipo'], 'titulo' => $b['titulo'],
    'descripcion' => $b['descripcion'], 'color_banner' => $b['color_banner'], 'color_hex' => $b['color_hex'],
    'precio_promo' => $b['precio_promo'] !== null ? (float) $b['precio_promo'] : null,
    'descuento_pct' => $b['descuento_pct'] !== null ? (int) $b['descuento_pct'] : null,
    'fecha_fin_fmt' => date('d/m/Y', strtotime($b['fecha_fin'])),
    'productos' => array_map(fn($p) => ['id' => (int) $p['id'], 'nombre' => $p['nombre'], 'precio' => (float) $p['precio'], 'emoji' => $p['emoji'], 'imagen' => $p['imagen'], 'stock' => (int) $p['stock']], $b['productos']),
  ];
}, $bannersHome);
?>

<!-- Hero + categorías comparten el fondo de rayas, que se desvanece hacia abajo -->
<div class="inicio-cabecera">

<!-- ======= HERO ======= -->

<section class="hero">
  <div class="hero-inner">
    <div class="hero-texto">
      <span class="hero-kicker">MAXIKIOSCO EL RÁPIDO — MONTE CASEROS</span>
      <h1 class="hero-titulo">
        <span class="hero-titulo-chica">Todo lo que necesitás,</span>
        <span class="hero-titulo-grande">RÁPIDO <span class="hero-acento">Y CERCA</span></span>
      </h1>
      <p class="hero-subtitulo">Bebidas, snacks, cigarrillos, recargas y mucho más — a un pedido de distancia, con la atención de siempre.</p>
      <div class="hero-botones">
        <?php if ($logueado): ?>
          <a href="<?= htmlspecialchars(urlWhatsapp()) ?>" class="btn-wsp" target="_blank" rel="noopener" aria-label="Pedir por WhatsApp (se abre en una pestaña nueva)">
            <?= icono('mensaje', '', 16) ?> Pedir por WhatsApp
          </a>
        <?php else: ?>
          <a href="login.php" class="btn-wsp">
            <?= icono('usuario', '', 16) ?> Iniciá sesión para comprar
          </a>
        <?php endif; ?>
        <a href="#ubicacion" class="btn-ubicacion">
          <?= icono('ubicacion', '', 16) ?> Ver ubicación
        </a>
      </div>
      <div class="hero-estado">
        <span class="estado-dot<?= $estaAbierto ? '' : ' estado-dot--cerrado' ?>" aria-hidden="true"></span>
        <span><b><?= htmlspecialchars($textoEstado) ?></b></span>
        <span class="hero-estado-sep"></span>
        <span><?= htmlspecialchars(NEGOCIO_HORARIO) ?></span>
      </div>
      <!-- Datos de entrega a la vista, sin tener que buscarlos -->
      <ul class="hero-datos" aria-label="Datos de entrega">
        <li><?= icono('camion', '', 16) ?> Delivery <?= htmlspecialchars(formatoPrecio((float) RECARGO_DELIVERY)) ?></li>
        <li><?= icono('casa', '', 16) ?> Retiro gratis</li>
        <li><?= icono('tarjeta', '', 16) ?> Pagás al recibir</li>
      </ul>
    </div>

    <div class="hero-imagen">
      <div class="foto-hero foto-hero--ciclo" id="fotoHero">
        <!-- Pausa de la foto que cambia sola (WCAG 2.2.2); la lógica está en global.js -->
        <button type="button" class="foto-pausa" id="fotoPausa" aria-pressed="false" aria-label="Pausar el cambio automático de foto día y noche">
          <span class="foto-pausa-ico foto-pausa-ico--pausa" aria-hidden="true"><svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor"><rect x="6" y="5" width="4" height="14" rx="1"/><rect x="14" y="5" width="4" height="14" rx="1"/></svg></span>
          <span class="foto-pausa-ico foto-pausa-ico--play" aria-hidden="true"><svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor"><path d="M8 5.5v13a1 1 0 0 0 1.5.86l10.5-6.5a1 1 0 0 0 0-1.72L9.5 4.64A1 1 0 0 0 8 5.5Z"/></svg></span>
        </button>
        <div class="foto-hero-marco foto-hero-marco--activa">
          <picture><source srcset="assets/img/hero-foto-640.webp 640w, assets/img/hero-foto.webp 1122w" sizes="(max-width: 900px) 100vw, 520px" type="image/webp"><img src="assets/img/hero-foto.jpg" srcset="assets/img/hero-foto-640.jpg 640w, assets/img/hero-foto.jpg 1122w" sizes="(max-width: 900px) 100vw, 520px" alt="Frente del Maxikiosco El Rápido de noche" class="foto-hero-img" fetchpriority="high" decoding="async"></picture>
          <span class="foto-hero-etiqueta"><?= icono('luna', '', 13) ?> De noche</span>
          <div class="foto-hero-duotono"></div>
        </div>
        <div class="foto-hero-marco">
          <picture><source srcset="assets/img/hero-foto-2-640.webp 640w, assets/img/hero-foto-2.webp 1122w" sizes="(max-width: 900px) 100vw, 520px" type="image/webp"><img src="assets/img/hero-foto-2.jpg" srcset="assets/img/hero-foto-2-640.jpg 640w, assets/img/hero-foto-2.jpg 1122w" sizes="(max-width: 900px) 100vw, 520px" alt="Frente del Maxikiosco El Rápido de día" class="foto-hero-img" loading="lazy" decoding="async"></picture>
          <span class="foto-hero-etiqueta"><?= icono('sol', '', 13) ?> De día</span>
          <div class="foto-hero-duotono"></div>
        </div>
        <div class="chip-flotante">
          <span class="chip-icono"><?= icono('tienda', '', 18) ?></span>
          <div>
            <div class="chip-num">+5</div>
            <div class="chip-txt">años en el<br>centro de Monte Caseros</div>
          </div>
        </div>
      </div>
    </div>

  </div>

</section>

<!-- ======= CATEGORÍAS ======= -->
<section class="categorias-section" aria-label="Categorías">
  <div class="categorias-inner">
    <?php foreach ($categoriasHome as $cat): ?>
      <a href="productos.php?cat=<?= urlencode($cat['slug']) ?>" class="categoria-item">
        <div class="categoria-circulo"><?= iconoCategoria($cat, 44) ?></div>
        <span class="categoria-nombre"><?= htmlspecialchars($cat['nombre']) ?></span>
      </a>
    <?php endforeach; ?>
  </div>
</section>

</div>


<!-- ======= PROMOCIONES DESTACADAS ======= -->
<section class="seccion">
  <div class="seccion-header">
    <h2 class="seccion-titulo">Promociones Destacadas</h2>
    <a href="productos.php" class="ver-todas" aria-label="Ver todas las promociones y productos">Ver todas <?= icono('flecha-der', '', 14) ?></a>
  </div>
  
  <div class="promos-grid">

    <?php if (empty($bannersHome)): ?>
      <p style="color:var(--gris-texto);">Por ahora no hay promociones activas. ¡Volvé pronto!</p>
    <?php else: ?>
      <?php foreach ($bannersHome as $banner): ?>
        <?php if ($logueado): ?>
          <div class="promo-clickeable-wrap" data-abrir-promo="<?= (int) $banner['id'] ?>" data-reveal>
            <?= renderBannerPromo($banner, $banner['productos'], 'assets/img/productos/', false) ?>
          </div>
        <?php else: ?>
          <div data-reveal>
            <?= renderBannerPromo($banner, $banner['productos'], 'assets/img/productos/', true) ?>
          </div>
        <?php endif; ?>
      <?php endforeach; ?>
    <?php endif; ?>

  </div>
</section>

<?php if (!empty($productosDestacados)): ?>
<!-- ======= LO MÁS PEDIDO ======= -->
<section class="seccion" aria-labelledby="t-pedidos">
  <div class="seccion-header">
    <h2 class="seccion-titulo" id="t-pedidos">Lo más pedido</h2>
    <a href="productos.php" class="ver-todas" aria-label="Ver todos los productos">Ver catálogo <?= icono('flecha-der', '', 14) ?></a>
  </div>
  <div class="rail">
    <?php foreach ($productosDestacados as $pd): ?>
      <?= renderTarjetaProducto($pd, ['titulo' => 'h3', 'mostrarPromo' => false, 'volver' => 'index.php']) ?>
    <?php endforeach; ?>
  </div>
</section>
<?php endif; ?>

<!-- ======= CÓMO PEDIR ======= -->
<section class="seccion" aria-labelledby="t-como">
  <div class="seccion-header"><h2 class="seccion-titulo" id="t-como">Cómo pedir</h2></div>
  <ol class="pasos">
    <li class="paso" data-reveal><span class="paso-num" aria-hidden="true">1</span><span class="paso-ico"><?= icono('cuadricula', '', 26) ?></span><h3>Elegí lo que necesitás</h3><p>Recorré el catálogo o las <strong>promos del día</strong> y sumá productos con un toque.</p></li>
    <li class="paso" data-reveal><span class="paso-num" aria-hidden="true">2</span><span class="paso-ico"><?= icono('carrito', '', 26) ?></span><h3>Confirmá tu pedido</h3><p>Revisá el carrito y elegí <strong>delivery (<?= htmlspecialchars(formatoPrecio((float) RECARGO_DELIVERY)) ?>)</strong> o <strong>retiro gratis</strong>.</p></li>
    <li class="paso" data-reveal><span class="paso-num" aria-hidden="true">3</span><span class="paso-ico"><?= icono('camion', '', 26) ?></span><h3>Recibí o retirá</h3><p><strong>Pagás al recibir.</strong> Atendemos todos los días de <?= htmlspecialchars(NEGOCIO_HORARIO) ?>.</p></li>
  </ol>
</section>

<!-- ======= GRID INFERIOR ======= -->
<section class="grid-inferior">

  <?php if ($logueado): ?>
  <!-- Compra Semanal -->
  <div class="compras-card" data-reveal>
    <div class="compras-header">
      <h2 class="compras-titulo">Compra Semanal</h2>
      <?php if (!empty($comprasSemanalesHome)): ?>
        <a href="mi-cuenta.php?seccion=compra-semanal" class="ver-todas" aria-label="Ver todas mis compras semanales">Ver todas</a>
      <?php endif; ?>
    </div>

    <?php if (empty($comprasSemanalesHome)): ?>
      <div class="compras-vacia-home">
        <div class="compras-vacia-home-icono"><?= icono('calendario', '', 36) ?></div>
        <p class="compras-vacia-home-texto">Todavía no creaste tu Compra Semanal.<br>Guardá tus productos habituales y repetí tu compra con un clic. Podés tener hasta 3 listas.</p>
        <a href="compra_semanal_editar.php" class="btn-crear-primera-compra-home">+ Crear mi primera compra</a>
      </div>
    <?php else: ?>
      <?php foreach ($comprasSemanalesHome as $lista): ?>
        <div class="compra-item">
          <div class="compra-img"><?= icono('calendario', '', 22) ?></div>
          <div class="compra-info">
            <div class="compra-nombre"><?= htmlspecialchars($lista['nombre']) ?></div>
            <div class="compra-cant"><?= (int) $lista['cantidad_items'] ?> producto<?= $lista['cantidad_items'] === 1 ? '' : 's' ?> · Actualizada <?= lcfirst(tiempoRelativo($lista['actualizado_en'])) ?></div>
          </div>
        </div>
      <?php endforeach; ?>
      <a href="mi-cuenta.php?seccion=compra-semanal" class="btn-guardar-compra" style="display:block; text-align:center;">
        VER MIS COMPRAS <?= icono('calendario', '', 14) ?> (<?= count($comprasSemanalesHome) ?>/3)
      </a>
    <?php endif; ?>
  </div>
  <?php else: ?>
  <!-- Invitación a iniciar sesión -->
  <div class="login-cta-card" data-reveal>
    <h2 class="login-cta-titulo">INICIÁ SESIÓN PARA COMPRAR</h2>
    <div class="login-cta-icono"><?= icono('carrito', '', 32) ?></div>
    <p class="login-cta-texto">Accedé a todas las promociones,<br>hacé tus pedidos y recibí en minutos.</p>
    <a href="login.php" class="btn-login-cta">Iniciar sesión</a>
    <p class="login-cta-alt">¿No tenés cuenta? <a href="registro.php">Registrate</a></p>
  </div>
  <?php endif; ?>

    <!-- Ubicación -->
  <div class="ubicacion-card" id="ubicacion" data-reveal>
    <!-- Fachada: el iframe de Google se crea recién al tocar el botón (mejoras.js), no al abrir la página -->
    <div class="mapa-embed" data-src="https://www.google.com/maps?q=<?= urlencode(NEGOCIO_DIRECCION . ', Monte Caseros, Corrientes, Argentina') ?>&output=embed">
      <svg viewBox="0 0 440 220" width="100%" height="220" preserveAspectRatio="xMidYMid slice" role="img" aria-label="Mapa de la ubicación (ilustrativo)" style="display:block;background:var(--crema)">
        <rect width="440" height="220" fill="var(--crema)"/>
        <g stroke="var(--crema-linea)" stroke-width="14" fill="none" stroke-linecap="round"><path d="M-10 60H450M-10 150H450M90-10V230M250-10V230M360-10V230"/></g>
        <g stroke="var(--superficie)" stroke-width="10" fill="none" stroke-linecap="round"><path d="M-10 60H450M-10 150H450M90-10V230M250-10V230M360-10V230"/></g>
        <rect x="105" y="72" width="130" height="64" rx="6" fill="var(--superficie-2)"/><rect x="265" y="72" width="80" height="64" rx="6" fill="var(--superficie-2)"/>
        <g transform="translate(250 96)"><path d="M0 0c0 0-16-15-16-27a16 16 0 0 1 32 0c0 12-16 27-16 27z" fill="#D91F26"/><circle cx="0" cy="-27" r="6" fill="#FFD600"/></g>
      </svg>
      <button type="button" class="mapa-facade" data-mapa-cargar><?= icono('ubicacion', '', 18) ?> Ver mapa interactivo</button>
      <noscript>
        <iframe src="https://www.google.com/maps?q=<?= urlencode(NEGOCIO_DIRECCION . ', Monte Caseros, Corrientes, Argentina') ?>&output=embed" width="100%" height="220" style="border:0;" loading="lazy" title="Ubicación de <?= htmlspecialchars(NEGOCIO_NOMBRE) ?>"></iframe>
      </noscript>
    </div>
    <div class="ubicacion-info">
      <div class="ubicacion-nombre"><?= htmlspecialchars(NEGOCIO_NOMBRE) ?></div>
      <div class="ubicacion-dir"><?= htmlspecialchars(NEGOCIO_DIRECCION) ?></div>
      <div class="ubicacion-dir">Monte Caseros, Corrientes</div>
      <a href="<?= htmlspecialchars(NEGOCIO_MAPS_URL) ?>" target="_blank" rel="noopener" class="btn-como-llegar" aria-label="<?= $logueado ? 'Cómo llegar' : 'Ver ubicación' ?> en Google Maps (se abre en una pestaña nueva)">
        <?php if ($logueado): ?>
          CÓMO LLEGAR <?= icono('flecha-der', '', 16) ?>
        <?php else: ?>
          VER UBICACIÓN
        <?php endif; ?>
      </a>
      <div class="ubicacion-horario">
        <?= icono('reloj', '', 14) ?> <strong>Horarios:</strong> Todos los días de <?= htmlspecialchars(NEGOCIO_HORARIO) ?>
      </div>
    </div>
  </div>

</section>




<?php include ('includes/promo_modal.php'); ?>

<script>
  window.USUARIO_LOGUEADO = <?= $logueado ? 'true' : 'false' ?>;
  window.PROMOS_DATA = <?= json_encode($bannersHomeJs) ?>;
</script>
<script src="<?= htmlspecialchars(assetUrl('assets/js/reveal.js')) ?>" defer></script>
<script src="<?= htmlspecialchars(assetUrl('assets/js/promo-publico.js')) ?>" defer></script>
<?php include ('includes/footer.php'); ?>
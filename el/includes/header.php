<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/carrito.php';
require_once __DIR__ . '/funciones.php';
require_once __DIR__ . '/iconos.php';
require_once __DIR__ . '/admin_bar.php';
require_once __DIR__ . '/assets.php';

$logueado = usuarioLogueado();
$nombreUsuario = $logueado ? $_SESSION['usuario']['nombre'] : null;
$paginaActual = basename($_SERVER['PHP_SELF'], '.php');
// Si la página ya calculó el carrito (carrito.php lo hace antes de incluir este header) se reutiliza
// ese cálculo en vez de repetir las consultas. Si no, se calcula en modo solo lectura: la limpieza
// con avisos la hace carrito.php.
$resumenCarrito = $resumenCarrito ?? carritoDetalle(obtenerConexion(), false);

// ── SEO (Paso 11) ─────────────────────────────────────────
// Cada página puede definir, ANTES de incluir este header:
//   $pageTitle        título corto de la página (se le agrega " | El Rápido")
//   $pageDescription  descripción para buscadores y redes (≈ 120-160 caracteres)
//   $pageRobots       'index,follow' (por defecto) o 'noindex,follow' para páginas privadas / sin valor SEO
//   $pageCanonical    ruta canónica relativa al sitio (ej. 'productos.php?cat=bebidas'); por defecto la página actual sin parámetros
//   $pageOgImage      imagen para redes, ruta relativa al sitio (por defecto la foto de portada)
//   $pageJsonLd       arreglo PHP con datos estructurados schema.org (opcional)
$seoTitulo = !empty($pageTitle) ? $pageTitle . ' | ' . SITE_NAME : SITE_NAME . ' - Maxi Kiosco en Monte Caseros';
$seoDescripcion = $pageDescription
  ?? 'Maxikiosco El Rápido en Monte Caseros, Corrientes: bebidas, snacks, golosinas, cigarrillos y recargas. Pedí online con delivery o retiro en el local.';
$seoRobots = $pageRobots ?? 'index,follow';
$seoSitio  = rtrim(SITE_URL, '/');
if (isset($pageCanonical)) {
  $seoCanonical = $seoSitio . '/' . ltrim($pageCanonical, '/');
} else {
  $seoCanonical = $seoSitio . '/' . ($paginaActual === 'index' ? '' : $paginaActual . '.php');
}
$seoImagen = $seoSitio . '/' . ltrim($pageOgImage ?? 'assets/img/hero-foto.jpg', '/');
$seoCantidadCarrito = (int) $resumenCarrito['cantidad_total'];

// Barra de administración: solo si hay un admin con sesión abierta (null para los clientes)
$barraAdmin = barraAdminDatos(obtenerConexion());
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title><?= htmlspecialchars($seoTitulo) ?></title>
  <meta name="description" content="<?= htmlspecialchars($seoDescripcion) ?>" />
  <meta name="robots" content="<?= htmlspecialchars($seoRobots) ?>" />
  <link rel="canonical" href="<?= htmlspecialchars($seoCanonical) ?>" />
  <meta name="theme-color" content="#D91F26" />
  <meta name="color-scheme" content="light dark" />
  <!-- Modo oscuro: se aplica ANTES de pintar la página para que no parpadee en blanco.
       Usa la opción guardada por el cliente; si nunca eligió, la del dispositivo. -->
  <script>
    (function () {
      try {
        var t = localStorage.getItem('tema');
        if (t !== 'dark' && t !== 'light') {
          t = window.matchMedia && matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
        }
        document.documentElement.setAttribute('data-theme', t);
      } catch (e) {}
    })();
  </script>
  <?php if (!empty($pageIntro)): ?>
  <!-- Intro: se decide ANTES de pintar para que no se vea el sitio un instante. Una vez por sesión; ?intro=1 la fuerza
       (botón "Ver intro" del footer). Con "reducir movimiento" activado no arranca sola. -->
  <script>
    (function () {
      try {
        var forzada = /[?&]intro=1(&|$)/.test(location.search);
        if (!forzada && (sessionStorage.getItem('elr_intro') || (window.matchMedia && matchMedia('(prefers-reduced-motion: reduce)').matches))) return;
        document.documentElement.classList.add('intro-on');
      } catch (e) {}
    })();
  </script>
  <?php endif; ?>
  <!-- Open Graph / redes sociales -->
  <meta property="og:type" content="website" />
  <meta property="og:site_name" content="<?= htmlspecialchars(SITE_NAME) ?>" />
  <meta property="og:locale" content="es_AR" />
  <meta property="og:title" content="<?= htmlspecialchars($seoTitulo) ?>" />
  <meta property="og:description" content="<?= htmlspecialchars($seoDescripcion) ?>" />
  <meta property="og:url" content="<?= htmlspecialchars($seoCanonical) ?>" />
  <meta property="og:image" content="<?= htmlspecialchars($seoImagen) ?>" />
  <meta name="twitter:card" content="summary_large_image" />
  <meta name="twitter:title" content="<?= htmlspecialchars($seoTitulo) ?>" />
  <meta name="twitter:description" content="<?= htmlspecialchars($seoDescripcion) ?>" />
  <meta name="twitter:image" content="<?= htmlspecialchars($seoImagen) ?>" />
  <!-- Favicon -->
  <link rel="icon" type="image/png" href="assets/img/icono-el-rapido.png" />
  <link rel="apple-touch-icon" href="assets/img/apple-touch-icon.png" />
  <!-- Modo app (PWA): ver manifest.webmanifest, sw.js y assets/js/pwa.js -->
  <link rel="manifest" href="manifest.webmanifest" />
  <meta name="mobile-web-app-capable" content="yes" />
  <meta name="apple-mobile-web-app-capable" content="yes" />
  <meta name="apple-mobile-web-app-title" content="El Rápido" />
  <meta name="apple-mobile-web-app-status-bar-style" content="default" />
  <?php if (!empty($pageJsonLd)): ?>
  <script type="application/ld+json"><?= json_encode($pageJsonLd, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
  <?php endif; ?>
  <!-- Fuentes de Google: la conexión se abre ya, pero la hoja NO bloquea el render (ver más abajo) -->
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <?php
  // Todas las hojas de estilo van juntas en UN solo archivo minificado (ver includes/assets.php).
  // El orden es el mismo de siempre: la cascada no cambia. modo-oscuro.css y mejoras.css van al final
  // porque sus estilos pisan a los de cada página.
  $hojasCss = ['assets/css/global.css', 'assets/css/chatbot.css'];
  if ($barraAdmin) $hojasCss[] = 'assets/css/admin-bar.css';
  if (!empty($pageCss)) $hojasCss[] = 'assets/css/' . $pageCss;
  foreach (($pageCssExtra ?? []) as $cssExtra) $hojasCss[] = 'assets/css/' . $cssExtra;
  array_push($hojasCss, 'assets/css/modo-oscuro.css', 'assets/css/mejoras.css', 'assets/css/movimiento.css', 'assets/css/intro.css');
  if ($logueado) $hojasCss[] = 'assets/css/notificaciones.css';
  echo cssLinks($hojasCss);
  ?>
  <!-- Tipografías de Google: carga NO bloqueante. Con media="print" el navegador no frena el pintado de la página
       esperando las fuentes; al terminar de bajar pasa a "all" y el texto cambia de tipografía sin saltos de
       diseño (font-display: swap). Sin JavaScript, el <noscript> la carga de la forma normal. -->
  <link rel="preload" as="style" href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Montserrat:wght@400;500;600;700;800;900&display=swap" />
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Montserrat:wght@400;500;600;700;800;900&display=swap" media="print" onload="this.media='all'" />
  <noscript><link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Montserrat:wght@400;500;600;700;800;900&display=swap" /></noscript>
  </head>

<body>

<?php if (!empty($pageIntro)) include __DIR__ . '/intro_gate.php'; ?>

<!-- Enlace para saltar la navegación (teclado y lectores de pantalla) -->
<a class="skip-link" href="#contenido">Saltar al contenido principal</a>

<?php if ($barraAdmin): ?>
<!-- ======= BARRA DE ADMINISTRACIÓN (solo visible para el admin logueado) ======= -->
<div class="admin-bar" role="region" aria-label="Barra de administración">
  <div class="admin-bar-inner">
    <span class="admin-bar-quien"><?= icono('llave', '', 14) ?> Admin · <?= htmlspecialchars($barraAdmin['nombre']) ?></span>
    <nav class="admin-bar-links" aria-label="Accesos al panel">
      <a href="admin/Seccion/inicio.php"><?= icono('casa', '', 15) ?><span class="admin-bar-texto">Panel</span></a>
      <a href="admin/Seccion/pedidos.php?estado=pendiente">
        <?= icono('paquete', '', 15) ?><span class="admin-bar-texto">Pedidos</span>
        <?php if ($barraAdmin['pendientes'] > 0): ?>
          <span class="admin-bar-badge" title="Pedidos pendientes"><?= $barraAdmin['pendientes'] ?><span class="sr-only"> pendientes</span></span>
        <?php endif; ?>
      </a>
      <a href="admin/Seccion/productos.php"><?= icono('carrito', '', 15) ?><span class="admin-bar-texto">Productos</span></a>
      <a href="admin/Seccion/promociones.php"><?= icono('etiqueta', '', 15) ?><span class="admin-bar-texto">Promociones</span></a>
    </nav>
    <a href="admin/logout.php" class="admin-bar-salir"><?= icono('salir', '', 15) ?><span class="admin-bar-texto">Salir</span></a>
  </div>
</div>
<?php endif; ?>

<!-- ======= HEADER ======= -->

<header class="site-header" id="site-header">
  <div class="header-inner">

    <a href="index.php" class="header-logo" aria-label="El Rápido - Ir al inicio">
      <img src="assets/img/logo-el-rapido-inicio.png" alt="Logo de El Rápido" width="180" height="60" decoding="async">
    </a>

    <nav class="header-nav" aria-label="Navegación principal">
      <a href="index.php" class="nav-link <?= $paginaActual === 'index' ? 'active' : '' ?>"<?= $paginaActual === 'index' ? ' aria-current="page"' : '' ?>>
        <span class="nav-icon"><?= icono('casa', '', 16) ?></span>Inicio
      </a>
      <a href="productos.php" class="nav-link <?= $paginaActual === 'productos' ? 'active' : '' ?>"<?= $paginaActual === 'productos' ? ' aria-current="page"' : '' ?>>
        <span class="nav-icon"><?= icono('paquete', '', 16) ?></span>Productos
      </a>
      <a href="nosotros.php" class="nav-link <?= $paginaActual === 'nosotros' ? 'active' : '' ?>"<?= $paginaActual === 'nosotros' ? ' aria-current="page"' : '' ?>>
        <span class="nav-icon"><?= icono('info', '', 16) ?></span>Nosotros
      </a>
    </nav>

    <div class="header-actions">

      <button type="button" class="btn-tema" id="btn-tema" aria-pressed="false" aria-label="Activar modo oscuro" title="Modo oscuro">
        <span class="tema-luna"><?= icono('luna', '', 20) ?></span>
        <span class="tema-sol"><?= icono('sol', '', 20) ?></span>
      </button>

      <a href="carrito.php" class="btn-carrito" aria-label="Ver carrito (<?= $seoCantidadCarrito ?> producto<?= $seoCantidadCarrito === 1 ? '' : 's' ?>)">
        <span class="carrito-icono">
          <?= icono('carrito', '', 20) ?>
          <span class="carrito-badge" id="carrito-badge"><?= (int) $resumenCarrito['cantidad_total'] ?></span>
        </span>
        <span class="carrito-total" id="carrito-total"><?= formatoPrecio($resumenCarrito['total']) ?></span>
      </a>                                                

      <?php if ($logueado): ?>
      <div class="cuenta-dropdown">
        <button type="button" class="btn-cuenta" aria-haspopup="true" aria-expanded="false" aria-controls="cuenta-menu" onclick="toggleCuenta()" id="btn-cuenta">
          <span class="cuenta-avatar"><?= icono('usuario', '', 18) ?></span>
          <span class="cuenta-info">
            <strong><?= htmlspecialchars($nombreUsuario) ?></strong>
            <span>Mi cuenta <?= icono('chevron-abajo', 'chevron-mini', 10) ?></span>
          </span>
        </button>
        <div class="cuenta-menu" id="cuenta-menu">
          <a href="mi-cuenta.php?seccion=resumen" class="cuenta-menu-item"><?= icono('usuario', '', 15) ?> Mi perfil</a>
          <a href="mi-cuenta.php?seccion=pedidos" class="cuenta-menu-item"><?= icono('paquete', '', 15) ?> Mis pedidos</a>
          <a href="mi-cuenta.php?seccion=compra-semanal" class="cuenta-menu-item"><?= icono('calendario', '', 15) ?> Compra semanal</a>
          <a href="logout.php" class="cuenta-menu-item cuenta-menu-salir"><?= icono('salir', '', 15) ?> Cerrar sesión</a>
        </div>
      </div>
      <?php else: ?>
      <a href="login.php" class="btn-cuenta">
        <span class="cuenta-avatar"><?= icono('usuario', '', 18) ?></span>
        <span class="cuenta-info">
          <strong>Mi cuenta</strong>
          <span>Iniciar sesión <?= icono('chevron-abajo', 'chevron-mini', 10) ?></span>
        </span>
      </a>
      <?php endif; ?>

      <button type="button" class="btn-hamburger" aria-label="Menú" aria-expanded="false" aria-controls="mobile-menu" onclick="toggleMenu()">
        <span></span><span></span><span></span>
      </button>
    </div>

  </div>

  <!-- Menú mobile -->
  <nav class="mobile-menu" id="mobile-menu" aria-label="Menú móvil">
    <a href="index.php" class="nav-link <?= $paginaActual === 'index' ? 'active' : '' ?>"<?= $paginaActual === 'index' ? ' aria-current="page"' : '' ?>><?= icono('casa', '', 16) ?> Inicio</a>
    <a href="productos.php" class="nav-link <?= $paginaActual === 'productos' ? 'active' : '' ?>"<?= $paginaActual === 'productos' ? ' aria-current="page"' : '' ?>><?= icono('paquete', '', 16) ?> Productos</a>
    <a href="nosotros.php" class="nav-link <?= $paginaActual === 'nosotros' ? 'active' : '' ?>"<?= $paginaActual === 'nosotros' ? ' aria-current="page"' : '' ?>><?= icono('info', '', 16) ?> Nosotros</a>
    <?php if ($logueado): ?>
      <a href="mi-cuenta.php?seccion=resumen" class="nav-link"><?= icono('usuario', '', 16) ?> Mi perfil</a>
      <a href="mi-cuenta.php?seccion=pedidos" class="nav-link"><?= icono('paquete', '', 16) ?> Mis pedidos</a>
      <a href="mi-cuenta.php?seccion=compra-semanal" class="nav-link"><?= icono('calendario', '', 16) ?> Compra semanal</a>
      <a href="logout.php" class="nav-link"><?= icono('salir', '', 16) ?> Cerrar sesión</a>
    <?php else: ?>
      <a href="login.php" class="nav-link"><?= icono('llave', '', 16) ?> Iniciar sesión</a>
      <a href="registro.php" class="nav-link"><?= icono('lapiz', '', 16) ?> Registrarse</a>
    <?php endif; ?>
    <a href="carrito.php" class="nav-link"><?= icono('carrito', '', 16) ?> Carrito</a>
  </nav>

</header>

<main id="contenido" tabindex="-1">



<?php

$pageCss = 'productos.css';
$pageCssExtra = ['promo-publico.css'];
// El título, la descripción y el canonical dependen de la categoría y de la página, así que el
// header se incluye recién después de leer los filtros (ver más abajo, antes del HTML).
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/funciones.php';
require_once 'includes/promociones_funciones.php';
require_once 'includes/favoritos.php';
require_once 'includes/iconos.php';
require_once 'includes/tarjeta_producto.php';

$pdo = obtenerConexion();
// Favoritos del cliente logueado: sus productos van primero y se ven con el corazón lleno
$usuarioIdFav = usuarioLogueado() ? (int) $_SESSION['usuario']['id'] : 0;
$idsFavoritos = obtenerIdsFavoritos($pdo, $usuarioIdFav);
$categorias = obtenerCategoriasPublico($pdo); // se usa también para el título SEO de la categoría activa

/* ───────────────────────────────────────────────
   0) PROMOCIONES ACTIVAS (categoría "Promociones" + etiqueta cruzada)
   ─────────────────────────────────────────────── */
actualizarEstadosPromociones($pdo);
$promosActivas = obtenerTodasLasPromocionesActivas($pdo);
$productosEnPromoMap = obtenerProductosEnPromoActivaMap($pdo);

$promosActivasJs = array_map(function ($p) {
  return [
    'id' => (int) $p['id'], 'tipo' => $p['tipo'], 'titulo' => $p['titulo'],
    'descripcion' => $p['descripcion'], 'color_banner' => $p['color_banner'], 'color_hex' => $p['color_hex'],
    'precio_promo' => $p['precio_promo'] !== null ? (float) $p['precio_promo'] : null,
    'descuento_pct' => $p['descuento_pct'] !== null ? (int) $p['descuento_pct'] : null,
    'fecha_fin_fmt' => date('d/m/Y', strtotime($p['fecha_fin'])),
    'productos' => array_map(fn($x) => ['id' => (int) $x['id'], 'nombre' => $x['nombre'], 'precio' => (float) $x['precio'], 'emoji' => $x['emoji'], 'imagen' => $x['imagen'], 'stock' => (int) $x['stock']], $p['productos']),
  ];
}, $promosActivas);

/* ───────────────────────────────────────────────
   1) LEER FILTROS DE LA URL (?cat=bebidas&orden=precio_asc&pagina=2)
   ─────────────────────────────────────────────── */
// getTexto(): si el parámetro falta o llega como array (?cat[]=x) devuelve el valor por defecto
$categoriaActiva = getTexto('cat', 'todas');
$orden           = validarOrden(getTexto('orden', 'destacados'));
$pagina          = max(1, (int) getTexto('pagina', '1'));
$porPagina       = 12;
// Buscador (?q=coca cola): palabras sueltas, espacios normalizados. En la categoría "Promociones" no se aplica
// (esa vista lista promociones, no productos): ahí el formulario de búsqueda salta a "Todas" (ver más abajo).
$busqueda        = trim(preg_replace('/\s+/u', ' ', getTexto('q', '', 60)));
if ($categoriaActiva === 'promociones') {
  $busqueda = '';
}

/* ───────────────────────────────────────────────
   2) CATEGORÍAS + CANTIDAD DE PRODUCTOS DE CADA UNA
   ─────────────────────────────────────────────── */
$totalProductosActivos = (int) $pdo->query("SELECT COUNT(*) FROM productos WHERE activo = 1")->fetchColumn();

/* ───────────────────────────────────────────────
   3) FILTRO + LISTADO DE PRODUCTOS DE LA PÁGINA ACTUAL
   ─────────────────────────────────────────────── */
[$where, $params] = construirFiltroProductosPublico($categoriaActiva, $busqueda);

$totalFiltrado = contarProductosPublico($pdo, $where, $params);

$totalPaginas = max(1, (int) ceil($totalFiltrado / $porPagina));
if ($pagina > $totalPaginas) {
  $pagina = $totalPaginas;
}
$offset = ($pagina - 1) * $porPagina;

$productos = obtenerProductosPublico($pdo, $where, $params, ordenesValidosProductos()[$orden], $porPagina, $offset, $usuarioIdFav ?: null);

/* Helper para armar links conservando los filtros actuales */
function urlConFiltrosProductos(array $sobrescribir = []): string {
  $actuales = [
    'cat'    => getTexto('cat', 'todas'),
    'q'      => getTexto('q', '', 60),
    'orden'  => getTexto('orden', 'destacados'),
    'pagina' => getTexto('pagina', '1'),
  ];
  // Los valores por defecto (todas / sin búsqueda / destacados / página 1) no se escriben en la URL: los enlaces internos
  // quedan limpios (productos.php?cat=bebidas) y coinciden con la URL canónica que ve el buscador.
  $finales = array_merge(array_filter($actuales, fn($v) => $v !== '' && $v !== null), $sobrescribir);
  if (($finales['cat'] ?? 'todas') === 'todas') unset($finales['cat']);
  if (trim((string) ($finales['q'] ?? '')) === '') unset($finales['q']);
  if (($finales['orden'] ?? 'destacados') === 'destacados') unset($finales['orden']);
  if ((int) ($finales['pagina'] ?? 1) <= 1) unset($finales['pagina']);
  return 'productos.php' . ($finales ? '?' . http_build_query($finales) : '');
}


/* ───────────────────────────────────────────────
   SEO: título, descripción y canonical según categoría y página
   (el orden "?orden=" no cambia el contenido, así que no entra en el canonical)
   ─────────────────────────────────────────────── */
$nombreCategoriaSeo = null;
foreach ($categorias as $catSeo) {
  if ($catSeo['slug'] === $categoriaActiva) { $nombreCategoriaSeo = $catSeo['nombre']; break; }
}
$sufijoPagina = $pagina > 1 ? ' - Página ' . $pagina : '';
if ($categoriaActiva === 'promociones') {
  $pageTitle       = 'Promociones y ofertas';
  $pageDescription = 'Promociones y ofertas vigentes de Maxikiosco El Rápido en Monte Caseros: combos, 2x1 y descuentos en bebidas, snacks y más.';
  $pageCanonical   = 'productos.php?cat=promociones';
} elseif ($nombreCategoriaSeo !== null) {
  $pageTitle       = $nombreCategoriaSeo . ' - Productos' . $sufijoPagina;
  $pageDescription = 'Comprá ' . mb_strtolower($nombreCategoriaSeo) . ' online en Maxikiosco El Rápido, Monte Caseros. Precios actualizados, delivery todos los días o retiro en el local.';
  $pageCanonical   = 'productos.php?cat=' . urlencode($categoriaActiva) . ($pagina > 1 ? '&pagina=' . $pagina : '');
} else {
  $pageTitle       = 'Productos: bebidas, snacks, golosinas y más' . $sufijoPagina;
  $pageDescription = 'Catálogo completo de Maxikiosco El Rápido en Monte Caseros: bebidas, snacks, golosinas, cigarrillos y recargas. Pedí online con delivery o retiro en el local.';
  $pageCanonical   = 'productos.php' . ($pagina > 1 ? '?pagina=' . $pagina : '');
}

// Resultados del buscador: no tienen valor para Google (infinitas combinaciones), así que no se indexan.
// El canonical de arriba ya apunta a la categoría sin búsqueda.
if ($busqueda !== '') {
  $pageTitle       = 'Resultados para «' . $busqueda . '»' . $sufijoPagina;
  $pageDescription = 'Productos de Maxikiosco El Rápido que coinciden con «' . $busqueda . '».';
  $pageRobots      = 'noindex,follow';
}

include 'includes/header.php';
?>

<!-- ══════════════════════════════════════ HERO ══ -->
<section class="hero hero--productos">
  <div class="hero-inner">
    <div class="hero-text">
      <span class="hero-kicker">CATÁLOGO COMPLETO</span>
      <h1>Nuestros productos</h1>
      <p>Elegí lo que necesitás, rápido y fácil.</p>
    </div>

    <!-- Carrito animado (movimiento.css): llega a toda velocidad, los productos caen adentro y queda en show permanente -->
    <div class="hero-carro" aria-hidden="true">
      <svg viewBox="0 0 360 290" xmlns="http://www.w3.org/2000/svg" focusable="false">
        <circle class="hc-halo" cx="190" cy="140" r="128" fill="#FFD600" opacity=".16"/>
        <circle class="hc-onda" cx="190" cy="140" r="60" fill="none" stroke="#FFD600" stroke-width="3" style="--d:3.6s"/>
        <circle class="hc-onda" cx="190" cy="140" r="60" fill="none" stroke="#D91F26" stroke-width="2" style="--d:5.6s"/>
        <g class="hc-orbita"><ellipse cx="190" cy="140" rx="150" ry="118" fill="none" stroke="#D91F26" stroke-width="2" stroke-dasharray="3 9" opacity=".45"/><circle cx="340" cy="140" r="9" fill="#FFD600" stroke="#e6bf00" stroke-width="2"/><circle cx="40" cy="140" r="7" fill="#2a6fdb"/><path  d="M190 12l2.8 7.2 7.2 2.8-7.2 2.8-2.8 7.2-2.8-7.2-7.2-2.8 7.2-2.8z" fill="#D91F26"/><circle cx="190" cy="258" r="6" fill="#2e9b4a"/></g>
        <ellipse class="hc-sombra" cx="196" cy="268" rx="104" ry="9" fill="#000" opacity=".13"/>
        <ellipse class="hc-shock" cx="196" cy="266" rx="30" ry="6" fill="none" stroke="#FFD600" stroke-width="4"/>
        <circle class="hc-part" cx="60" cy="270" r="4" fill="#FFD600" style="--d:0s"/><circle class="hc-part" cx="110" cy="270" r="3" fill="#D91F26" style="--d:1.1s"/><circle class="hc-part" cx="170" cy="270" r="5" fill="#2a6fdb" style="--d:2.3s"/><circle class="hc-part" cx="230" cy="270" r="3" fill="#FFD600" style="--d:0.6s"/><circle class="hc-part" cx="290" cy="270" r="4" fill="#D91F26" style="--d:1.8s"/><circle class="hc-part" cx="335" cy="270" r="3" fill="#2e9b4a" style="--d:3s"/><circle class="hc-part" cx="20" cy="270" r="3" fill="#2a6fdb" style="--d:2.6s"/>
        <g class="hc-marcas" fill="none" stroke-linecap="round"><path class="hc-marca" pathLength="1" d="M128 254H52" stroke="#2b2622" stroke-width="5" opacity=".5"/><path class="hc-marca" pathLength="1" d="M128 259H60" stroke="#2b2622" stroke-width="2" opacity=".35"/><path class="hc-marca" pathLength="1" d="M278 254H202" stroke="#2b2622" stroke-width="5" opacity=".5"/><path class="hc-marca" pathLength="1" d="M278 259H210" stroke="#2b2622" stroke-width="2" opacity=".35"/></g>
        <g class="hc-bri" style="--d:0s"><path  d="M52 52l3.4 8.6 8.6 3.4-8.6 3.4-3.4 8.6-3.4-8.6-8.6-3.4 8.6-3.4z" fill="#FFD600"/></g>
        <g class="hc-bri" style="--d:.9s"><path  d="M338 27l2.5 6.5 6.5 2.5-6.5 2.5-2.5 6.5-2.5-6.5-6.5-2.5 6.5-2.5z" fill="#D91F26"/></g>
        <g class="hc-bri" style="--d:1.6s"><path  d="M334 148l2.2 5.8 5.8 2.2-5.8 2.2-2.2 5.8-2.2-5.8-5.8-2.2 5.8-2.2z" fill="#FFD600"/></g>
        <g class="hc-salto">
        <g class="hc-entra">
          <g class="hc-vel" stroke-linecap="round" stroke-width="6"><path d="M330 90h70" stroke="#FFD600"/><path d="M345 140h90" stroke="#D91F26"/><path d="M330 190h60" stroke="#FFD600"/><path d="M350 240h70" stroke="#2a6fdb"/></g>
          <g class="hc-resp">
            <g class="hc-prod" style="--i:0"><g class="hc-sp" style="--i:0"><g class="hc-bob" style="--b:0"><rect x="112" y="22" width="46" height="84" rx="9" fill="#D91F26"/><rect x="112" y="46" width="46" height="26" fill="#fff" opacity=".9"/><rect x="120" y="14" width="30" height="12" rx="4" fill="#b01920"/></g></g></g>
            <g class="hc-prod" style="--i:1"><g class="hc-sp" style="--i:1"><g class="hc-bob" style="--b:1"><path d="M176 40h52l-5 66h-42z" fill="#FFD600"/><path d="M176 40l6-10h40l6 10" fill="#e6bf00"/><circle cx="202" cy="72" r="13" fill="#D91F26"/></g></g></g>
            <g class="hc-prod" style="--i:2"><g class="hc-sp" style="--i:2"><g class="hc-bob" style="--b:2"><rect x="244" y="12" width="30" height="94" rx="12" fill="#2a6fdb"/><rect x="250" y="2" width="18" height="16" rx="4" fill="#1d4fa0"/><rect x="244" y="50" width="30" height="24" fill="#fff" opacity=".85"/></g></g></g>
            <g class="hc-prod" style="--i:3"><g class="hc-sp" style="--i:3"><g class="hc-bob" style="--b:3"><rect x="284" y="50" width="42" height="56" rx="6" fill="#2e9b4a"/><path d="M284 64h42" stroke="#fff" stroke-width="4" opacity=".7"/></g></g></g>
            <path d="M16 34h42l30 138h226" fill="none" stroke="#b01920" stroke-width="9" stroke-linecap="round" stroke-linejoin="round"/>
            <path d="M70 74h262l-28 94H100z" fill="var(--superficie)" stroke="#D91F26" stroke-width="8" stroke-linejoin="round"/>
            <path d="M138 80l8 82M196 80v82M254 80l-8 82M78 118h246" stroke="#D91F26" stroke-width="4" stroke-linecap="round" opacity=".5"/>
            <path d="M118 172l8 30h172l8-30" fill="none" stroke="#b01920" stroke-width="7" stroke-linecap="round" stroke-linejoin="round"/>
            <g class="hc-rueda"><circle cx="132" cy="236" r="19" fill="#1a1a1a"/><circle cx="132" cy="236" r="8" fill="#FFD600"/><path d="M132 220v32M116 236h32" stroke="#FFD600" stroke-width="3" stroke-linecap="round"/></g>
            <g class="hc-rueda"><circle cx="282" cy="236" r="19" fill="#1a1a1a"/><circle cx="282" cy="236" r="8" fill="#FFD600"/><path d="M282 220v32M266 236h32" stroke="#FFD600" stroke-width="3" stroke-linecap="round"/></g>
            <g class="hc-chapa"><circle cx="322" cy="40" r="23" fill="#FFD600" stroke="#D91F26" stroke-width="5"/><path d="M326 26l-14 17h10l-4 15 15-19h-10z" fill="#D91F26"/></g>
          </g>
        </g>
        </g>
        <circle class="hc-polvo" cx="152" cy="259" r="8" fill="#bdb3a6"/><circle class="hc-polvo" cx="178" cy="254" r="6" fill="#bdb3a6" style="--d2:.07s"/><circle class="hc-polvo" cx="300" cy="259" r="8" fill="#bdb3a6" style="--d2:.03s"/><circle class="hc-polvo" cx="326" cy="254" r="6" fill="#bdb3a6" style="--d2:.1s"/><circle class="hc-polvo" cx="48" cy="258" r="6" fill="#bdb3a6" style="--d2:.3s"/>
        <g class="hc-burst" style="--x:-120px;--y:-40px;--d:0s"><path  d="M190 51l2.5 6.5 6.5 2.5-6.5 2.5-2.5 6.5-2.5-6.5-6.5-2.5 6.5-2.5z" fill="#FFD600"/></g><g class="hc-burst" style="--x:-80px;--y:-90px;--d:0.04s"><path  d="M190 51l2.5 6.5 6.5 2.5-6.5 2.5-2.5 6.5-2.5-6.5-6.5-2.5 6.5-2.5z" fill="#D91F26"/></g><g class="hc-burst" style="--x:0px;--y:-120px;--d:0.02s"><path  d="M190 51l2.5 6.5 6.5 2.5-6.5 2.5-2.5 6.5-2.5-6.5-6.5-2.5 6.5-2.5z" fill="#FFD600"/></g><g class="hc-burst" style="--x:90px;--y:-90px;--d:0.06s"><path  d="M190 51l2.5 6.5 6.5 2.5-6.5 2.5-2.5 6.5-2.5-6.5-6.5-2.5 6.5-2.5z" fill="#2a6fdb"/></g><g class="hc-burst" style="--x:130px;--y:-30px;--d:0.03s"><path  d="M190 51l2.5 6.5 6.5 2.5-6.5 2.5-2.5 6.5-2.5-6.5-6.5-2.5 6.5-2.5z" fill="#FFD600"/></g><g class="hc-burst" style="--x:-140px;--y:40px;--d:0.05s"><path  d="M190 51l2.5 6.5 6.5 2.5-6.5 2.5-2.5 6.5-2.5-6.5-6.5-2.5 6.5-2.5z" fill="#2e9b4a"/></g><g class="hc-burst" style="--x:140px;--y:50px;--d:0.01s"><path  d="M190 51l2.5 6.5 6.5 2.5-6.5 2.5-2.5 6.5-2.5-6.5-6.5-2.5 6.5-2.5z" fill="#D91F26"/></g><g class="hc-burst" style="--x:60px;--y:-130px;--d:0.07s"><path  d="M190 51l2.5 6.5 6.5 2.5-6.5 2.5-2.5 6.5-2.5-6.5-6.5-2.5 6.5-2.5z" fill="#FFD600"/></g>
      </svg>
    </div>
    <button type="button" class="hc-sonido" aria-pressed="false" aria-label="Activar el sonido de la animación" title="Sonido de la animación">
      <svg class="s-off" viewBox="0 0 24 24" aria-hidden="true"><path d="M11 5 6 9H3v6h3l5 4z"/><path d="m16 9 5 6M21 9l-5 6"/></svg>
      <svg class="s-on" viewBox="0 0 24 24" aria-hidden="true"><path d="M11 5 6 9H3v6h3l5 4z"/><path d="M15.5 8.5a5 5 0 0 1 0 7M18.5 5.5a9 9 0 0 1 0 13"/></svg>
    </button>
  </div>
</section>

<!-- ══════════════════════════════════════ MAIN ══ -->
<!-- CATEGORÍAS EN MÓVIL: chips horizontales (en escritorio se usa la barra lateral) -->
<div class="cat-chips-wrap">
  <nav class="cat-chips" aria-label="Categorías">
    <a href="<?= htmlspecialchars(urlConFiltrosProductos(['cat' => 'promociones', 'pagina' => 1])) ?>" class="cat-chip cat-chip--promo<?= $categoriaActiva === 'promociones' ? ' active' : '' ?>"<?= $categoriaActiva === 'promociones' ? ' aria-current="page"' : '' ?>><?= icono('etiqueta', '', 16) ?> Promociones</a>
    <a href="<?= htmlspecialchars(urlConFiltrosProductos(['cat' => 'todas', 'pagina' => 1])) ?>" class="cat-chip<?= $categoriaActiva === 'todas' ? ' active' : '' ?>"<?= $categoriaActiva === 'todas' ? ' aria-current="page"' : '' ?>>Todas</a>
    <?php foreach ($categorias as $cat): ?>
      <a href="<?= htmlspecialchars(urlConFiltrosProductos(['cat' => $cat['slug'], 'pagina' => 1])) ?>" class="cat-chip<?= $categoriaActiva === $cat['slug'] ? ' active' : '' ?>"<?= $categoriaActiva === $cat['slug'] ? ' aria-current="page"' : '' ?>><?= iconoCategoria($cat, 16) ?> <?= htmlspecialchars($cat['nombre']) ?></a>
    <?php endforeach; ?>
  </nav>
</div>

<div class="main">

  <!-- SIDEBAR -->
  <aside class="sidebar" aria-label="Categorías y compra semanal">
    <p class="sidebar-title" id="sidebar-categorias-titulo">CATEGORÍAS</p>
    <nav aria-labelledby="sidebar-categorias-titulo">
    <ul class="cat-list">
      <li class="cat-item-promos">
        <a href="<?= htmlspecialchars(urlConFiltrosProductos(['cat' => 'promociones', 'pagina' => 1])) ?>"
           class="<?= $categoriaActiva === 'promociones' ? 'active' : '' ?>"<?= $categoriaActiva === 'promociones' ? ' aria-current="page"' : '' ?>>
          <span class="cat-name"><span class="cat-icono"><?= icono('etiqueta', '', 20) ?></span> Promociones</span>
          <?php if (count($promosActivas) > 0): ?>
            <span class="cat-badge"><?= count($promosActivas) ?></span>
          <?php endif; ?>
        </a>
      </li>
      <li>
        <a href="<?= htmlspecialchars(urlConFiltrosProductos(['cat' => 'todas', 'pagina' => 1])) ?>"
           class="<?= $categoriaActiva === 'todas' ? 'active' : '' ?>"<?= $categoriaActiva === 'todas' ? ' aria-current="page"' : '' ?>>
          <span class="cat-name"><span class="cat-icono"><?= icono('cuadricula', '', 20) ?></span> Todas</span>
          <span class="cat-badge"><?= $totalProductosActivos ?></span>
        </a>
      </li>
      <?php foreach ($categorias as $cat): ?>
      <li>
        <a href="<?= htmlspecialchars(urlConFiltrosProductos(['cat' => $cat['slug'], 'pagina' => 1])) ?>"
           class="<?= $categoriaActiva === $cat['slug'] ? 'active' : '' ?>"<?= $categoriaActiva === $cat['slug'] ? ' aria-current="page"' : '' ?>>
          <span class="cat-name"><span class="cat-icono"><?= iconoCategoria($cat, 20) ?></span> <?= htmlspecialchars($cat['nombre']) ?></span>
          <span class="cat-badge"><?= (int) $cat['cantidad'] ?></span>
        </a>
      </li>
      <?php endforeach; ?>
    </ul>
    </nav>

    <div class="compra-semanal">
      <h2>COMPRA SEMANAL</h2>
      <div class="cs-cuerpo">
        <span class="cs-icon"><?= icono('bolsa', '', 32) ?></span>
        <p>Guardá tu compra y recibila cuando quieras.</p>
      </div>
      <!-- Lleva a Mi cuenta > Compra semanal. Si no hay sesión iniciada,
           mi-cuenta.php lo manda al login y después vuelve a Mi cuenta. -->
      <a href="mi-cuenta.php?seccion=compra-semanal" class="btn-compra">Ver mis compras <?= icono('flecha-der', '', 16) ?></a>
    </div>
  </aside>

  <!-- PRODUCTS -->
  <section class="products-area">
    <?= flashHtml() ?>

    <!-- BUSCADOR: formulario GET común (funciona sin JavaScript). Conserva la categoría y el orden elegidos y
         vuelve a la página 1. Desde "Promociones" no manda la categoría, así busca en todos los productos. -->
    <form class="busqueda" id="form-busqueda" role="search" action="productos.php" method="get">
      <label class="sr-only" for="buscar-productos">Buscar productos</label>
      <span class="busqueda-icono" aria-hidden="true"><?= icono('buscar', '', 20) ?></span>
      <input type="search" id="buscar-productos" name="q" value="<?= htmlspecialchars($busqueda) ?>"
             placeholder="Buscar productos: coca cola, alfajor, papas…" maxlength="60"
             autocomplete="off" autocapitalize="none" spellcheck="false" enterkeyhint="search">
      <button type="button" class="busqueda-limpiar" id="busqueda-limpiar" aria-label="Borrar búsqueda"<?= $busqueda === '' ? ' hidden' : '' ?>><?= icono('x', '', 16) ?></button>
      <?php if ($categoriaActiva !== 'todas' && $categoriaActiva !== 'promociones'): ?>
        <input type="hidden" name="cat" value="<?= htmlspecialchars($categoriaActiva) ?>">
      <?php endif; ?>
      <?php if ($orden !== 'destacados'): ?>
        <input type="hidden" name="orden" value="<?= htmlspecialchars($orden) ?>">
      <?php endif; ?>
      <button type="submit" class="busqueda-btn">Buscar</button>
    </form>

    <div class="products-header">
      <?php if ($categoriaActiva === 'promociones'): ?>
        <span class="products-count"><?= icono('etiqueta', '', 15) ?> <?= count($promosActivas) ?> promoci<?= count($promosActivas) === 1 ? 'ón' : 'ones' ?> activa<?= count($promosActivas) === 1 ? '' : 's' ?></span>
      <?php else: ?>
        <span class="products-count" role="status" aria-live="polite">
          <?php if ($busqueda !== ''): ?>
            <?= $totalFiltrado ?> resultado<?= $totalFiltrado === 1 ? '' : 's' ?> para <strong>«<?= htmlspecialchars($busqueda) ?>»</strong>
            <a class="busqueda-quitar" href="<?= htmlspecialchars(urlConFiltrosProductos(['q' => '', 'pagina' => 1])) ?>">Quitar búsqueda</a>
          <?php else: ?>
            <?= $totalFiltrado ?> producto<?= $totalFiltrado === 1 ? '' : 's' ?> disponible<?= $totalFiltrado === 1 ? '' : 's' ?>
          <?php endif; ?>
        </span>
        <div class="sort-box">
          <label for="sort-select">Ordenar por</label>
          <select class="sort-select" id="sort-select">
            <option value="destacados"  <?= $orden === 'destacados'  ? 'selected' : '' ?>>Más vendidos</option>
            <option value="precio_asc"  <?= $orden === 'precio_asc'  ? 'selected' : '' ?>>Menor precio</option>
            <option value="precio_desc" <?= $orden === 'precio_desc' ? 'selected' : '' ?>>Mayor precio</option>
            <option value="az"          <?= $orden === 'az'          ? 'selected' : '' ?>>A – Z</option>
          </select>
        </div>
      <?php endif; ?>
    </div>

    <?php if ($categoriaActiva === 'promociones'): ?>

      <?php if (empty($promosActivas)): ?>
        <p style="padding: 40px 0; text-align: center; color: var(--gris-texto);">
          Todavía no hay promociones activas. ¡Volvé pronto!
        </p>
      <?php else: ?>
        <div class="promos-cat-grid">
          <?php foreach ($promosActivas as $p): ?>
            <?php
              $bgColor = ($p['color_banner'] === 'personalizado' && !empty($p['color_hex']))
                ? htmlspecialchars($p['color_hex'], ENT_QUOTES)
                : ($p['color_banner'] === 'rojo' ? '#D91F26' : ($p['color_banner'] === 'amarillo' ? '#FFD600' : '#1a1a2e'));
              $textColor = $p['color_banner'] === 'amarillo' ? '#1a1a1a' : '#fff';
              $precioListaTotal = array_sum(array_column($p['productos'], 'precio'));
              $badgeTxt = $p['tipo'] === 'descuento_pct' ? ((int) $p['descuento_pct']) . '% OFF' : etiquetasTipoPromo()[$p['tipo']][0];
            ?>
            <div class="promo-prod-card" data-abrir-promo="<?= (int) $p['id'] ?>" style="cursor:pointer;" data-reveal>
              <div class="promo-prod-franja" style="background:<?= $bgColor ?>;color:<?= $textColor ?>;"><?= htmlspecialchars(mb_strtoupper($badgeTxt)) ?></div>
              <div class="promo-prod-body">
                <div class="promo-prod-img">
                  <?php if (!empty($p['productos'][0]['imagen'])): ?>
                    <img src="assets/img/productos/<?= htmlspecialchars($p['productos'][0]['imagen']) ?>" alt="" loading="lazy" decoding="async">
                  <?php else: ?>
                    <?= !empty($p['productos'][0]['emoji']) ? htmlspecialchars($p['productos'][0]['emoji']) : icono('bolsa', '', 40) ?>
                  <?php endif; ?>
                </div>
                <div class="promo-prod-nombre"><?= htmlspecialchars($p['titulo']) ?></div>
                <div class="promo-prod-precio">
                  <?php if (in_array($p['tipo'], ['descuento_pct', 'segunda_unidad'], true)): ?>
                    <span class="precio-final">-<?= (int) $p['descuento_pct'] ?>%</span>
                  <?php elseif (in_array($p['tipo'], ['2x1', '3x2'], true)): ?>
                    <span class="precio-final"><?= htmlspecialchars($badgeTxt) ?></span>
                  <?php else: ?>
                    <span class="precio-final"><?= formatoPrecio((float) $p['precio_promo']) ?></span>
                    <span class="precio-orig"><?= formatoPrecio($precioListaTotal) ?></span>
                  <?php endif; ?>
                </div>
                <button type="button" class="btn-ver-promo">Ver y agregar</button>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

    <?php else: ?>

      <?php if (empty($productos)): ?>

      <?php if ($busqueda !== ''): ?>
        <div class="busqueda-vacia">
          <span class="busqueda-vacia-icono" aria-hidden="true"><?= icono('buscar', '', 40) ?></span>
          <p class="busqueda-vacia-titulo">No encontramos nada para «<?= htmlspecialchars($busqueda) ?>»</p>
          <p>Revisá que esté bien escrito o probá con una sola palabra (por ejemplo «coca» en vez de «coca cola zero»).</p>
          <div class="busqueda-vacia-acciones">
            <?php if ($categoriaActiva !== 'todas'): ?>
              <a class="btn-compra" href="<?= htmlspecialchars(urlConFiltrosProductos(['cat' => 'todas', 'pagina' => 1])) ?>">Buscar en todas las categorías</a>
            <?php endif; ?>
            <a class="btn-compra" href="<?= htmlspecialchars(urlConFiltrosProductos(['q' => '', 'pagina' => 1])) ?>">Ver todos los productos</a>
          </div>
        </div>
      <?php else: ?>
      <p style="padding: 40px 0; text-align: center; color: var(--gris-texto);">
        No encontramos productos en esta categoría todavía.
      </p>
      <?php endif; ?>

    <?php else: ?>

    <div class="products-grid">
      <?php foreach ($productos as $p): ?>
        <?= renderTarjetaProducto($p, ['favoritos' => $idsFavoritos, 'promos' => $productosEnPromoMap, 'volver' => urlConFiltrosProductos(), 'titulo' => 'h3']) ?>
      <?php endforeach; ?>
    </div><!-- /grid -->

    <!-- PAGINACIÓN -->
    <?php if ($totalPaginas > 1): ?>
    <nav class="pagination" aria-label="Paginación de productos">
      <?php if ($pagina > 1): ?>
        <a class="page-btn" rel="prev" aria-label="Página anterior" href="<?= htmlspecialchars(urlConFiltrosProductos(['pagina' => $pagina - 1])) ?>"><span aria-hidden="true">‹</span></a>
      <?php endif; ?>

      <?php for ($i = 1; $i <= $totalPaginas; $i++): ?>
        <a class="page-btn <?= $i === $pagina ? 'active' : '' ?>"
           aria-label="Página <?= $i ?>"<?= $i === $pagina ? ' aria-current="page"' : '' ?>
           href="<?= htmlspecialchars(urlConFiltrosProductos(['pagina' => $i])) ?>"><?= $i ?></a>
      <?php endfor; ?>

      <?php if ($pagina < $totalPaginas): ?>
        <a class="page-btn" rel="next" aria-label="Página siguiente" href="<?= htmlspecialchars(urlConFiltrosProductos(['pagina' => $pagina + 1])) ?>"><span aria-hidden="true">›</span></a>
      <?php endif; ?>
    </nav>
    <?php endif; ?>

    <?php endif; ?>

    <?php // ↑ cierra el "if (empty($productos))"; el endif de abajo cierra el "if cat===promociones" ?>
    <?php endif; ?>

  </section>
</div><!-- /main -->

<?php include 'includes/promo_modal.php'; ?>

<script>
  window.USUARIO_LOGUEADO = <?= $logueado ? 'true' : 'false' ?>;
  window.CSRF_TOKEN = <?= json_encode(csrfToken()) ?>;
  window.PROMOS_DATA = <?= json_encode($promosActivasJs) ?>;
</script>
<script src="<?= htmlspecialchars(assetUrl('assets/js/reveal.js')) ?>" defer></script>
<script src="<?= htmlspecialchars(assetUrl('assets/js/promo-publico.js')) ?>" defer></script>
<script src="<?= htmlspecialchars(assetUrl('assets/js/productos.js')) ?>" defer></script>
<script src="<?= htmlspecialchars(assetUrl('assets/js/carrito-sonido.js')) ?>" defer></script>

<?php

include 'includes/footer.php';

?>

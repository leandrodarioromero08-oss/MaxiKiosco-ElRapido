<?php require_once __DIR__ . '/../Config/iconos.php'; ?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="robots" content="noindex, nofollow">
  <title><?= htmlspecialchars(($tituloPagina ?? 'Panel') . ' — El Rápido Admin') ?></title>
  <link rel="icon" type="image/png" href="../../assets/img/icono-el-rapido.png" />
  <link rel="apple-touch-icon" href="../../assets/img/apple-touch-icon.png" />
  <link rel="manifest" href="../manifest.webmanifest" />
  <meta name="theme-color" content="#1A1A1A" />
  <meta name="mobile-web-app-capable" content="yes" />
  <meta name="apple-mobile-web-app-capable" content="yes" />
  <meta name="apple-mobile-web-app-title" content="Admin El Rápido" />

  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <!-- Carga no bloqueante: media="print" no frena el pintado; al terminar pasa a "all" (sin JS, el <noscript> la carga normal) -->
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Inter:wght@400;500;600;700&display=swap" media="print" onload="this.media='all'" />
  <noscript><link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Inter:wght@400;500;600;700&display=swap" /></noscript>
  <link rel="stylesheet" href="../assets/CSS/global.css">
  <link rel="stylesheet" href="../assets/CSS/notificaciones.css">
  <?php if (!empty($CSSpage)): ?>
        <link rel="stylesheet" href="../assets/CSS/<?= htmlspecialchars($CSSpage) ?>">
    <?php endif; ?>
</head>
<body>

<?php
  // Qué link del menú va marcado como "activo". Cada página puede definir
  // $seccionActiva = 'inicio' | 'productos' | 'pedidos' | 'promociones' | 'configuracion'
  // ANTES de hacer el include de este sidebar. Si no la define, seguimos
  // mostrando "Pedidos" como activo (comportamiento de siempre) para no
  // romper las páginas que todavía no la usan.
  $seccionActiva = $seccionActiva ?? 'pedidos';
?>

<div class="admin-wrapper">


  <!-- ==========================================
       SIDEBAR
       ========================================== -->
  <aside class="sidebar" id="admin-sidebar">

    <div class="sidebar-logo">
      <img src="../../assets/img/logo-el-rapido-admin.png" alt="El Rápido — Administración" class="sidebar-logo-full">
      <img src="../../assets/img/icono-el-rapido.png" alt="El Rápido" class="sidebar-logo-mini">
    </div>

    <ul class="sidebar-nav">
      <li>
        <a href="../Seccion/inicio.php" class="<?= $seccionActiva === 'inicio' ? 'activo' : '' ?>">
          <span class="nav-icon"><?= icono('inicio', '', 18) ?></span><span class="nav-label">Inicio</span>
        </a>
      </li>
      <li>
        <a href="../Seccion/productos.php" class="<?= $seccionActiva === 'productos' ? 'activo' : '' ?>">
          <span class="nav-icon"><?= icono('productos', '', 18) ?></span><span class="nav-label">Productos</span>
        </a>
      </li>
      <li>
        <a href="../Seccion/categorias.php" class="<?= $seccionActiva === 'categorias' ? 'activo' : '' ?>">
          <span class="nav-icon"><?= icono('etiqueta', '', 18) ?></span><span class="nav-label">Categorías</span>
        </a>
      </li>
      <li>
        <a href="../Seccion/pedidos.php" class="<?= $seccionActiva === 'pedidos' ? 'activo' : '' ?>">
          <span class="nav-icon"><?= icono('carrito', '', 18) ?></span><span class="nav-label">Pedidos</span>
        </a>
      </li>
      <li>
        <a href="../Seccion/promociones.php" class="<?= $seccionActiva === 'promociones' ? 'activo' : '' ?>">
          <span class="nav-icon"><?= icono('promociones', '', 18) ?></span><span class="nav-label">Promociones</span>
        </a>
      </li>
      <li>
        <a href="../Seccion/configuracion.php" class="<?= $seccionActiva === 'configuracion' ? 'activo' : '' ?>">
          <span class="nav-icon"><?= icono('configuracion', '', 18) ?></span><span class="nav-label">Configuración</span>
        </a>
      </li>
    </ul>

    <div class="sidebar-bottom">
      <a href="../../index.php">
        <span class="nav-icon"><?= icono('casa', '', 18) ?></span><span class="nav-label">Volver a la tienda</span>
      </a>
      <a href="../logout.php">
        <span class="nav-icon"><?= icono('salir', '', 18) ?></span><span class="nav-label">Cerrar sesión</span>
      </a>
    </div>

  </aside>


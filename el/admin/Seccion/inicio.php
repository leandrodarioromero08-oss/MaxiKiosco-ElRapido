<?php

require_once '../Config/funciones.php';
require_once '../Config/auth.php';
requerirAdmin();
$pdo = obtenerConexion();

/* ═══════════════════════════════════════════════════════════
   1) ESTADÍSTICAS DEL DÍA (las 4 tarjetas de arriba)
   ═══════════════════════════════════════════════════════════ */

// --- Pedidos hoy, comparado con ayer (sin contar cancelados: mismo criterio que "Ventas hoy" y que pedidos.php) ---
$pedidosHoy  = (int) $pdo->query("SELECT COUNT(*) FROM pedidos WHERE creado_en >= CURDATE() AND creado_en < CURDATE() + INTERVAL 1 DAY AND estado <> 'cancelado'")->fetchColumn();
$pedidosAyer = (int) $pdo->query("SELECT COUNT(*) FROM pedidos WHERE creado_en >= CURDATE() - INTERVAL 1 DAY AND creado_en < CURDATE() AND estado <> 'cancelado'")->fetchColumn();
$diffPedidos = $pedidosHoy - $pedidosAyer;

// --- Ventas hoy, comparado con ayer (sin contar pedidos cancelados; mismo criterio que pedidos.php) ---
$ventasHoy  = (float) $pdo->query("SELECT COALESCE(SUM(total),0) FROM pedidos WHERE creado_en >= CURDATE() AND creado_en < CURDATE() + INTERVAL 1 DAY AND estado <> 'cancelado'")->fetchColumn();
$ventasAyer = (float) $pdo->query("SELECT COALESCE(SUM(total),0) FROM pedidos WHERE creado_en >= CURDATE() - INTERVAL 1 DAY AND creado_en < CURDATE() AND estado <> 'cancelado'")->fetchColumn();
// Si ayer no hubo ventas no existe un porcentaje real (dividir por 0): se muestra un texto en vez de un "+100%" inventado
$sinVentasAyer = $ventasAyer <= 0;
$pctVentas = $sinVentasAyer ? 0 : (int) round((($ventasHoy - $ventasAyer) / $ventasAyer) * 100);

// --- Stock bajo (mismo umbral que productos.php: UMBRAL_STOCK_BAJO) ---
$stockBajo = (int) $pdo->query("SELECT COUNT(*) FROM productos WHERE activo = 1 AND stock < " . UMBRAL_STOCK_BAJO)->fetchColumn();

// --- Producto top (el de más ventas acumuladas) ---
$productoTop = $pdo->query("SELECT nombre, ventas FROM productos ORDER BY ventas DESC, nombre ASC LIMIT 1")->fetch();

/* ═══════════════════════════════════════════════════════════
   2) PEDIDOS RECIENTES (los últimos 4, igual que en pedidos.php)
   ═══════════════════════════════════════════════════════════ */
$pedidosRecientes = obtenerPedidosFiltrados($pdo, 'WHERE 1=1', [], 4, 0);
$etiquetasEstado  = etiquetasEstadoPedido();

/** "Hoy, 14:32" / "Ayer, 14:32" / "12/09, 14:32" según corresponda */
function formatoFechaPedido(string $fechaMysql): string {
  $fecha = new DateTime($fechaMysql);
  $hoy   = new DateTime('today');
  $ayer  = new DateTime('yesterday');
  $hora  = $fecha->format('H:i');

  if ($fecha >= $hoy) {
    return 'Hoy, ' . $hora;
  }
  if ($fecha >= $ayer) {
    return 'Ayer, ' . $hora;
  }
  return $fecha->format('d/m') . ', ' . $hora;
}

/* ═══════════════════════════════════════════════════════════
   3) GRÁFICO: VENTAS DE LOS ÚLTIMOS 7 DÍAS
   ═══════════════════════════════════════════════════════════ */
$stmtGrafico = $pdo->query("
  SELECT DATE(creado_en) AS dia, SUM(total) AS total
  FROM pedidos
  WHERE creado_en >= CURDATE() - INTERVAL 6 DAY AND estado <> 'cancelado'
  GROUP BY DATE(creado_en)
");
$ventasPorDia = array_column($stmtGrafico->fetchAll(), 'total', 'dia');

$diasCorto = ['Dom', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb']; // índice = date('w')
$xPos      = [36, 100, 165, 230, 295, 360, 425]; // mismas 7 posiciones que ya tenía el diseño

$grafico = [];
for ($i = 6; $i >= 0; $i--) {
  $fecha     = date('Y-m-d', strtotime("-{$i} day"));
  $grafico[] = [
    'label' => $diasCorto[(int) date('w', strtotime($fecha))],
    'valor' => (float) ($ventasPorDia[$fecha] ?? 0),
  ];
}
$maxVenta = max(array_column($grafico, 'valor'));

/** Redondea el máximo de ventas a un techo "prolijo" para las líneas de la grilla */
function techoGrafico(float $maxValor): float {
  if ($maxValor <= 0) {
    return 1000.0;
  }
  $base  = 10 ** floor(log10($maxValor));
  $techo = ceil($maxValor / $base) * $base;
  if ($techo * 0.9 < $maxValor) {
    $techo += $base; // si quedó muy pegado al máximo real, subimos un escalón
  }
  return $techo;
}

/** Etiqueta corta para el eje Y: "$50K" si es grande, "$800" si es chico */
function etiquetaEjeGrafico(float $valor): string {
  if ($valor >= 1000) {
    // Un decimal cuando hace falta ("$2,5K") para que dos líneas de la grilla no repitan "$2K"
    $miles = $valor / 1000;
    return '$' . number_format($miles, fmod($miles, 1) == 0.0 ? 0 : 1, ',', '.') . 'K';
  }
  return formatoMoneda($valor);
}

$techo = techoGrafico($maxVenta);

// 6 líneas de grilla, de arriba (el techo) hacia abajo ($0)
$gridY      = [20, 60, 100, 140, 180, 210];
$etiquetasY = [];
for ($i = 0; $i < 6; $i++) {
  $etiquetasY[] = etiquetaEjeGrafico($techo * (5 - $i) / 5);
}

// Puntos de la línea: convertimos cada venta diaria a una coordenada Y del SVG
$puntos = [];
foreach ($grafico as $i => $dia) {
  $y        = 210 - ($dia['valor'] / $techo) * 190;
  $puntos[] = ['x' => $xPos[$i], 'y' => round($y, 1)];
}
$puntosSvg    = implode(' ', array_map(fn($p) => "{$p['x']},{$p['y']}", $puntos));
$poligonoSvg  = $puntosSvg . " {$xPos[6]},210 {$xPos[0]},210";

$CSSpage       = 'inicio.css';
$tituloPagina  = 'Inicio';
$seccionActiva = 'inicio';
include('../Template/sidebar.php');
include('../Template/topbar.php');

?>

    <!-- ========================================
         CONTENIDO DE LA PÁGINA
         ======================================== -->
    <main class="page-content">

      <!-- Saludo -->
      <h1 class="page-title">¡Bienvenido, <?= htmlspecialchars(adminActual()['nombre'] ?? 'Admin') ?>! 👋</h1>
      <p class="page-subtitle">Resumen general de tu kiosco</p>


      <!-- ======================================
           FILA 1: LAS 4 TARJETAS DE ESTADÍSTICAS
           ====================================== -->
      <div class="stats-grid">

        <!-- Tarjeta 1: Pedidos hoy -->
        <div class="stat-card">
          <div class="stat-icon tono-azul"><?= icono('carrito', '', 24) ?></div>
          <div class="stat-info">
            <p class="stat-label">Pedidos hoy</p>
            <p class="stat-value"><?= $pedidosHoy ?></p>
            <?php if ($diffPedidos > 0): ?>
              <p class="stat-trend trend-positivo">+<?= $diffPedidos ?> que ayer</p>
            <?php elseif ($diffPedidos < 0): ?>
              <p class="stat-trend trend-negativo"><?= $diffPedidos ?> que ayer</p>
            <?php else: ?>
              <p class="stat-trend">Igual que ayer</p>
            <?php endif; ?>
          </div>
        </div>

        <!-- Tarjeta 2: Ventas hoy -->
        <div class="stat-card">
          <div class="stat-icon tono-verde"><?= icono('dinero', '', 24) ?></div>
          <div class="stat-info">
            <p class="stat-label">Ventas hoy</p>
            <p class="stat-value"><?= formatoMoneda($ventasHoy) ?></p>
            <?php if ($sinVentasAyer): ?>
              <p class="stat-trend"><?= $ventasHoy > 0 ? 'Ayer no hubo ventas' : 'Sin ventas todavía' ?></p>
            <?php elseif ($pctVentas > 0): ?>
              <p class="stat-trend trend-positivo">+<?= $pctVentas ?>% que ayer</p>
            <?php elseif ($pctVentas < 0): ?>
              <p class="stat-trend trend-negativo"><?= $pctVentas ?>% que ayer</p>
            <?php else: ?>
              <p class="stat-trend">Sin cambios respecto a ayer</p>
            <?php endif; ?>
          </div>
        </div>

        <!-- Tarjeta 3: Stock bajo -->
        <div class="stat-card">
          <div class="stat-icon tono-rojo"><?= icono('paquete', '', 24) ?></div>
          <div class="stat-info">
            <p class="stat-label">Stock bajo</p>
            <p class="stat-value"><?= $stockBajo ?></p>
            <a href="productos.php?estado=stock_bajo" class="stat-link">Ver productos →</a>
          </div>
        </div>

        <!-- Tarjeta 4: Producto top -->
        <div class="stat-card">
          <div class="stat-icon tono-amarillo"><?= icono('fuego', '', 24) ?></div>
          <div class="stat-info">
            <p class="stat-label">Producto top</p>
            <p class="stat-product-name"><?= $productoTop ? htmlspecialchars($productoTop['nombre']) : 'Sin ventas todavía' ?></p>
            <p class="stat-product-sub"><?= ($productoTop && (int) $productoTop['ventas'] > 0) ? 'Más vendido' : 'Todavía no hay ventas' ?></p>
          </div>
        </div>

      </div>
      <!-- FIN ESTADÍSTICAS -->


      <!-- ======================================
           FILA 2: PEDIDOS RECIENTES + GRÁFICO
           ====================================== -->
      <div class="dos-columnas">

        <!-- ---------- PEDIDOS RECIENTES ---------- -->
        <div class="card">

          <div class="seccion-header">
            <h2 class="seccion-titulo">Pedidos recientes</h2>
            <a href="pedidos.php" class="ver-todos-link">Ver todos →</a>
          </div>

          <?php if (empty($pedidosRecientes)): ?>

            <p style="color: var(--gris-texto); font-size: 13px; padding: 20px 0; text-align: center;">
              Todavía no hay pedidos cargados.
            </p>

          <?php else: ?>

            <div class="tabla-scroll">
            <table class="tabla-pedidos">
              <tbody>

                <?php foreach ($pedidosRecientes as $ped): ?>
                  <?php [$etiquetaTexto, $etiquetaClase] = $etiquetasEstado[$ped['estado']] ?? [ucfirst($ped['estado']), 'badge-pendiente']; ?>
                  <tr>
                    <td>
                      <span class="pedido-num">#<?= (int) $ped['id'] ?></span>
                      <span class="pedido-hora"><?= formatoFechaPedido($ped['creado_en']) ?></span>
                    </td>
                    <td class="pedido-cliente"><?= htmlspecialchars($ped['cliente_nombre']) ?></td>
                    <td class="pedido-precio"><?= formatoMoneda((float) $ped['total']) ?></td>
                    <td><span class="badge <?= $etiquetaClase ?>"><?= $etiquetaTexto ?></span></td>
                    <td><a href="pedidos.php?q=<?= (int) $ped['id'] ?>" class="btn-rojo-borde">Ver pedido</a></td>
                  </tr>
                <?php endforeach; ?>

              </tbody>
            </table>
            </div>

          <?php endif; ?>

        </div>
        <!-- FIN PEDIDOS RECIENTES -->


        <!-- ---------- GRÁFICO DE VENTAS ---------- -->
        <div class="card">

          <div class="grafico-header">
            <h2 class="grafico-titulo">Ventas de los últimos 7 días <?= icono('grafico', 'icono-inline', 18) ?></h2>
          </div>

          <!--
            GRÁFICO HECHO CON SVG (sin JavaScript ni librerías).
            Los valores de "points" se arman en PHP más arriba
            ($puntosSvg / $poligonoSvg), a partir de las ventas reales
            de los últimos 7 días agrupadas por día.
          -->
          <svg class="grafico-svg" viewBox="0 0 460 220" xmlns="http://www.w3.org/2000/svg">

            <!-- FONDO blanco -->
            <rect width="460" height="220" fill="white"/>

            <!-- ETIQUETAS DEL EJE Y (calculadas según el máximo real de ventas) -->
            <?php foreach ($gridY as $i => $y): ?>
              <text x="0" y="<?= $y === 210 ? 210 : $y + 6 ?>" font-size="10" fill="#9E9E9E" font-family="Inter,sans-serif"><?= $etiquetasY[$i] ?></text>
            <?php endforeach; ?>

            <!-- LÍNEAS DE LA GRILLA (horizontales, color gris muy suave) -->
            <?php foreach ($gridY as $y): ?>
              <line x1="36" y1="<?= $y ?>" x2="455" y2="<?= $y ?>" stroke="#F0F0F0" stroke-width="1"/>
            <?php endforeach; ?>

            <!-- ÁREA RELLENA DEBAJO DE LA LÍNEA -->
            <polygon points="<?= $poligonoSvg ?>" fill="#FFEBEE"/>

            <!-- LÍNEA ROJA DEL GRÁFICO -->
            <polyline
              points="<?= $puntosSvg ?>"
              fill="none"
              stroke="#E53935"
              stroke-width="2.5"
              stroke-linejoin="round"
            />

            <!-- PUNTOS EN LA LÍNEA (uno por día) -->
            <?php foreach ($puntos as $p): ?>
              <circle cx="<?= $p['x'] ?>" cy="<?= $p['y'] ?>" r="4" fill="white" stroke="#E53935" stroke-width="2"/>
            <?php endforeach; ?>

            <!-- ETIQUETAS DEL EJE X (últimos 7 días, terminando hoy) -->
            <?php foreach ($grafico as $i => $dia): ?>
              <text x="<?= $xPos[$i] ?>" y="220" font-size="10" fill="#9E9E9E" font-family="Inter,sans-serif" text-anchor="middle"><?= $dia['label'] ?></text>
            <?php endforeach; ?>

          </svg>

        </div>
        <!-- FIN GRÁFICO -->

      </div>
      <!-- FIN DOS COLUMNAS -->


      <!-- ======================================
           FILA 3: ACCIONES RÁPIDAS
           ====================================== -->
      <div class="card" style="margin-bottom: 0;">

        <div class="seccion-header">
          <h2 class="seccion-titulo">Acciones rápidas</h2>
        </div>

        <div class="acciones-grid">

          <!-- Acción 1: Agregar producto -->
          <a href="productos.php?nuevo=1" class="accion-card">
            <div class="accion-icon tono-rojo"><?= icono('mas', '', 22) ?></div>
            <div>
              <p class="accion-nombre">Agregar producto</p>
              <p class="accion-desc">Crear nuevo producto</p>
            </div>
          </a>

          <!-- Acción 2: Crear promo -->
          <a href="promociones.php" class="accion-card">
            <div class="accion-icon tono-amarillo"><?= icono('etiqueta', '', 22) ?></div>
            <div>
              <p class="accion-nombre">Crear promo</p>
              <p class="accion-desc">Nueva promoción</p>
            </div>
          </a>

          <!-- Acción 3: Ver pedidos -->
          <a href="pedidos.php" class="accion-card">
            <div class="accion-icon tono-azul"><?= icono('carrito', '', 22) ?></div>
            <div>
              <p class="accion-nombre">Ver pedidos</p>
              <p class="accion-desc">Gestionar pedidos</p>
            </div>
          </a>

          <!-- Acción 4: Actualizar stock -->
          <a href="productos.php?estado=stock_bajo" class="accion-card">
            <div class="accion-icon tono-verde"><?= icono('paquete', '', 22) ?></div>
            <div>
              <p class="accion-nombre">Actualizar stock</p>
              <p class="accion-desc">Modificar inventario</p>
            </div>
          </a>

        </div>

      </div>
      <!-- FIN ACCIONES RÁPIDAS -->

    </main>
    <!-- FIN CONTENIDO DE LA PÁGINA -->

  </div>
  <!-- FIN MAIN CONTENT -->

</div>
<!-- FIN ADMIN WRAPPER -->

</body>
</html>

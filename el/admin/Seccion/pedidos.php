<?php
require_once '../Config/funciones.php';
require_once '../Config/auth.php';
requerirAdmin();
csrfExigir(); // protección CSRF: rechaza cualquier POST sin token válido
$pdo = obtenerConexion();

/* ═══════════════════════════════════════════════════════════
   1) CAMBIAR ESTADO DE UN PEDIDO
   ═══════════════════════════════════════════════════════════ */
function urlListadoPedidos(array $extra = []): string {
  $actuales = [
    // En un POST (cambiar estado, cancelar, eliminar) los filtros vienen en los campos *_actual
    'q'          => $_GET['q'] ?? ($_POST['q_actual'] ?? ''),
    'estado'     => $_GET['estado'] ?? ($_POST['estado_actual'] ?? ''),
    'fecha'      => $_GET['fecha'] ?? ($_POST['fecha_actual'] ?? ''),
    'pagina'     => $_GET['pagina'] ?? ($_POST['pagina_actual'] ?? 1),
    'por_pagina' => $_GET['por_pagina'] ?? ($_POST['por_pagina_actual'] ?? 10),
  ];
  return urlConFiltros('pedidos.php', $actuales, $extra);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'cambiar_estado') {
  $codigo = cambiarEstadoPedido($pdo, (int) ($_POST['pedido_id'] ?? 0), (string) ($_POST['estado'] ?? ''));
  header('Location: ' . urlListadoPedidos(['msg' => $codigo]));
  exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'cancelar_pedido') {
  $codigo = cambiarEstadoPedido($pdo, (int) ($_POST['pedido_id'] ?? 0), 'cancelado');
  header('Location: ' . urlListadoPedidos(['msg' => $codigo]));
  exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'eliminar_pedido') {
  $codigo = eliminarPedidoAdmin($pdo, (int) ($_POST['pedido_id'] ?? 0));
  header('Location: ' . urlListadoPedidos(['msg' => $codigo]));
  exit;
}

/* ═══════════════════════════════════════════════════════════
   2) FILTROS
   ═══════════════════════════════════════════════════════════ */
$busqueda     = trim($_GET['q'] ?? '');
$estadoFiltro = $_GET['estado'] ?? '';
$fechaFiltro  = $_GET['fecha'] ?? '';
$pagina       = max(1, (int) ($_GET['pagina'] ?? 1));
$porPagina    = (int) ($_GET['por_pagina'] ?? 10);
if (!in_array($porPagina, [5, 10, 25], true)) {
  $porPagina = 10;
}

/* ═══════════════════════════════════════════════════════════
   3) ESTADÍSTICAS REALES
   ═══════════════════════════════════════════════════════════ */
// Pedidos y ventas de hoy NO incluyen pedidos cancelados (mismo criterio que inicio.php)
$pedidosHoy   = (int) $pdo->query("SELECT COUNT(*) FROM pedidos WHERE creado_en >= CURDATE() AND creado_en < CURDATE() + INTERVAL 1 DAY AND estado <> 'cancelado'")->fetchColumn();
$ventasHoy    = (float) $pdo->query("SELECT COALESCE(SUM(total),0) FROM pedidos WHERE creado_en >= CURDATE() AND creado_en < CURDATE() + INTERVAL 1 DAY AND estado <> 'cancelado'")->fetchColumn();
$pendientes   = (int) $pdo->query("SELECT COUNT(*) FROM pedidos WHERE estado = 'pendiente'")->fetchColumn();
$entregados   = (int) $pdo->query("SELECT COUNT(*) FROM pedidos WHERE estado = 'entregado'")->fetchColumn();

/* ═══════════════════════════════════════════════════════════
   4) FILTRO + LISTADO (mismas funciones que usa pedidos_exportar.php,
      así el CSV exporta exactamente lo que se ve filtrado en pantalla)
   ═══════════════════════════════════════════════════════════ */
[$where, $params] = construirFiltroPedidos($busqueda, $estadoFiltro, $fechaFiltro);

$totalFiltrado = contarPedidosFiltrados($pdo, $where, $params);

$totalPaginas = max(1, (int) ceil($totalFiltrado / $porPagina));
if ($pagina > $totalPaginas) {
  $pagina = $totalPaginas;
}
$offset = ($pagina - 1) * $porPagina;

$pedidos = obtenerPedidosFiltrados($pdo, $where, $params, $porPagina, $offset);

/* Traemos los items de todos los pedidos de esta página en un solo query */
$itemsPorPedido = obtenerItemsPorPedido($pdo, array_column($pedidos, 'id'));

$etiquetasEstado = etiquetasEstadoPedido();
$etiquetasPago   = etiquetasMetodoPago();

$CSSpage = 'pedidos.css';
$tituloPagina = 'Pedidos';
$seccionActiva = 'pedidos';

/* Campos ocultos con los filtros actuales: cada formulario POST de abajo los reenvía
   para que, después de cambiar un estado, se vuelva a la MISMA vista filtrada. */
$camposFiltroActual = '';
foreach (['q_actual' => $busqueda, 'estado_actual' => $estadoFiltro, 'fecha_actual' => $fechaFiltro,
          'pagina_actual' => $pagina, 'por_pagina_actual' => $porPagina] as $nombreCampo => $valorCampo) {
  $camposFiltroActual .= '<input type="hidden" name="' . $nombreCampo . '" value="' . htmlspecialchars((string) $valorCampo, ENT_QUOTES) . '">';
}
include('../Template/sidebar.php');
include('../Template/topbar.php');
?>


    <main class="page-content">

      <div class="page-header">
        <div>
          <h1 class="page-title">Pedidos</h1>
          <p class="page-subtitle">Gestiona todos los pedidos de tu kiosco</p>
        </div>
      </div>

      <?php
        $mensajesPedido = [
          'estado_actualizado' => 'Estado del pedido actualizado. El cliente recibe el aviso en la página.',
          'pedido_cancelado'   => 'Pedido cancelado. El stock fue devuelto al catálogo.',
          'pedido_eliminado'   => 'Pedido eliminado correctamente.',
          'estado_sin_cambios' => 'El pedido ya tenía ese estado.',
        ];
        $mensajesPedidoError = [
          'estado_invalido'            => 'El estado elegido no es válido.',
          'pedido_inexistente'         => 'El pedido ya no existe.',
          'pedido_entregado_bloqueado' => 'Un pedido entregado ya no se puede modificar ni cancelar: la mercadería salió del local y el stock no se devuelve.',
          'estado_incompatible'        => 'Ese estado no corresponde a este tipo de pedido ("En camino" es solo para delivery y "Listo para retirar" solo para retiro).',
          'pedido_cancelado_bloqueado' => 'Un pedido cancelado no se puede reabrir (el stock ya fue devuelto). Si el cliente quiere comprar de nuevo, tiene que hacer un pedido nuevo.',
          'error_estado'               => 'No se pudo actualizar el pedido. No se hizo ningún cambio; probá de nuevo.',
        ];
        $msgPedido = $_GET['msg'] ?? '';
      ?>
      <?php if (isset($mensajesPedido[$msgPedido])): ?>
        <div class="alerta alerta-exito" data-notif="exito">
          <?= icono('check-circulo', '', 18) ?> <span><?= htmlspecialchars($mensajesPedido[$msgPedido]) ?></span>
        </div>
      <?php elseif (isset($mensajesPedidoError[$msgPedido])): ?>
        <div class="alerta alerta-error" data-notif="error">
          <?= icono('alerta', '', 18) ?> <span><?= htmlspecialchars($mensajesPedidoError[$msgPedido]) ?></span>
        </div>
      <?php endif; ?>


      <!-- ======================================
           4 TARJETAS DE ESTADÍSTICAS
           ====================================== -->
      <div class="stats-grid">

        <div class="stat-card">
          <div class="stat-icon tono-azul"><?= icono('carrito', '', 24) ?></div>
          <div class="stat-info">
            <p class="stat-label">Pedidos hoy</p>
            <p class="stat-value"><?= $pedidosHoy ?></p>
          </div>
        </div>

        <div class="stat-card">
          <div class="stat-icon tono-verde"><?= icono('dinero', '', 24) ?></div>
          <div class="stat-info">
            <p class="stat-label">Ventas hoy</p>
            <p class="stat-value"><?= formatoMoneda($ventasHoy) ?></p>
          </div>
        </div>

        <div class="stat-card">
          <div class="stat-icon tono-naranja"><?= icono('paquete', '', 24) ?></div>
          <div class="stat-info">
            <p class="stat-label">Pedidos pendientes</p>
            <p class="stat-value"><?= $pendientes ?></p>
            <a href="<?= htmlspecialchars(urlListadoPedidos(['estado' => 'pendiente', 'pagina' => 1])) ?>" class="stat-link">Ver pendientes →</a>
          </div>
        </div>

        <div class="stat-card">
          <div class="stat-icon tono-verde"><?= icono('check-circulo', '', 24) ?></div>
          <div class="stat-info">
            <p class="stat-label">Pedidos entregados</p>
            <p class="stat-value"><?= $entregados ?></p>
            <a href="<?= htmlspecialchars(urlListadoPedidos(['estado' => 'entregado', 'pagina' => 1])) ?>" class="stat-sub" style="text-decoration:none;">Ver entregados</a>
          </div>
        </div>

      </div>


      <!-- ======================================
           BARRA DE FILTROS
           ====================================== -->
      <form class="filtros-bar" method="GET" action="pedidos.php">

        <div class="filtro-buscar">
          <span class="search-icon"><?= icono('buscar', '', 15) ?></span>
          <input type="text" name="q" aria-label="Buscar pedidos" placeholder="Buscar por # de pedido o cliente..." value="<?= htmlspecialchars($busqueda) ?>">
        </div>

        <select class="filtro-select" name="estado" aria-label="Filtrar por estado" onchange="this.form.submit()">
          <option value="">Todos los estados</option>
          <?php foreach ($etiquetasEstado as $valorEstado => [$textoEstado, $claseEstado]): ?>
            <option value="<?= $valorEstado ?>" <?= $estadoFiltro === $valorEstado ? 'selected' : '' ?>><?= htmlspecialchars($textoEstado) ?></option>
          <?php endforeach; ?>
        </select>

        <select class="filtro-select" name="fecha" aria-label="Filtrar por fecha" onchange="this.form.submit()">
          <option value="">Fecha</option>
          <option value="hoy"    <?= $fechaFiltro === 'hoy'    ? 'selected' : '' ?>>Hoy</option>
          <option value="ayer"   <?= $fechaFiltro === 'ayer'   ? 'selected' : '' ?>>Ayer</option>
          <option value="semana" <?= $fechaFiltro === 'semana' ? 'selected' : '' ?>>Últimos 7 días</option>
          <option value="mes"    <?= $fechaFiltro === 'mes'    ? 'selected' : '' ?>>Últimos 30 días</option>
        </select>

        <input type="hidden" name="pagina" value="1">
        <input type="hidden" name="por_pagina" value="<?= (int) $porPagina ?>">
        <button type="submit" class="btn-filtros"><?= icono('filtrar', '', 15) ?> Filtrar</button>
        <a class="btn-exportar" href="pedidos_exportar.php?<?= http_build_query(array_filter(['q' => $busqueda, 'estado' => $estadoFiltro, 'fecha' => $fechaFiltro])) ?>"><?= icono('descargar', '', 15) ?> Exportar</a>

      </form>


      <!-- ======================================
           TABLA DE PEDIDOS
           ====================================== -->
      <div class="tabla-wrapper">

        <?php if (empty($pedidos)): ?>

          <p class="sin-resultados">No hay pedidos que coincidan con estos filtros.</p>

        <?php else: ?>

        <table class="tabla-pedidos">
          <thead>
            <tr>
              <th>Pedido</th>
              <th>Cliente</th>
              <th>Fecha</th>
              <th>Entrega</th>
              <th>Estado</th>
              <th>Total</th>
              <th>Pago</th>
              <th>Acciones</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($pedidos as $ped): ?>
              <?php
                $items = $itemsPorPedido[$ped['id']] ?? [];
                [$pagoTexto, $pagoClase] = $etiquetasPago[$ped['metodo_pago']] ?? ['—', ''];
                $esDelivery = $ped['tipo_entrega'] === 'delivery';

                // Todo lo que necesita el modal de detalle va en un solo JSON
                // (más simple y menos frágil que ir sumando parámetros sueltos
                // al onclick cada vez que se agrega un dato nuevo al pedido).
                $pedidoDetalleData = [
                  'id'         => (int) $ped['id'],
                  'cliente'    => $ped['cliente_nombre'],
                  'fecha'      => date('d/m/Y H:i', strtotime($ped['creado_en'])),
                  'total'      => (float) $ped['total'],
                  'items'      => $items,
                  'esDelivery' => $esDelivery,
                  'direccion'  => $ped['direccion'] ?? '',
                  'recargo'    => (float) $ped['recargo_envio'],
                  'contacto'   => trim(($ped['entrega_nombre'] ?? '') . ' ' . ($ped['entrega_apellido'] ?? '')),
                  'telefono'   => $ped['telefono_contacto'] ?? '',
                  'calle'      => $ped['entrega_calle'] ?? '',
                  'numero'     => $ped['entrega_numero'] ?? '',
                  'barrio'     => $ped['entrega_barrio'] ?? '',
                  'referencia' => $ped['entrega_referencia'] ?? '',
                ];
                $pedidoDetalleJson = htmlspecialchars(json_encode($pedidoDetalleData, JSON_UNESCAPED_UNICODE), ENT_QUOTES);
              ?>
              <tr data-pedido="<?= $pedidoDetalleJson ?>">
                <td>
                  <span class="pedido-num">#<?= (int) $ped['id'] ?></span><br>
                  <a role="button" tabindex="0" class="pedido-ver" onclick="verDetalle(this.closest('tr')); return false;">Ver detalle</a>
                </td>
                <td>
                  <span class="cliente-nombre"><?= htmlspecialchars($ped['cliente_nombre']) ?></span><br>
                  <span class="cliente-tel"><?= icono('telefono', '', 12) ?> <?= htmlspecialchars($ped['telefono_contacto'] ?? 'Sin teléfono') ?></span>
                </td>
                <td class="pedido-fecha"><?= date('d/m/Y H:i', strtotime($ped['creado_en'])) ?></td>
                <td>
                  <?php if ($esDelivery): ?>
                    <span class="pago-badge" style="background:#FFF3E0;color:#E65100;"><?= icono('camion', '', 13) ?> Delivery</span>
                  <?php else: ?>
                    <span class="pago-badge" style="background:#E8F5E9;color:#2E7D32;"><?= icono('casa', '', 13) ?> Retiro</span>
                  <?php endif; ?>
                </td>
                <td>
                  <?php
                    $estadosOk  = estadosPermitidosPedido($ped['estado'], $ped['tipo_entrega']);
                    $estadoFijo = in_array($ped['estado'], ['cancelado', 'entregado'], true);
                  ?>
                  <form method="POST" action="pedidos.php">
                    <?= csrfCampo() ?>
                    <?= $camposFiltroActual ?>
                    <input type="hidden" name="accion" value="cambiar_estado">
                    <input type="hidden" name="pedido_id" value="<?= (int) $ped['id'] ?>">
                    <select name="estado" aria-label="Estado del pedido" class="select-estado <?= $etiquetasEstado[$ped['estado']][1] ?>" data-actual="<?= htmlspecialchars($ped['estado']) ?>"
                      onchange="cambiarEstadoDesdeSelect(this)"
                      <?= $estadoFijo ? 'disabled title="' . ($ped['estado'] === 'cancelado' ? 'Un pedido cancelado no se puede reabrir' : 'Un pedido entregado no se puede modificar') . '"' : '' ?>>
                      <?php foreach ($etiquetasEstado as $valor => [$texto, $clase]): ?>
                        <option value="<?= $valor ?>" <?= $ped['estado'] === $valor ? 'selected' : '' ?> <?= in_array($valor, $estadosOk, true) ? '' : 'disabled' ?>><?= $texto ?></option>
                      <?php endforeach; ?>
                    </select>
                  </form>
                </td>
                <td class="pedido-total"><?= formatoMoneda((float) $ped['total']) ?></td>
                <td><span class="pago-badge <?= $pagoClase ?>"><?= $pagoTexto ?></span></td>
                <td>
                  <div class="acciones-td">
                    <button type="button" class="btn-accion btn-ver" title="Ver pedido"
                      onclick="verDetalle(this.closest('tr'))"><?= icono('ojo', '', 15) ?></button>

                    <div class="menu-mas-wrap">
                      <button type="button" class="btn-accion btn-mas" title="Más opciones" onclick="toggleMenuMas(event, <?= (int) $ped['id'] ?>)"><?= icono('mas-opciones', '', 16) ?></button>
                      <div class="menu-mas-lista" id="menu-mas-<?= (int) $ped['id'] ?>">
                        <?php if (!in_array($ped['estado'], ['cancelado', 'entregado'], true)): ?>
                          <form method="POST" action="pedidos.php" onsubmit="return confirm('¿Cancelar el pedido #<?= (int) $ped['id'] ?>? El stock de los productos se devolverá al catálogo y el pedido no se podrá reabrir.');">
                            <?= csrfCampo() ?>
                            <?= $camposFiltroActual ?>
                            <input type="hidden" name="accion" value="cancelar_pedido">
                            <input type="hidden" name="pedido_id" value="<?= (int) $ped['id'] ?>">
                            <button type="submit" class="menu-mas-item"><?= icono('prohibido', '', 15) ?> Cancelar pedido</button>
                          </form>
                          <div class="menu-mas-divisor"></div>
                        <?php endif; ?>
                        <?php
                          $avisoEliminar = $ped['estado'] === 'entregado'
                            ? 'El pedido #' . (int) $ped['id'] . ' ya fue ENTREGADO: al eliminarlo desaparece de las ventas y estadísticas, y el stock NO se devuelve. ¿Eliminarlo definitivamente?'
                            : ($ped['estado'] === 'cancelado'
                              ? '¿Eliminar el pedido #' . (int) $ped['id'] . ' definitivamente? Esta acción no se puede deshacer.'
                              : '¿Eliminar el pedido #' . (int) $ped['id'] . ' definitivamente? Está sin cerrar: el stock se devolverá al catálogo. Esta acción no se puede deshacer.');
                        ?>
                        <form method="POST" action="pedidos.php" data-confirmar="<?= htmlspecialchars($avisoEliminar, ENT_QUOTES) ?>" onsubmit="return confirm(this.dataset.confirmar);">
                          <?= csrfCampo() ?>
                          <?= $camposFiltroActual ?>
                          <input type="hidden" name="accion" value="eliminar_pedido">
                          <input type="hidden" name="pedido_id" value="<?= (int) $ped['id'] ?>">
                          <button type="submit" class="menu-mas-item item-peligro"><?= icono('basura', '', 15) ?> Eliminar pedido</button>
                        </form>
                      </div>
                    </div>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>

        <div class="paginacion-wrapper">
          <span>Mostrando <?= $totalFiltrado === 0 ? 0 : $offset + 1 ?> a <?= min($offset + $porPagina, $totalFiltrado) ?> de <?= $totalFiltrado ?> pedidos</span>

          <div class="paginacion-botones">
            <?php if ($pagina > 1): ?>
              <a class="pag-btn" href="<?= htmlspecialchars(urlListadoPedidos(['pagina' => $pagina - 1])) ?>">‹</a>
            <?php endif; ?>
            <?php foreach (paginasVisibles($pagina, $totalPaginas) as $i): ?>
              <?php if ($i === null): ?>
                <span class="pag-btn" style="pointer-events:none;">…</span>
              <?php else: ?>
                <a class="pag-btn <?= $i === $pagina ? 'activa' : '' ?>" href="<?= htmlspecialchars(urlListadoPedidos(['pagina' => $i])) ?>"><?= $i ?></a>
              <?php endif; ?>
            <?php endforeach; ?>
            <?php if ($pagina < $totalPaginas): ?>
              <a class="pag-btn" href="<?= htmlspecialchars(urlListadoPedidos(['pagina' => $pagina + 1])) ?>">›</a>
            <?php endif; ?>
          </div>

          <div class="pag-por-pagina">
            <select aria-label="Resultados por página" onchange="location.href = actualizarPorPaginaPedidos(this.value)">
              <option value="5"  <?= $porPagina === 5  ? 'selected' : '' ?>>5 por página</option>
              <option value="10" <?= $porPagina === 10 ? 'selected' : '' ?>>10 por página</option>
              <option value="25" <?= $porPagina === 25 ? 'selected' : '' ?>>25 por página</option>
            </select>
          </div>
        </div>

        <?php endif; ?>

      </div>

    </main>

  </div>
</div>


<!-- ============================================================
     MODAL — Detalle del pedido
     ============================================================ -->
<div class="modal-overlay" id="modal-detalle">
  <div class="modal-box">
    <div class="modal-header">
      <h2 id="detalle-titulo">Pedido</h2>
      <button type="button" class="modal-cerrar" onclick="cerrarModalDetalle()" aria-label="Cerrar"><?= icono('x', '', 18) ?></button>
    </div>
    <div class="modal-body">
      <div class="detalle-fila"><span>Cliente</span><strong id="detalle-cliente"></strong></div>
      <div class="detalle-fila"><span>Fecha</span><strong id="detalle-fecha"></strong></div>
      <div class="detalle-fila" id="detalle-contacto-fila" style="display:none;"><span>Nombre y apellido</span><strong id="detalle-contacto"></strong></div>
      <div class="detalle-fila" id="detalle-telefono-fila" style="display:none;"><span>Teléfono</span><strong id="detalle-telefono"></strong></div>
      <div class="detalle-fila"><span>Entrega</span><strong id="detalle-entrega"></strong></div>
      <div class="detalle-fila" id="detalle-direccion-fila" style="display:none;"><span>Dirección</span><strong id="detalle-direccion"></strong></div>
      <div class="detalle-fila" id="detalle-referencia-fila" style="display:none;"><span>Referencia</span><strong id="detalle-referencia"></strong></div>
      <div class="detalle-fila" id="detalle-maps-fila" style="display:none;">
        <span></span>
        <a id="detalle-maps-link" href="#" target="_blank" rel="noopener" class="btn-exportar" style="text-decoration:none;"><?= icono('mapa', '', 15) ?> Ver en Google Maps</a>
      </div>

      <table class="detalle-items-tabla">
        <thead>
          <tr><th>Producto</th><th>Cant.</th><th>Subtotal</th></tr>
        </thead>
        <tbody id="detalle-items-body"></tbody>
      </table>

      <div class="detalle-total-fila">
        <span>Total</span>
        <span id="detalle-total"></span>
      </div>
    </div>
  </div>
</div>


<script src="../assets/js/pedidos.js" defer></script>

</body>
</html>

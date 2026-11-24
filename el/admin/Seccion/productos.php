<?php
require_once '../Config/funciones.php';
require_once '../Config/auth.php';
requerirAdmin();
// Si el archivo subido supera post_max_size, PHP descarta TODO el POST (llega vacío) y csrfExigir()
// mostraría un 403 incomprensible. Lo detectamos antes para dar un mensaje claro.
$postExcedido = $_SERVER['REQUEST_METHOD'] === 'POST' && empty($_POST) && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0;
if (!$postExcedido) {
  csrfExigir(); // protección CSRF: rechaza cualquier POST sin token válido
}
$pdo = obtenerConexion();
$errorGuardado   = $postExcedido
  ? 'Los datos enviados superan el tamaño máximo que acepta el servidor (' . ini_get('post_max_size') . '). Probá con una imagen más liviana.'
  : null;
$reabrirProducto = null; // si el guardado falla, se reabre el modal con lo que escribió el admin

/* ═══════════════════════════════════════════════════════════
   1) ACCIONES: guardar (crear/editar) y eliminar
   Se procesan ANTES de imprimir cualquier HTML porque usan
   header('Location: ...') para redirigir.
   ═══════════════════════════════════════════════════════════ */

/* Arma la URL de vuelta a productos.php conservando los filtros actuales */
function urlListado(array $extra = []): string {
  $actuales = [
    'q'          => $_GET['q'] ?? ($_POST['q_actual'] ?? ''),
    'cat'        => $_GET['cat'] ?? ($_POST['cat_actual'] ?? ''),
    'estado'     => $_GET['estado'] ?? ($_POST['estado_actual'] ?? ''),
    'pagina'     => $_GET['pagina'] ?? ($_POST['pagina_actual'] ?? 1),
    'por_pagina' => $_GET['por_pagina'] ?? ($_POST['por_pagina_actual'] ?? 10),
  ];
  return urlConFiltros('productos.php', $actuales, $extra);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'guardar') {

  $productoId   = !empty($_POST['producto_id']) ? (int) $_POST['producto_id'] : null;
  $nombre       = postTexto('nombre');
  $categoriaId  = (int) ($_POST['categoria_id'] ?? 0);
  $precio       = (float) str_replace(',', '.', postTexto('precio') ?: '0');
  $descuentoRaw = (int) ($_POST['descuento_pct'] ?? 0);
  $emoji        = mb_substr(postTexto('emoji') ?: '📦', 0, 8);
  $stockRaw     = (int) ($_POST['stock'] ?? 0);
  $destacado    = isset($_POST['destacado']) ? 1 : 0;
  $activo       = isset($_POST['activo']) ? 1 : 0;

  // La imagen ACTUAL se lee siempre de la BASE DE DATOS (nunca del campo oculto del formulario,
  // que controla el navegador) y acá solo se ve si se tildó "Quitar imagen".
  $quitarImagen   = isset($_POST['quitar_imagen']);
  $imagenActual   = null;
  $productoExiste = true;
  if ($productoId) {
    $stmtImgActual = $pdo->prepare('SELECT imagen FROM productos WHERE id = :id');
    $stmtImgActual->execute([':id' => $productoId]);
    $filaActual = $stmtImgActual->fetch();
    if ($filaActual === false) {
      $productoExiste = false;   // lo borraron desde otra pestaña/sesión mientras se editaba
    } else {
      $imagenActual = $filaActual['imagen'] ?: null;
    }
  }
  $imagenFinal = $imagenActual;

  // Stock: si estamos editando, se aplica la DIFERENCIA respecto de lo que el admin vio
  // al abrir el formulario, no el valor absoluto. Evita pisar ventas concurrentes.
  $stockOriginalPost = $_POST['stock_original'] ?? '';
  $deltaStock = ($productoId && $stockOriginalPost !== '' && ctype_digit((string) $stockOriginalPost))
    ? $stockRaw - (int) $stockOriginalPost
    : null; // sin dato de referencia: se usa el valor absoluto

  // Primero se valida TODO y recién después se toca el disco: así una validación fallida
  // no deja fotos huérfanas en assets/img/productos/.
  if (!$productoExiste) {
    $errorGuardado = 'El producto que intentabas editar ya no existe (¿lo eliminaron desde otra pantalla?). No se guardó nada.';
  } elseif ($nombre === '' || $categoriaId <= 0 || $precio <= 0) {
    $errorGuardado = 'Revisá los datos: nombre, categoría y un precio mayor a 0 son obligatorios.';
  } elseif (mb_strlen($nombre) > 150 || $precio > 99999999.99) {
    $errorGuardado = 'El nombre es demasiado largo (máx. 150 caracteres) o el precio es demasiado alto.';
  } elseif ($descuentoRaw < 0 || $descuentoRaw > 100) {
    $errorGuardado = 'El descuento tiene que estar entre 0 y 100.';
  } elseif ($stockRaw < 0 || $stockRaw > 1000000) {
    $errorGuardado = 'El stock tiene que estar entre 0 y 1.000.000.';
  } elseif (!in_array($categoriaId, array_map('intval', array_column(obtenerCategoriasAdmin($pdo), 'id')), true)) {
    $errorGuardado = 'La categoría elegida no existe.';
  } else {
    $descuentoPct = $descuentoRaw;
    $stock        = $stockRaw;

    $errorSubida  = null;
    $imagenSubida = subirImagenProducto($_FILES['imagen'] ?? [], $errorSubida);

    if ($imagenSubida === false) {
      // Falló la validación de la imagen (formato o tamaño)
      $errorGuardado = $errorSubida;
    } else {

      $imagenParaBorrar = null;
      if ($imagenSubida !== null) {
        // Se subió una foto nueva: la anterior se borra del disco DESPUÉS de guardar en BD
        $imagenParaBorrar = $imagenActual;
        $imagenFinal = $imagenSubida;
      } elseif ($quitarImagen) {
        $imagenParaBorrar = $imagenActual;
        $imagenFinal = null;
      }

      try {
        if ($productoId) {
          // stock = GREATEST(0, stock + delta): atómico, nunca negativo
          $stockSql = $deltaStock === null ? ':sto' : 'GREATEST(0, stock + :sto)';
          $stmt = $pdo->prepare("
            UPDATE productos
            SET categoria_id = :cat, nombre = :nom, precio = :pre, descuento_pct = :desc,
                emoji = :emo, imagen = :img, stock = $stockSql, destacado = :dest, activo = :act
            WHERE id = :id
          ");
          $stmt->execute([
            ':cat' => $categoriaId, ':nom' => $nombre, ':pre' => $precio, ':desc' => $descuentoPct,
            ':emo' => $emoji, ':img' => $imagenFinal, ':sto' => $deltaStock ?? $stock, ':dest' => $destacado, ':act' => $activo, ':id' => $productoId,
          ]);
          $redir = 'actualizado';
        } else {
          $stmt = $pdo->prepare("
            INSERT INTO productos (categoria_id, nombre, precio, descuento_pct, emoji, imagen, stock, destacado, activo)
            VALUES (:cat, :nom, :pre, :desc, :emo, :img, :sto, :dest, :act)
          ");
          $stmt->execute([
            ':cat' => $categoriaId, ':nom' => $nombre, ':pre' => $precio, ':desc' => $descuentoPct,
            ':emo' => $emoji, ':img' => $imagenFinal, ':sto' => $stock, ':dest' => $destacado, ':act' => $activo,
          ]);
          $redir = 'creado';
        }
      } catch (Throwable $e) {
        error_log('[el-rapido] admin productos guardar: ' . $e->getMessage());
        // La BD no cambió: si ya habíamos subido una foto nueva, la descartamos para no dejar basura
        if ($imagenSubida !== null) {
          eliminarImagenProducto($imagenSubida);
        }
        $errorGuardado = 'No se pudo guardar el producto. No se hizo ningún cambio; revisá los datos e intentá de nuevo.';
        $redir = null;
      }

      if ($redir !== null) {
        eliminarImagenProducto($imagenParaBorrar); // recién ahora que la BD ya quedó guardada
        header('Location: ' . urlListado(['msg' => $redir]));
        exit;
      }
    }
  }

  // Algo falló: se reabre el modal con lo que había escrito el admin (el archivo de imagen no se
  // puede reponer por seguridad del navegador: hay que volver a elegirlo).
  if ($errorGuardado !== null && $productoExiste) {
    $reabrirProducto = [
      'id' => $productoId, 'nombre' => $nombre, 'categoria' => $categoriaId, 'precio' => $_POST['precio'] ?? '',
      'descuento' => $descuentoRaw, 'emoji' => $emoji, 'imagen' => $imagenActual ?? '', 'stock' => $stockRaw,
      'stockOriginal' => $stockOriginalPost, 'destacado' => $destacado, 'activo' => $activo,
    ];
  }
}

// Eliminar: solo por POST con token CSRF (antes era un link ?eliminar=ID,
// que se podía disparar con un simple clic desde otro sitio).
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'eliminar') {
  $idEliminar = (int) ($_POST['producto_id'] ?? 0);

  $stmtImg = $pdo->prepare("SELECT imagen FROM productos WHERE id = :id");
  $stmtImg->execute([':id' => $idEliminar]);
  $filaEliminar = $stmtImg->fetch();
  if ($filaEliminar === false) {
    header('Location: ' . urlListado(['msg' => 'no_existe']));
    exit;
  }

  // Si el producto tiene ventas, está en alguna promoción o en listas de Compra Semanal, NO se
  // borra: se desactiva (baja lógica). Así no se pierde su historial, las promociones que lo
  // incluyen no quedan huérfanas y los clientes no pierden productos de sus listas sin aviso.
  // Solo los productos sin ninguna referencia se borran de verdad.
  $refs = obtenerReferenciasProductos($pdo, [$idEliminar])[$idEliminar];
  if ($refs['ventas'] > 0 || $refs['promos'] > 0 || $refs['listas'] > 0) {
    $pdo->prepare('UPDATE productos SET activo = 0 WHERE id = :id')->execute([':id' => $idEliminar]);
    header('Location: ' . urlListado(['msg' => 'desactivado']));
    exit;
  }
  try {
    $pdo->prepare("DELETE FROM productos WHERE id = :id")->execute([':id' => $idEliminar]);
  } catch (Throwable $e) {
    error_log('[el-rapido] admin productos eliminar: ' . $e->getMessage());
    header('Location: ' . urlListado(['msg' => 'error_eliminar']));
    exit;
  }
  eliminarImagenProducto($filaEliminar['imagen'] ?: null);
  header('Location: ' . urlListado(['msg' => 'eliminado']));
  exit;
}

/* ═══════════════════════════════════════════════════════════
   2) LEER FILTROS DE LA URL
   ═══════════════════════════════════════════════════════════ */
$busqueda        = trim($_GET['q'] ?? '');
$categoriaFiltro = isset($_GET['cat']) && $_GET['cat'] !== '' ? (int) $_GET['cat'] : 0;
$estadoFiltro    = $_GET['estado'] ?? '';
$pagina          = max(1, (int) ($_GET['pagina'] ?? 1));
$porPagina       = (int) ($_GET['por_pagina'] ?? 10);
if (!in_array($porPagina, [5, 10, 25, 50], true)) {
  $porPagina = 10;
}

/* ═══════════════════════════════════════════════════════════
   3) CATEGORÍAS (para el <select> del filtro y del modal)
   ═══════════════════════════════════════════════════════════ */
$categorias = obtenerCategoriasAdmin($pdo);

/* ═══════════════════════════════════════════════════════════
   4) ESTADÍSTICAS REALES
   ═══════════════════════════════════════════════════════════ */
$totalProductos     = (int) $pdo->query("SELECT COUNT(*) FROM productos")->fetchColumn();
$totalTop           = (int) $pdo->query("SELECT COUNT(*) FROM productos WHERE destacado = 1")->fetchColumn();
$totalBajoStock     = (int) $pdo->query("SELECT COUNT(*) FROM productos WHERE activo = 1 AND stock < " . UMBRAL_STOCK_BAJO)->fetchColumn();
$totalCategoriasAct = (int) $pdo->query("SELECT COUNT(DISTINCT categoria_id) FROM productos WHERE activo = 1")->fetchColumn();

/* ═══════════════════════════════════════════════════════════
   5) FILTRO + LISTADO (mismas funciones que usa productos_exportar.php,
      así el CSV exporta exactamente lo que se ve filtrado en pantalla)
   ═══════════════════════════════════════════════════════════ */
[$where, $params] = construirFiltroProductos($busqueda, $categoriaFiltro, $estadoFiltro);

$totalFiltrado = contarProductosFiltrados($pdo, $where, $params);

$totalPaginas = max(1, (int) ceil($totalFiltrado / $porPagina));
if ($pagina > $totalPaginas) {
  $pagina = $totalPaginas;
}
$offset = ($pagina - 1) * $porPagina;

$productos = obtenerProductosFiltrados($pdo, $where, $params, $porPagina, $offset);

$mensajesOk = [
  'creado'      => 'Producto creado correctamente.',
  'actualizado' => 'Producto actualizado correctamente.',
  'eliminado'   => 'Producto eliminado correctamente.',
  'desactivado' => 'El producto tiene ventas, promociones o listas de clientes asociadas, así que se desactivó en lugar de borrarse (se conserva su historial y deja de verse en la tienda).',
];
$mensajesError = [
  'no_existe'      => 'El producto ya no existe (¿lo eliminaron desde otra pantalla?).',
  'error_eliminar' => 'No se pudo eliminar el producto. No se hizo ningún cambio; intentá de nuevo.',
];
$msg = $_GET['msg'] ?? null;

// Referencias (ventas / promos / listas) de los productos de esta página: la confirmación de
// borrado le dice al admin qué va a pasar de verdad (borrar o solo desactivar).
$referencias = obtenerReferenciasProductos($pdo, array_column($productos, 'id'));

$CSSpage = 'productos.css';
$tituloPagina = 'Productos';
$seccionActiva = 'productos';
include('../Template/sidebar.php');
include('../Template/topbar.php');
?>


    <!-- ========================================
         CONTENIDO DE LA PÁGINA
         ======================================== -->
    <main class="page-content">

      <!-- Título + Botón agregar producto -->
      <div class="page-header">
        <div>
          <h1 class="page-title">Productos</h1>
          <p class="page-subtitle">Gestiona tu catálogo de productos</p>
        </div>
        <button type="button" class="btn-rojo" style="display:flex; align-items:center; gap:8px; padding:12px 20px; border:none;" onclick="abrirModalAgregar()">
          <?= icono('mas', '', 18) ?>
          Agregar producto
        </button>
      </div>

      <?php if ($msg && isset($mensajesOk[$msg])): ?>
        <div class="alerta alerta-exito"><?= icono('check-circulo', '', 18) ?> <span><?= htmlspecialchars($mensajesOk[$msg]) ?></span></div>
      <?php elseif ($msg && isset($mensajesError[$msg])): ?>
        <div class="alerta alerta-error"><?= icono('alerta', '', 18) ?> <span><?= htmlspecialchars($mensajesError[$msg]) ?></span></div>
      <?php endif; ?>
      <?php if (!empty($errorGuardado)): ?>
        <div class="alerta alerta-error"><?= icono('alerta', '', 18) ?> <span><?= htmlspecialchars($errorGuardado) ?></span></div>
      <?php endif; ?>


      <!-- ======================================
           FILA DE 4 ESTADÍSTICAS
           ====================================== -->
      <div class="stats-grid">

        <!-- Total productos -->
        <div class="stat-card">
          <div class="stat-icon"><?= icono('paquete', '', 24) ?></div>
          <div class="stat-info">
            <p class="stat-label">Total productos</p>
            <p class="stat-value"><?= $totalProductos ?></p>
          </div>
        </div>

        <!-- Productos top -->
        <div class="stat-card">
          <div class="stat-icon tono-amarillo"><?= icono('fuego', '', 24) ?></div>
          <div class="stat-info">
            <p class="stat-label">Destacados</p>
            <p class="stat-value"><?= $totalTop ?></p>
            <p class="stat-sub">Marcados como destacados</p>
          </div>
        </div>

        <!-- Bajo stock -->
        <div class="stat-card">
          <div class="stat-icon tono-rojo"><?= icono('paquete', '', 24) ?></div>
          <div class="stat-info">
            <p class="stat-label">Bajo stock</p>
            <p class="stat-value"><?= $totalBajoStock ?></p>
            <a href="<?= htmlspecialchars(urlListado(['estado' => 'stock_bajo', 'cat' => '', 'q' => '', 'pagina' => 1])) ?>" class="stat-link">Necesitan reposición →</a>
          </div>
        </div>

        <!-- Categorías -->
        <div class="stat-card">
          <div class="stat-icon tono-azul"><?= icono('ojo', '', 24) ?></div>
          <div class="stat-info">
            <p class="stat-label">Categorías</p>
            <p class="stat-value"><?= $totalCategoriasAct ?></p>
            <p class="stat-sub">Activas</p>
          </div>
        </div>

      </div>


      <!-- ======================================
           BARRA DE FILTROS
           ====================================== -->
      <form class="filtros-bar" method="GET" action="productos.php">

        <!-- Búsqueda de producto -->
        <div class="filtro-buscar">
          <span class="search-icon"><?= icono('buscar', '', 15) ?></span>
          <input type="text" name="q" aria-label="Buscar productos" placeholder="Buscar productos..." value="<?= htmlspecialchars($busqueda) ?>">
        </div>

        <!-- Select de categoría -->
        <select class="filtro-select" name="cat" aria-label="Filtrar por categoría" onchange="this.form.submit()">
          <option value="">Todas las categorías</option>
          <?php foreach ($categorias as $cat): ?>
            <option value="<?= (int) $cat['id'] ?>" <?= $categoriaFiltro === (int) $cat['id'] ? 'selected' : '' ?>>
              <?= htmlspecialchars($cat['icono'] . ' ' . $cat['nombre']) ?>
            </option>
          <?php endforeach; ?>
        </select>

        <!-- Select de estado -->
        <select class="filtro-select" name="estado" aria-label="Filtrar por estado" onchange="this.form.submit()">
          <option value="">Estado</option>
          <option value="activo"   <?= $estadoFiltro === 'activo'   ? 'selected' : '' ?>>Activo</option>
          <option value="inactivo" <?= $estadoFiltro === 'inactivo' ? 'selected' : '' ?>>Inactivo</option>
          <option value="stock_bajo" <?= $estadoFiltro === 'stock_bajo' ? 'selected' : '' ?>>Stock bajo (menos de <?= UMBRAL_STOCK_BAJO ?>)</option>
        </select>

        <input type="hidden" name="pagina" value="1">
        <input type="hidden" name="por_pagina" value="<?= (int) $porPagina ?>">

        <!-- Botón filtros (submit del buscador) -->
        <button type="submit" class="btn-filtros">
          <?= icono('filtrar', '', 15) ?> Filtrar
        </button>

        <!-- Botón exportar (va a la derecha) -->
        <a class="btn-exportar" href="productos_exportar.php?<?= http_build_query(array_filter(['q' => $busqueda, 'cat' => $categoriaFiltro ?: '', 'estado' => $estadoFiltro])) ?>">
          <?= icono('descargar', '', 15) ?> Exportar
        </a>

      </form>


      <!-- ======================================
           TABLA DE PRODUCTOS
           ====================================== -->
      <div class="tabla-wrapper" id="tabla-stock-bajo">

        <?php if (empty($productos)): ?>

          <p class="sin-resultados">No hay productos que coincidan con estos filtros.</p>

        <?php else: ?>

        <table class="tabla-productos">

          <thead>
            <tr>
              <th>Producto</th>
              <th>Categoría</th>
              <th>Precio</th>
              <th>Stock</th>
              <th>Estado</th>
              <th>Ventas</th>
              <th>Acciones</th>
            </tr>
          </thead>

          <tbody>
            <?php foreach ($productos as $p): ?>
              <?php
                $stockClase = $p['stock'] <= 0 ? 'stock-sin' : ($p['stock'] < 10 ? 'stock-bajo' : 'stock-ok');
                $stockTexto = $p['stock'] <= 0 ? 'Sin stock' : ($p['stock'] < 10 ? 'Stock bajo' : 'En stock');
              ?>
              <tr>
                <td>
                  <div class="producto-info">
                    <div class="producto-img">
                      <?php if (!empty($p['imagen'])): ?>
                        <img src="../../assets/img/productos/<?= htmlspecialchars($p['imagen']) ?>" alt="<?= htmlspecialchars($p['nombre']) ?>">
                      <?php else: ?>
                        <?= htmlspecialchars($p['emoji']) ?>
                      <?php endif; ?>
                    </div>
                    <div>
                      <p class="producto-nombre"><?= htmlspecialchars($p['nombre']) ?></p>
                      <p class="producto-categoria-sub"><?= htmlspecialchars($p['categoria_nombre']) ?></p>
                    </div>
                  </div>
                </td>
                <td><?= htmlspecialchars($p['categoria_nombre']) ?></td>
                <td class="producto-precio">
                  <?= formatoMoneda((float) $p['precio']) ?>
                  <?php if ((int) $p['descuento_pct'] > 0): ?>
                    <small style="display:block;color:#757575;font-weight:400;">−<?= (int) $p['descuento_pct'] ?>% → <?= formatoMoneda((float) $p['precio'] * (100 - (int) $p['descuento_pct']) / 100) ?> en la tienda</small>
                  <?php endif; ?>
                </td>
                <td>
                  <span class="stock-numero"><?= (int) $p['stock'] ?></span>
                  <span class="stock-estado <?= $stockClase ?>"><?= $stockTexto ?></span>
                </td>
                <td>
                  <?php if ($p['activo']): ?>
                    <span class="badge-activo">Activo</span>
                  <?php else: ?>
                    <span class="badge-inactivo">Inactivo</span>
                  <?php endif; ?>
                </td>
                <td><?= (int) $p['ventas'] ?></td>
                <td>
                  <div class="acciones-td">
                    <button type="button" class="btn-accion btn-editar" title="Editar"
                      data-id="<?= (int) $p['id'] ?>"
                      data-nombre="<?= htmlspecialchars($p['nombre'], ENT_QUOTES) ?>"
                      data-categoria="<?= (int) $p['categoria_id'] ?>"
                      data-precio="<?= (float) $p['precio'] ?>"
                      data-descuento="<?= (int) $p['descuento_pct'] ?>"
                      data-emoji="<?= htmlspecialchars($p['emoji'], ENT_QUOTES) ?>"
                      data-imagen="<?= htmlspecialchars($p['imagen'] ?? '', ENT_QUOTES) ?>"
                      data-stock="<?= (int) $p['stock'] ?>"
                      data-destacado="<?= (int) $p['destacado'] ?>"
                      data-activo="<?= (int) $p['activo'] ?>"
                      onclick="abrirModalEditar(this)"><?= icono('lapiz', '', 15) ?></button>
                    <?php $ref = $referencias[(int) $p['id']] ?? ['ventas' => 0, 'promos' => 0, 'listas' => 0]; ?>
                    <button type="button" class="btn-accion btn-eliminar" title="Eliminar"
                      data-id="<?= (int) $p['id'] ?>"
                      data-nombre="<?= htmlspecialchars($p['nombre'], ENT_QUOTES) ?>"
                      data-ventas="<?= $ref['ventas'] ?>" data-promos="<?= $ref['promos'] ?>" data-listas="<?= $ref['listas'] ?>"
                      onclick="confirmarEliminar(this)"><?= icono('basura', '', 15) ?></button>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>


        <!-- ====================================
             PAGINACIÓN
             ==================================== -->
        <div class="paginacion-wrapper">

          <span>
            Mostrando <?= $totalFiltrado === 0 ? 0 : $offset + 1 ?> a <?= min($offset + $porPagina, $totalFiltrado) ?> de <?= $totalFiltrado ?> productos
          </span>

          <div class="paginacion-botones">
            <?php if ($pagina > 1): ?>
              <a class="pag-btn" href="<?= htmlspecialchars(urlListado(['pagina' => $pagina - 1])) ?>">‹</a>
            <?php endif; ?>

            <?php foreach (paginasVisibles($pagina, $totalPaginas) as $i): ?>
              <?php if ($i === null): ?>
                <span class="pag-btn" style="pointer-events:none;">…</span>
              <?php else: ?>
                <a class="pag-btn <?= $i === $pagina ? 'activa' : '' ?>" href="<?= htmlspecialchars(urlListado(['pagina' => $i])) ?>"><?= $i ?></a>
              <?php endif; ?>
            <?php endforeach; ?>

            <?php if ($pagina < $totalPaginas): ?>
              <a class="pag-btn" href="<?= htmlspecialchars(urlListado(['pagina' => $pagina + 1])) ?>">›</a>
            <?php endif; ?>
          </div>

          <div class="pag-por-pagina">
            <select aria-label="Resultados por página" onchange="location.href = actualizarPorPagina(this.value)">
              <option value="5"  <?= $porPagina === 5  ? 'selected' : '' ?>>5 por página</option>
              <option value="10" <?= $porPagina === 10 ? 'selected' : '' ?>>10 por página</option>
              <option value="25" <?= $porPagina === 25 ? 'selected' : '' ?>>25 por página</option>
              <option value="50" <?= $porPagina === 50 ? 'selected' : '' ?>>50 por página</option>
            </select>
          </div>

        </div>

        <?php endif; ?>

      </div>
      <!-- FIN TABLA WRAPPER -->

    </main>

  </div>
  <!-- FIN MAIN CONTENT -->

</div>
<!-- FIN ADMIN WRAPPER -->


<!-- ============================================================
     MODAL — Agregar / Editar producto
     ============================================================ -->
<div class="modal-overlay" id="modal-producto"
     data-filtro-q="<?= htmlspecialchars($busqueda) ?>"
     data-filtro-cat="<?= (int) $categoriaFiltro ?>"
     data-filtro-estado="<?= htmlspecialchars($estadoFiltro) ?>"
     data-filtro-pagina="<?= (int) $pagina ?>"
     data-filtro-por-pagina="<?= (int) $porPagina ?>">
  <div class="modal-box">
    <!-- Formulario oculto: lo usa confirmarEliminar() (productos.js) para eliminar por POST -->
    <form method="POST" action="productos.php" id="form-eliminar-producto" style="display:none;">
      <?= csrfCampo() ?>
      <input type="hidden" name="accion" value="eliminar">
      <input type="hidden" name="producto_id" value="">
      <input type="hidden" name="q_actual" value="">
      <input type="hidden" name="cat_actual" value="">
      <input type="hidden" name="estado_actual" value="">
      <input type="hidden" name="pagina_actual" value="">
      <input type="hidden" name="por_pagina_actual" value="">
    </form>

    <form method="POST" action="productos.php" id="form-producto" enctype="multipart/form-data">
<?= csrfCampo() ?>
      <input type="hidden" name="accion" value="guardar">
      <input type="hidden" name="producto_id" id="campo-producto-id" value="">
      <input type="hidden" name="imagen_actual" id="campo-imagen-actual" value="">
      <input type="hidden" name="stock_original" id="campo-stock-original" value="">
      <!-- Conservamos los filtros actuales para volver a la misma vista -->
      <input type="hidden" name="q_actual" value="<?= htmlspecialchars($busqueda) ?>">
      <input type="hidden" name="cat_actual" value="<?= (int) $categoriaFiltro ?>">
      <input type="hidden" name="estado_actual" value="<?= htmlspecialchars($estadoFiltro) ?>">
      <input type="hidden" name="pagina_actual" value="<?= (int) $pagina ?>">
      <input type="hidden" name="por_pagina_actual" value="<?= (int) $porPagina ?>">

      <div class="modal-header">
        <h2 id="modal-titulo">Agregar producto</h2>
        <button type="button" class="modal-cerrar" onclick="cerrarModal()" aria-label="Cerrar"><?= icono('x', '', 18) ?></button>
      </div>

      <div class="modal-body">

        <div class="campo-grupo">
          <label for="campo-nombre">Nombre del producto</label>
          <input type="text" id="campo-nombre" name="nombre" required placeholder="Ej: Coca Cola 2.25L">
        </div>

        <div class="campo-grupo">
          <label for="campo-imagen">Foto del producto</label>
          <div class="imagen-upload-box">
            <div class="imagen-preview" id="imagen-preview">
              <span id="imagen-preview-emoji">📦</span>
              <img id="imagen-preview-img" src="" alt="Vista previa" style="display:none;">
            </div>
            <div class="imagen-upload-controles">
              <input type="file" id="campo-imagen" name="imagen" accept="image/jpeg,image/png,image/webp,image/gif" onchange="previsualizarImagen(this)">
              <p class="imagen-upload-ayuda">JPG, PNG, WEBP o GIF. Máximo 5 MB.</p>
              <label class="campo-check" id="opcion-quitar-imagen" style="display:none;">
                <input type="checkbox" id="campo-quitar-imagen" name="quitar_imagen">
                Quitar la foto actual
              </label>
            </div>
          </div>
        </div>

        <div class="campo-fila">
          <div class="campo-grupo">
            <label for="campo-categoria">Categoría</label>
            <select id="campo-categoria" name="categoria_id" required>
              <?php foreach ($categorias as $cat): ?>
                <option value="<?= (int) $cat['id'] ?>"><?= htmlspecialchars($cat['icono'] . ' ' . $cat['nombre']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="campo-grupo">
            <label for="campo-emoji">Ícono de respaldo (emoji)</label>
            <input type="text" id="campo-emoji" name="emoji" maxlength="4" placeholder="🥤">
            <p class="imagen-upload-ayuda">Se usa solo si el producto no tiene foto.</p>
          </div>
        </div>

        <div class="campo-fila">
          <div class="campo-grupo">
            <label for="campo-precio">Precio ($)</label>
            <input type="number" id="campo-precio" name="precio" min="0.01" step="0.01" required placeholder="1200">
          </div>
          <div class="campo-grupo">
            <label for="campo-descuento">Descuento (%)</label>
            <input type="number" id="campo-descuento" name="descuento_pct" min="0" max="100" value="0">
          </div>
        </div>

        <div class="campo-grupo">
          <label for="campo-stock">Stock</label>
          <input type="number" id="campo-stock" name="stock" min="0" max="1000000" value="100">
        </div>

        <div class="campo-fila">
          <label class="campo-check">
            <input type="checkbox" id="campo-destacado" name="destacado">
            Producto destacado
          </label>
          <label class="campo-check">
            <input type="checkbox" id="campo-activo" name="activo" checked>
            Activo (visible en la tienda)
          </label>
        </div>

      </div>

      <div class="modal-footer">
        <button type="button" class="btn-cancelar" onclick="cerrarModal()">Cancelar</button>
        <button type="submit" class="btn-rojo" style="border:none;">Guardar producto</button>
      </div>

    </form>
  </div>
</div>


<script>
  // Si el guardado falló, se reabre el modal con lo que había escrito el admin (null = nada que reabrir).
  window.PRODUCTO_ERROR = <?= json_encode($errorGuardado, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
  window.PRODUCTO_REABRIR = <?= json_encode($reabrirProducto, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
  // ?nuevo=1 (acción rápida del Inicio) abre directamente el formulario de producto nuevo.
  window.PRODUCTO_ABRIR_NUEVO = <?= !empty($_GET['nuevo']) ? 'true' : 'false' ?>;
</script>
<script src="../assets/js/productos.js" defer></script>

</body>
</html>

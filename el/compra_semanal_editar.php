<?php
$pageCss = 'cuenta.css';
// Toda la lógica que puede redirigir con header('Location: ...') va ANTES de
// incluir includes/header.php (ese archivo ya imprime HTML). Si el header se
// incluye primero, PHP tira "Cannot modify header information - headers
// already sent" al guardar. header.php se incluye recién más abajo, justo
// antes de que arranque el HTML de la página (mismo criterio que mi-cuenta.php).
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/funciones.php';
require_once 'includes/compra_semanal.php';
csrfExigir(); // protección CSRF: rechaza cualquier POST sin token válido

if (!usuarioLogueado()) {
  header('Location: login.php?volver=compra_semanal_editar.php');
  exit;
}

$pdo = obtenerConexion();
$usuarioId = (int) $_SESSION['usuario']['id'];

$compraIdParam = isset($_GET['id']) ? (int) $_GET['id'] : null;
$esNueva = isset($_GET['nueva']) || $compraIdParam === null;

// Si pide crear una nueva pero ya llegó al máximo, la mandamos de vuelta
if ($esNueva && contarComprasSemanales($pdo, $usuarioId) >= MAX_COMPRAS_SEMANALES && $_SERVER['REQUEST_METHOD'] !== 'POST') {
  flashSet('error', 'Ya tenés ' . MAX_COMPRAS_SEMANALES . ' Compras Semanales guardadas. Eliminá o editá alguna para poder crear una nueva.');
  header('Location: mi-cuenta.php?seccion=compra-semanal');
  exit;
}

/* ═══════════════════════════════════════════════════════════
   GUARDAR CAMBIOS
   ═══════════════════════════════════════════════════════════ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $nombre = mb_substr(postTexto('nombre') !== '' ? postTexto('nombre') : 'Compra semanal', 0, 100);
  $dia = $_POST['dia_compra'] ?? '';
  $dia = $dia !== '' ? $dia : null;
  $compraIdPost = !empty($_POST['compra_id']) ? (int) $_POST['compra_id'] : null;

  $cantidades = $_POST['cantidad'] ?? []; // [producto_id => cantidad]
  $items = [];
  foreach ($cantidades as $productoId => $cantidad) {
    $cantidad = limitarCantidad((int) $cantidad);
    if ($cantidad > 0) {
      $items[(int) $productoId] = $cantidad;
    }
  }

  if (empty($items)) {
    $errorGuardar = 'Elegí al menos un producto con cantidad mayor a 0 para guardar tu Compra Semanal.';
  } else {
    try {
      $resultado = guardarCompraSemanal($pdo, $usuarioId, $nombre, $dia, $items, $compraIdPost);
    } catch (Throwable $e) {
      // Por ejemplo, un producto_id inexistente manipulado desde el formulario:
      // no mostramos el error de MySQL, solo un aviso genérico.
      error_log('[compra_semanal] ' . $e->getMessage());
      $resultado = null;
      $errorGuardar = 'No pudimos guardar tu Compra Semanal. Revisá los productos e intentá de nuevo.';
    }
    if ($resultado === null) {
      // ya se cargó $errorGuardar arriba
    } elseif ($resultado === false) {
      $errorGuardar = 'Ya tenés ' . MAX_COMPRAS_SEMANALES . ' Compras Semanales guardadas. Eliminá o editá alguna para poder crear una nueva.';
    } else {
      flashSet('ok', 'Tu Compra Semanal se guardó correctamente.');
      header('Location: mi-cuenta.php?seccion=compra-semanal');
      exit;
    }
  }
}

/* ═══════════════════════════════════════════════════════════
   DATOS PARA EL FORMULARIO
   ═══════════════════════════════════════════════════════════ */
$compraSemanal = ($esNueva || $compraIdParam === null) ? null : obtenerCompraSemanalPorId($pdo, $usuarioId, $compraIdParam);

$nombreActual = $compraSemanal['nombre'] ?? '';
$diaActual = $compraSemanal['dia_compra'] ?? '';

$cantidadesActuales = []; // producto_id => cantidad
if ($compraSemanal) {
  foreach ($compraSemanal['items'] as $it) {
    if ($it['producto_id'] !== null) {
      $cantidadesActuales[$it['producto_id']] = $it['cantidad'];
    }
  }
}

$categorias = obtenerCategoriasPublico($pdo);
$stmtProductos = $pdo->query("
  SELECT p.id, p.nombre, p.precio, p.descuento_pct, p.emoji, p.imagen, p.stock, c.nombre AS categoria_nombre, c.id AS categoria_id
  FROM productos p
  JOIN categorias c ON c.id = p.categoria_id
  WHERE p.activo = 1
  ORDER BY c.orden ASC, p.nombre ASC
");
$productos = $stmtProductos->fetchAll();

$productosPorCategoria = [];
foreach ($productos as $p) {
  $productosPorCategoria[$p['categoria_id']][] = $p;
}

// Recién ahora se imprime el HTML del sitio (ya no hay más redirecciones posibles).
$pageTitle = $compraSemanal ? 'Editar Compra Semanal' : 'Crear Compra Semanal';
$pageDescription = 'Elegí los productos y cantidades de tu Compra Semanal en El Rápido.';
$pageRobots = 'noindex,follow'; // área privada
include 'includes/header.php';
?>

<div class="cuenta-wrap">
  <div class="editor-lista-header">
    <a href="mi-cuenta.php?seccion=compra-semanal" class="editor-volver">← Volver a Compra semanal</a>
    <h1 class="cuenta-seccion-titulo"><?= $compraSemanal ? 'Editar productos' : 'Crear mi Compra Semanal' ?></h1>
    <p class="cuenta-seccion-sub">Elegí los productos y las cantidades que querés guardar. Los precios se actualizan solos cada vez que vuelvas a comprar.</p>
  </div>

  <?php if (!empty($errorGuardar)): ?>
    <div class="cuenta-alerta cuenta-alerta-error" role="alert"><?= icono('alerta', '', 16) ?> <?= htmlspecialchars($errorGuardar) ?></div>
  <?php endif; ?>

  <form method="POST" action="compra_semanal_editar.php<?= $compraSemanal ? '?id=' . (int) $compraSemanal['id'] : '' ?>" id="form-editar-lista">
<?= csrfCampo() ?>
    <?php if ($compraSemanal): ?>
      <input type="hidden" name="compra_id" value="<?= (int) $compraSemanal['id'] ?>">
    <?php endif; ?>

    <div class="editor-datos-card">
      <div class="resumen-campo">
        <label for="campo-nombre-lista">Nombre de la lista</label>
        <input type="text" id="campo-nombre-lista" name="nombre" maxlength="100"
               placeholder="Ej: Compra de los viernes, Compra para la casa, Compra del trabajo"
               value="<?= htmlspecialchars($nombreActual) ?>" required>
      </div>
      <div class="resumen-campo">
        <label for="campo-dia-compra">Día habitual de compra (opcional)</label>
        <select id="campo-dia-compra" name="dia_compra">
          <option value="">Sin definir</option>
          <?php foreach (diasSemanaValidos() as $dia): ?>
            <option value="<?= $dia ?>" <?= $diaActual === $dia ? 'selected' : '' ?>><?= diaCompraLabel($dia) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>

    <div class="editor-contador-flotante">
      <span id="contador-seleccionados" aria-live="polite"><?= count($cantidadesActuales) ?></span> producto(s) seleccionados
      <button type="submit" class="btn-guardar-lista"><?= icono('check', '', 16) ?> Guardar Compra Semanal</button>
    </div>

    <?php foreach ($categorias as $cat): if (empty($productosPorCategoria[$cat['id']])) continue; ?>
      <div class="editor-categoria-bloque">
        <h2 class="editor-categoria-titulo"><span class="editor-categoria-icono"><?= iconoCategoria($cat, 22) ?></span> <?= htmlspecialchars($cat['nombre']) ?></h2>
        <div class="editor-productos-grid">
          <?php foreach ($productosPorCategoria[$cat['id']] as $p):
            $cantidadGuardada = $cantidadesActuales[$p['id']] ?? 0;
            $precioFinal = precioConDescuento((float) $p['precio'], (int) $p['descuento_pct']);
          ?>
            <div class="editor-producto-item <?= $cantidadGuardada > 0 ? 'seleccionado' : '' ?>" data-producto-item>
              <div class="editor-producto-img">
                <?php if (!empty($p['imagen'])): ?>
                  <img src="assets/img/productos/<?= htmlspecialchars($p['imagen']) ?>" alt="<?= htmlspecialchars($p['nombre']) ?>" loading="lazy" decoding="async">
                <?php else: ?>
                  <?= htmlspecialchars($p['emoji']) ?>
                <?php endif; ?>
              </div>
              <p class="editor-producto-nombre"><?= htmlspecialchars($p['nombre']) ?></p>
              <p class="editor-producto-precio"><?= formatoPrecio($precioFinal) ?></p>
              <div class="editor-producto-stepper">
                <button type="button" class="stepper-btn" data-restar aria-label="Restar una unidad de <?= htmlspecialchars($p['nombre']) ?>">−</button>
                <input type="number" name="cantidad[<?= (int) $p['id'] ?>]" aria-label="Cantidad de <?= htmlspecialchars($p['nombre']) ?>" min="0" max="<?= (int) $p['stock'] ?>"
                       value="<?= (int) $cantidadGuardada ?>" class="stepper-input" data-input>
                <button type="button" class="stepper-btn" data-sumar aria-label="Sumar una unidad de <?= htmlspecialchars($p['nombre']) ?>">+</button>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endforeach; ?>

    <button type="submit" class="btn-guardar-lista btn-guardar-lista-final"><?= icono('check', '', 16) ?> Guardar Compra Semanal</button>
  </form>
</div>

<script src="<?= htmlspecialchars(assetUrl('assets/js/compra-semanal-editar.js')) ?>" defer></script>

<?php include 'includes/footer.php'; ?>

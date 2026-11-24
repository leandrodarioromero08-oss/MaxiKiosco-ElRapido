<?php
require_once '../Config/funciones.php';
require_once '../Config/auth.php';
requerirAdmin();
csrfExigir(); // protección CSRF: rechaza cualquier POST sin token válido
$pdo = obtenerConexion();

/*
 * GESTIÓN DE CATEGORÍAS
 * Antes las categorías solo se podían tocar con SQL. Acá se crean, renombran, reordenan y
 * eliminan. El "slug" (el nombre que se ve en la URL de la tienda y con el que se elige su ícono SVG)
 * se genera solo al crearla y NO cambia al renombrarla, para no romper links ya existentes.
 */

const SLUGS_RESERVADOS = ['todas', 'promociones']; // los usa la tienda como filtros especiales

/** "Almacén & Más" → "almacen-mas" */
function slugDesdeNombre(string $nombre): string {
  $slug = mb_strtolower($nombre, 'UTF-8');
  $slug = strtr($slug, ['á' => 'a', 'à' => 'a', 'ä' => 'a', 'â' => 'a', 'é' => 'e', 'è' => 'e', 'ë' => 'e', 'ê' => 'e',
                        'í' => 'i', 'ì' => 'i', 'ï' => 'i', 'î' => 'i', 'ó' => 'o', 'ò' => 'o', 'ö' => 'o', 'ô' => 'o',
                        'ú' => 'u', 'ù' => 'u', 'ü' => 'u', 'û' => 'u', 'ñ' => 'n', 'ç' => 'c']);
  $slug = trim(preg_replace('/[^a-z0-9]+/', '-', $slug), '-');
  return $slug !== '' ? mb_substr($slug, 0, 50) : 'categoria';
}

/** Slug libre: si ya existe (o está reservado) le agrega -2, -3... */
function slugLibre(PDO $pdo, string $base): string {
  $slug = $base;
  $n = 2;
  $stmt = $pdo->prepare('SELECT 1 FROM categorias WHERE slug = :s');
  while (true) {
    $stmt->execute([':s' => $slug]);
    if (!in_array($slug, SLUGS_RESERVADOS, true) && !$stmt->fetchColumn()) {
      return $slug;
    }
    $slug = $base . '-' . $n++;
  }
}

$errorGuardado = null;
$reabrirCategoria = null; // si el guardado falla, se reabre el modal con lo que escribió el admin

/* ═══════════════════════════════════════════════════════════
   1) GUARDAR (crear / editar)
   ═══════════════════════════════════════════════════════════ */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'guardar') {
  $categoriaId = !empty($_POST['categoria_id']) ? (int) $_POST['categoria_id'] : null;
  $nombre      = postTexto('nombre');
  $icono       = mb_substr(postTexto('icono') ?: '📦', 0, 10);
  $orden       = (int) ($_POST['orden'] ?? 0);

  if ($nombre === '') {
    $errorGuardado = 'El nombre de la categoría es obligatorio.';
  } elseif (mb_strlen($nombre) > 60) {
    $errorGuardado = 'El nombre es demasiado largo (máximo 60 caracteres).';
  } elseif ($orden < 0 || $orden > 9999) {
    $errorGuardado = 'El orden tiene que ser un número entre 0 y 9999.';
  } else {
    try {
      // Nombre repetido (sin distinguir mayúsculas): confunde al cliente en el menú de la tienda
      $stmtDup = $pdo->prepare('SELECT id FROM categorias WHERE nombre = :n AND id <> :id LIMIT 1');
      $stmtDup->execute([':n' => $nombre, ':id' => $categoriaId ?? 0]);
      if ($stmtDup->fetchColumn()) {
        $errorGuardado = 'Ya existe una categoría con ese nombre.';
      } elseif ($categoriaId) {
        $stmtExiste = $pdo->prepare('SELECT 1 FROM categorias WHERE id = :id');
        $stmtExiste->execute([':id' => $categoriaId]);
        if (!$stmtExiste->fetchColumn()) {
          $errorGuardado = 'La categoría que intentás editar ya no existe.';
        } else {
          $pdo->prepare('UPDATE categorias SET nombre = :n, icono = :i, orden = :o WHERE id = :id')
              ->execute([':n' => $nombre, ':i' => $icono, ':o' => $orden, ':id' => $categoriaId]);
          header('Location: categorias.php?msg=actualizada');
          exit;
        }
      } else {
        $slug = slugLibre($pdo, slugDesdeNombre($nombre));
        $pdo->prepare('INSERT INTO categorias (nombre, slug, icono, orden) VALUES (:n, :s, :i, :o)')
            ->execute([':n' => $nombre, ':s' => $slug, ':i' => $icono, ':o' => $orden]);
        header('Location: categorias.php?msg=creada');
        exit;
      }
    } catch (Throwable $e) {
      error_log('[el-rapido] admin categorias guardar: ' . $e->getMessage());
      $errorGuardado = 'No se pudo guardar la categoría. No se hizo ningún cambio; intentá de nuevo.';
    }
  }
  $reabrirCategoria = ['id' => $categoriaId, 'nombre' => $nombre, 'icono' => $icono, 'orden' => $orden];
}

/* ═══════════════════════════════════════════════════════════
   2) ELIMINAR (solo si no tiene productos)
   ═══════════════════════════════════════════════════════════ */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'eliminar') {
  $idEliminar = (int) ($_POST['categoria_id'] ?? 0);
  try {
    $stmtExiste = $pdo->prepare('SELECT 1 FROM categorias WHERE id = :id');
    $stmtExiste->execute([':id' => $idEliminar]);
    if (!$stmtExiste->fetchColumn()) {
      header('Location: categorias.php?msg=no_existe');
      exit;
    }
    $stmtProd = $pdo->prepare('SELECT COUNT(*) FROM productos WHERE categoria_id = :id');
    $stmtProd->execute([':id' => $idEliminar]);
    if ((int) $stmtProd->fetchColumn() > 0) {
      header('Location: categorias.php?msg=con_productos');
      exit;
    }
    $pdo->prepare('DELETE FROM categorias WHERE id = :id')->execute([':id' => $idEliminar]);
    header('Location: categorias.php?msg=eliminada');
    exit;
  } catch (Throwable $e) {
    error_log('[el-rapido] admin categorias eliminar: ' . $e->getMessage());
    header('Location: categorias.php?msg=error');
    exit;
  }
}

/* ═══════════════════════════════════════════════════════════
   3) LISTADO
   ═══════════════════════════════════════════════════════════ */
$categorias = $pdo->query("
  SELECT c.id, c.nombre, c.slug, c.icono, c.orden,
         COUNT(p.id) AS total_productos,
         COALESCE(SUM(p.activo = 1), 0) AS productos_activos
  FROM categorias c
  LEFT JOIN productos p ON p.categoria_id = c.id
  GROUP BY c.id, c.nombre, c.slug, c.icono, c.orden
  ORDER BY c.orden ASC, c.nombre ASC
")->fetchAll();

$mensajesOk = [
  'creada'      => 'Categoría creada correctamente.',
  'actualizada' => 'Categoría actualizada correctamente.',
  'eliminada'   => 'Categoría eliminada correctamente.',
];
$mensajesError = [
  'con_productos' => 'No se puede eliminar: la categoría todavía tiene productos. Movelos a otra categoría (editando cada producto) y probá de nuevo.',
  'no_existe'     => 'La categoría ya no existe.',
  'error'         => 'No se pudo eliminar la categoría. No se hizo ningún cambio; intentá de nuevo.',
];
$msg = $_GET['msg'] ?? '';

$CSSpage       = 'productos.css';
$tituloPagina  = 'Categorías';
$seccionActiva = 'categorias';
include('../Template/sidebar.php');
include('../Template/topbar.php');
?>

    <main class="page-content">

      <div class="page-header">
        <div>
          <h1 class="page-title">Categorías</h1>
          <p class="page-subtitle">Organizá el catálogo de la tienda</p>
        </div>
        <button type="button" class="btn-rojo" style="display:flex; align-items:center; gap:8px; padding:12px 20px; border:none;" onclick="abrirModalCategoria()">
          <?= icono('mas', '', 18) ?>
          Nueva categoría
        </button>
      </div>

      <?php if (isset($mensajesOk[$msg])): ?>
        <div class="alerta alerta-exito"><?= icono('check-circulo', '', 18) ?> <span><?= htmlspecialchars($mensajesOk[$msg]) ?></span></div>
      <?php elseif (isset($mensajesError[$msg])): ?>
        <div class="alerta alerta-error"><?= icono('alerta', '', 18) ?> <span><?= htmlspecialchars($mensajesError[$msg]) ?></span></div>
      <?php endif; ?>
      <?php if (!empty($errorGuardado)): ?>
        <div class="alerta alerta-error"><?= icono('alerta', '', 18) ?> <span><?= htmlspecialchars($errorGuardado) ?></span></div>
      <?php endif; ?>

      <div class="tabla-wrapper">
        <?php if (empty($categorias)): ?>
          <p class="sin-resultados">Todavía no hay categorías. Creá la primera con "Nueva categoría".</p>
        <?php else: ?>
        <table class="tabla-productos">
          <thead>
            <tr>
              <th>Categoría</th>
              <th>Orden</th>
              <th>Productos</th>
              <th>Activos en tienda</th>
              <th>Acciones</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($categorias as $c): ?>
              <tr>
                <td>
                  <div class="producto-info">
                    <div class="producto-img"><?= htmlspecialchars($c['icono']) ?></div>
                    <div>
                      <p class="producto-nombre"><?= htmlspecialchars($c['nombre']) ?></p>
                      <p class="producto-categoria-sub"><?= htmlspecialchars($c['slug']) ?></p>
                    </div>
                  </div>
                </td>
                <td><?= (int) $c['orden'] ?></td>
                <td><?= (int) $c['total_productos'] ?></td>
                <td><?= (int) $c['productos_activos'] ?></td>
                <td>
                  <div class="acciones-td">
                    <a class="btn-accion" title="Ver sus productos" href="productos.php?cat=<?= (int) $c['id'] ?>"><?= icono('ojo', '', 15) ?></a>
                    <button type="button" class="btn-accion btn-editar" title="Editar"
                      data-id="<?= (int) $c['id'] ?>"
                      data-nombre="<?= htmlspecialchars($c['nombre'], ENT_QUOTES) ?>"
                      data-icono="<?= htmlspecialchars($c['icono'], ENT_QUOTES) ?>"
                      data-orden="<?= (int) $c['orden'] ?>"
                      data-slug="<?= htmlspecialchars($c['slug'], ENT_QUOTES) ?>"
                      onclick="abrirModalCategoria(this)"><?= icono('lapiz', '', 15) ?></button>
                    <button type="button" class="btn-accion btn-eliminar" title="Eliminar"
                      data-id="<?= (int) $c['id'] ?>"
                      data-nombre="<?= htmlspecialchars($c['nombre'], ENT_QUOTES) ?>"
                      data-productos="<?= (int) $c['total_productos'] ?>"
                      onclick="confirmarEliminarCategoria(this)"><?= icono('basura', '', 15) ?></button>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
        <?php endif; ?>
      </div>

    </main>

  </div>
</div>


<!-- ============================================================
     MODAL — Nueva / Editar categoría
     ============================================================ -->
<div class="modal-overlay" id="modal-categoria">
  <div class="modal-box">

    <form method="POST" action="categorias.php" id="form-eliminar-categoria" style="display:none;">
      <?= csrfCampo() ?>
      <input type="hidden" name="accion" value="eliminar">
      <input type="hidden" name="categoria_id" value="">
    </form>

    <form method="POST" action="categorias.php" id="form-categoria">
      <?= csrfCampo() ?>
      <input type="hidden" name="accion" value="guardar">
      <input type="hidden" name="categoria_id" id="campo-categoria-id" value="">

      <div class="modal-header">
        <h2 id="modal-categoria-titulo">Nueva categoría</h2>
        <button type="button" class="modal-cerrar" onclick="cerrarModalCategoria()" aria-label="Cerrar"><?= icono('x', '', 18) ?></button>
      </div>

      <div class="modal-body">
        <div class="campo-grupo">
          <label for="campo-cat-nombre">Nombre</label>
          <input type="text" id="campo-cat-nombre" name="nombre" required maxlength="60" placeholder="Ej: Bebidas">
        </div>
        <div class="campo-fila">
          <div class="campo-grupo">
            <label for="campo-cat-icono">Ícono (emoji)</label>
            <input type="text" id="campo-cat-icono" name="icono" maxlength="4" placeholder="🥤">
            <p class="imagen-upload-ayuda">Solo se ve en este panel. En la tienda cada categoría muestra un ícono SVG según su nombre.</p>
          </div>
          <div class="campo-grupo">
            <label for="campo-cat-orden">Orden</label>
            <input type="number" id="campo-cat-orden" name="orden" min="0" max="9999" value="0">
            <p class="imagen-upload-ayuda">Menor número = aparece primero.</p>
          </div>
        </div>
        <p class="imagen-upload-ayuda" id="campo-cat-slug-ayuda" style="display:none;"></p>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn-cancelar" onclick="cerrarModalCategoria()">Cancelar</button>
        <button type="submit" class="btn-rojo" style="border:none;">Guardar categoría</button>
      </div>
    </form>
  </div>
</div>

<script>
  // Si el guardado falló, se reabre el modal con lo que había escrito el admin (null = nada que reabrir)
  window.CATEGORIA_ERROR = <?= json_encode($errorGuardado, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
  window.CATEGORIA_REABRIR = <?= json_encode($reabrirCategoria, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
</script>
<script src="../assets/js/categorias.js" defer></script>

</body>
</html>

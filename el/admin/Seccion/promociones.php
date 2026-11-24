<?php
require_once '../Config/funciones.php';
require_once '../Config/auth.php';
requerirAdmin();
csrfExigir(); // protección CSRF: rechaza cualquier POST sin token válido
require_once '../../includes/promociones_funciones.php';
$pdo = obtenerConexion();

/* ═══════════════════════════════════════════════════════════
   0) Antes que nada, recalculamos estados según la fecha de hoy
   ═══════════════════════════════════════════════════════════ */
actualizarEstadosPromociones($pdo);

/* Arma la URL de vuelta a promociones.php conservando los filtros actuales */
function urlListadoPromos(array $extra = []): string {
  $actuales = [
    'q'          => $_GET['q'] ?? ($_POST['q_actual'] ?? ''),
    'estado'     => $_GET['estado'] ?? ($_POST['estado_actual'] ?? ''),
    'tipo'       => $_GET['tipo'] ?? ($_POST['tipo_actual'] ?? ''),
    'posicion'   => $_GET['posicion'] ?? ($_POST['posicion_actual'] ?? ''),
    'pagina'     => $_GET['pagina'] ?? ($_POST['pagina_actual'] ?? 1),
    'por_pagina' => $_GET['por_pagina'] ?? ($_POST['por_pagina_actual'] ?? 5),
  ];
  return urlConFiltros('promociones.php', $actuales, $extra);
}

$errorGuardado = null;
$reabrirPromo  = null; // si el guardado falla, se reabre el wizard con lo que escribió el admin

/**
 * Verifica que los productos elegidos existan y que el precio promocional (tipos oferta,
 * oferta_dia y combo) sea realmente MENOR que el precio de la tienda: antes se podía crear
 * una "oferta" más cara que el producto y la tienda la vendía a ese precio.
 * Devuelve el mensaje de error, o null si está todo bien.
 */
function validarProductosYPrecioPromo(PDO $pdo, string $tipo, int $id1, int $id2, ?float $precioPromo): ?string {
  $ids = array_values(array_filter([$id1, $id2]));
  if (empty($ids)) {
    return null; // otro control ya avisa que falta el producto
  }
  $marc = implode(',', array_fill(0, count($ids), '?'));
  $stmt = $pdo->prepare("SELECT id, nombre, ROUND(precio * (100 - LEAST(100, descuento_pct)) / 100, 2) AS precio_final FROM productos WHERE id IN ($marc)");
  $stmt->execute($ids);
  $productos = [];
  foreach ($stmt->fetchAll() as $fila) {
    $productos[(int) $fila['id']] = $fila;
  }
  foreach ($ids as $id) {
    if (!isset($productos[$id])) {
      return 'Uno de los productos elegidos ya no existe en el catálogo. Elegí otro.';
    }
  }
  if ($precioPromo !== null) {
    $precioLista = array_sum(array_map(fn($i) => (float) $productos[$i]['precio_final'], $ids));
    if ($precioPromo >= $precioLista) {
      return 'El precio promocional ($' . number_format($precioPromo, 2, ',', '.') . ') tiene que ser menor que el precio actual en la tienda ($'
        . number_format($precioLista, 2, ',', '.') . ($tipo === 'combo' ? ', sumando los dos productos' : '') . ').';
    }
  }
  return null;
}

/**
 * Cuántas OTRAS promociones (no pausadas ni finalizadas) comparten posición de banner y
 * se superponen en fechas con esta. En la tienda solo se ve una por posición (la de mayor
 * prioridad), así que el admin merece un aviso.
 */
function contarConflictosBanner(PDO $pdo, int $promoId, int $posicion, string $ini, string $fin): int {
  $stmt = $pdo->prepare("
    SELECT COUNT(*) FROM promociones
    WHERE id <> :id AND posicion = :pos AND estado IN ('activa', 'programada')
      AND fecha_inicio <= :fin AND fecha_fin >= :ini
  ");
  $stmt->execute([':id' => $promoId, ':pos' => $posicion, ':ini' => $ini, ':fin' => $fin]);
  return (int) $stmt->fetchColumn();
}

/* ═══════════════════════════════════════════════════════════
   1) GUARDAR (crear/editar)
   ═══════════════════════════════════════════════════════════ */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'guardar') {

  $promoId       = !empty($_POST['promocion_id']) ? (int) $_POST['promocion_id'] : null;
  $tipo          = postTexto('tipo');
  $productoId1   = (int) ($_POST['producto_id'] ?? 0);
  $productoId2   = (int) ($_POST['producto_id_2'] ?? 0);
  $titulo        = postTexto('titulo');
  $descripcion   = postTexto('descripcion') ?: null;
  $precioPromo   = postTexto('precio_promo') !== '' ? (float) str_replace(',', '.', postTexto('precio_promo')) : null;
  // El descuento se valida más abajo (1-90): antes se recortaba en silencio y el admin
  // creía haber guardado un valor que en realidad era otro.
  $descuentoRaw  = postTexto('descuento_pct') !== '' ? (int) postTexto('descuento_pct') : null;
  $descuentoPct  = $descuentoRaw;
  $colorBanner   = postTexto('color_banner') ?: 'rojo';
  // color_hex: solo un color #RRGGBB válido (si no, se descarta). Evita meter CSS arbitrario en el banner.
  $colorHex      = preg_match('/^#[0-9a-fA-F]{6}\z/', postTexto('color_hex')) ? postTexto('color_hex') : null;
  $posicion      = max(1, min(3, (int) ($_POST['posicion'] ?? 1)));
  $prioridad     = max(1, min(10, (int) ($_POST['prioridad'] ?? 5)));
  $fechaInicio   = postTexto('fecha_inicio');
  $fechaFin      = postTexto('fecha_fin');

  $tiposValidos = array_keys(etiquetasTipoPromo());
  $usaDescuento = in_array($tipo, ['2x1', '3x2', 'descuento_pct', 'segunda_unidad'], true);
  // Cada tipo usa UNO de los dos campos; el otro se descarta (un valor viejo que quedó escrito en el
  // wizard al cambiar de tipo no debe guardarse ni disparar validaciones que no corresponden).
  if ($usaDescuento) {
    $precioPromo = null;
  } else {
    $descuentoPct = null;
  }

  // Solo el combo lleva 2 productos. Antes el 2º se guardaba igual aunque el tipo fuera
  // otro, y ese producto sobrante bajaba el stock máximo de la promoción.
  if ($tipo !== 'combo') {
    $productoId2 = 0;
  }

  if (!in_array($tipo, $tiposValidos, true)) {
    $errorGuardado = 'Elegí un tipo de promoción válido.';
  } elseif ($productoId1 <= 0) {
    $errorGuardado = 'Elegí al menos un producto del catálogo.';
  } elseif ($tipo === 'combo' && $productoId2 <= 0) {
    $errorGuardado = 'El combo necesita un segundo producto.';
  } elseif ($tipo === 'combo' && $productoId2 === $productoId1) {
    $errorGuardado = 'Los dos productos del combo tienen que ser distintos.';
  } elseif ($titulo === '') {
    $errorGuardado = 'El título del banner es obligatorio.';
  } elseif (mb_strlen($titulo) > 60 || ($descripcion !== null && mb_strlen($descripcion) > 120)) {
    $errorGuardado = 'El título admite hasta 60 caracteres y la descripción hasta 120.';
  } elseif (!preg_match('/^\d{4}-\d{2}-\d{2}\z/', $fechaInicio) || !preg_match('/^\d{4}-\d{2}-\d{2}\z/', $fechaFin)) {
    $errorGuardado = 'Las fechas no tienen un formato válido.';
  } elseif ($fechaInicio === '' || $fechaFin === '') {
    $errorGuardado = 'Completá la fecha de inicio y de fin.';
  } elseif ($fechaFin < $fechaInicio) {
    $errorGuardado = 'La fecha de fin no puede ser anterior a la de inicio.';
  } elseif ($usaDescuento && !$descuentoPct) {
    $errorGuardado = 'Ingresá el descuento (%) de la promoción.';
  } elseif ($usaDescuento && ($descuentoPct < 1 || $descuentoPct > 90)) {
    $errorGuardado = 'El descuento tiene que estar entre 1% y 90%.';
  } elseif (!$usaDescuento && !$precioPromo) {
    $errorGuardado = 'Ingresá el precio promocional.';
  } elseif (!$usaDescuento && ($precioPromo <= 0 || $precioPromo > 99999999.99)) {
    $errorGuardado = 'El precio promocional no es válido.';
  } elseif (($errorProductos = validarProductosYPrecioPromo($pdo, $tipo, $productoId1, $productoId2, $usaDescuento ? null : $precioPromo)) !== null) {
    $errorGuardado = $errorProductos;
  } elseif ($colorHex !== null && !preg_match('/^#[0-9A-Fa-f]{6}$/', $colorHex)) {
    $errorGuardado = 'El color personalizado tiene que tener el formato #RRGGBB.';
  } elseif (!in_array($colorBanner, ['rojo', 'amarillo', 'negro', 'personalizado'], true)) {
    $errorGuardado = 'Elegí un color de banner válido.';
  } elseif ($colorBanner === 'personalizado' && $colorHex === null) {
    $errorGuardado = 'El color personalizado debe tener el formato #RRGGBB.';
  } else {

    // Si ya estaba Pausada manualmente y solo la estamos editando, la dejamos Pausada;
    // si no, el estado se recalcula solo según las fechas que acaba de poner el admin.
    $estadoActual = null;
    if ($promoId) {
      $stmtEstado = $pdo->prepare('SELECT estado FROM promociones WHERE id = :id');
      $stmtEstado->execute([':id' => $promoId]);
      $estadoActual = $stmtEstado->fetchColumn();
    }
    $nuevoEstado = ($estadoActual === 'pausada') ? 'pausada' : calcularEstadoPorFechas($fechaInicio, $fechaFin);

    // Promoción + sus productos en UNA transacción: si falla algo, no queda una promo sin productos.
    try {
      $pdo->beginTransaction();
      if ($promoId) {
        $stmt = $pdo->prepare("
          UPDATE promociones
          SET tipo = :tipo, titulo = :titulo, descripcion = :desc, precio_promo = :precio,
              descuento_pct = :descuento, color_banner = :color, color_hex = :hex,
              posicion = :pos, prioridad = :prio, fecha_inicio = :ini, fecha_fin = :fin, estado = :estado
          WHERE id = :id
        ");
        $stmt->execute([
          ':tipo' => $tipo, ':titulo' => $titulo, ':desc' => $descripcion, ':precio' => $precioPromo,
          ':descuento' => $descuentoPct, ':color' => $colorBanner, ':hex' => $colorHex,
          ':pos' => $posicion, ':prio' => $prioridad, ':ini' => $fechaInicio, ':fin' => $fechaFin,
          ':estado' => $nuevoEstado, ':id' => $promoId,
        ]);
        guardarProductosDePromocion($pdo, $promoId, [$productoId1, $productoId2]);
        $codigoMsg = 'actualizado';
        $idGuardado = $promoId;
      } else {
        $stmt = $pdo->prepare("
          INSERT INTO promociones (tipo, titulo, descripcion, precio_promo, descuento_pct, color_banner, color_hex, posicion, prioridad, fecha_inicio, fecha_fin, estado)
          VALUES (:tipo, :titulo, :desc, :precio, :descuento, :color, :hex, :pos, :prio, :ini, :fin, :estado)
        ");
        $stmt->execute([
          ':tipo' => $tipo, ':titulo' => $titulo, ':desc' => $descripcion, ':precio' => $precioPromo,
          ':descuento' => $descuentoPct, ':color' => $colorBanner, ':hex' => $colorHex,
          ':pos' => $posicion, ':prio' => $prioridad, ':ini' => $fechaInicio, ':fin' => $fechaFin,
          ':estado' => $nuevoEstado,
        ]);
        $nuevoId = (int) $pdo->lastInsertId();
        guardarProductosDePromocion($pdo, $nuevoId, [$productoId1, $productoId2]);
        $codigoMsg = 'creado';
        $idGuardado = $nuevoId;
      }
      $pdo->commit();
      $conflictos = $nuevoEstado === 'pausada' ? 0 : contarConflictosBanner($pdo, $idGuardado, $posicion, $fechaInicio, $fechaFin);
      header('Location: ' . urlListadoPromos(['msg' => $codigoMsg, 'conf' => $conflictos ?: null]));
      exit;
    } catch (Throwable $e) {
      if ($pdo->inTransaction()) {
        $pdo->rollBack();
      }
      error_log('[el-rapido] admin promociones guardar: ' . $e->getMessage());
      $errorGuardado = 'No se pudo guardar la promoción. No se hizo ningún cambio; revisá los datos e intentá de nuevo.';
    }
  }

  // Algo falló: se reabre el wizard con lo que había escrito el admin (en vez de perderlo todo)
  if ($errorGuardado !== null) {
    $idsRe = array_values(array_filter([$productoId1, $productoId2]));
    $prodsRe = [];
    if ($idsRe) {
      $marc = implode(',', array_fill(0, count($idsRe), '?'));
      $stmtRe = $pdo->prepare("SELECT id, nombre, precio, emoji, imagen, stock FROM productos WHERE id IN ($marc)");
      $stmtRe->execute($idsRe);
      $porId = [];
      foreach ($stmtRe->fetchAll() as $f) { $porId[(int) $f['id']] = $f; }
      foreach ([$productoId1 => 'principal', $productoId2 => 'secundario'] as $idP => $rolP) {
        if ($idP > 0 && isset($porId[$idP])) { $prodsRe[] = $porId[$idP] + ['rol' => $rolP]; }
      }
    }
    $reabrirPromo = [
      'id' => $promoId, 'tipo' => $tipo, 'titulo' => $titulo, 'descripcion' => $descripcion,
      'precio_promo' => $precioPromo, 'descuento_pct' => $descuentoPct, 'color_banner' => $colorBanner,
      'color_hex' => $colorHex, 'posicion' => $posicion, 'prioridad' => $prioridad,
      'fecha_inicio' => $fechaInicio, 'fecha_fin' => $fechaFin, 'productos' => $prodsRe,
    ];
  }
}

/* ═══════════════════════════════════════════════════════════
   2) ELIMINAR / PAUSAR-REANUDAR / DUPLICAR
   ═══════════════════════════════════════════════════════════ */
// Eliminar / pausar / duplicar: solo por POST con token CSRF (antes eran links GET).
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'eliminar') {
  try {
    $stmt = $pdo->prepare("DELETE FROM promociones WHERE id = :id");
    $stmt->execute([':id' => (int) ($_POST['promocion_id'] ?? 0)]);
    // rowCount() = 0 → la promoción ya no existía (la borraron desde otra pestaña): no mentimos con "eliminada"
    $codigo = $stmt->rowCount() > 0 ? 'eliminado' : 'no_existe';
  } catch (Throwable $e) {
    error_log('[el-rapido] admin promociones eliminar: ' . $e->getMessage());
    $codigo = 'error_accion';
  }
  header('Location: ' . urlListadoPromos(['msg' => $codigo]));
  exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'pausar') {
  $id = (int) ($_POST['promocion_id'] ?? 0);
  $codigo = 'no_existe';
  try {
    $stmt = $pdo->prepare("SELECT estado, fecha_inicio, fecha_fin FROM promociones WHERE id = :id");
    $stmt->execute([':id' => $id]);
    $fila = $stmt->fetch();
    if ($fila) {
      $estabaPausada = $fila['estado'] === 'pausada';
      $nuevoEstado = $estabaPausada
        ? calcularEstadoPorFechas($fila['fecha_inicio'], $fila['fecha_fin'])
        : 'pausada';
      $pdo->prepare("UPDATE promociones SET estado = :e WHERE id = :id")->execute([':e' => $nuevoEstado, ':id' => $id]);
      $codigo = $estabaPausada ? 'reanudado' : 'pausado';
    }
  } catch (Throwable $e) {
    error_log('[el-rapido] admin promociones pausar: ' . $e->getMessage());
    $codigo = 'error_accion';
  }
  header('Location: ' . urlListadoPromos(['msg' => $codigo]));
  exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'duplicar') {
  $original = obtenerPromocionPorId($pdo, (int) ($_POST['promocion_id'] ?? 0));
  if (!$original) {
    header('Location: ' . urlListadoPromos(['msg' => 'no_existe']));
    exit;
  }
  try {
    // La copia nace PAUSADA: antes nacía "programada" con fechas hoy → +7, la rutina de estados la
    // pasaba a "activa" al instante y, con igual prioridad, tapaba a la original en la portada.
    // El admin la edita (fechas, precio) y recién ahí la reanuda.
    $pdo->beginTransaction();
    $stmt = $pdo->prepare("
      INSERT INTO promociones (tipo, titulo, descripcion, precio_promo, descuento_pct, color_banner, color_hex, posicion, prioridad, fecha_inicio, fecha_fin, estado)
      VALUES (:tipo, :titulo, :desc, :precio, :descuento, :color, :hex, :pos, :prio, :ini, :fin, 'pausada')
    ");
    $ini = date('Y-m-d');
    $fin = date('Y-m-d', strtotime('+7 days'));
    // El título es VARCHAR(60): se recorta para que " (copia)" nunca lo desborde (antes daba error 500)
    $sufijo = ' (copia)';
    $tituloCopia = mb_substr($original['titulo'], 0, 60 - mb_strlen($sufijo)) . $sufijo;
    $stmt->execute([
      ':tipo' => $original['tipo'], ':titulo' => $tituloCopia, ':desc' => $original['descripcion'],
      ':precio' => $original['precio_promo'], ':descuento' => $original['descuento_pct'],
      ':color' => $original['color_banner'], ':hex' => $original['color_hex'],
      ':pos' => $original['posicion'], ':prio' => $original['prioridad'], ':ini' => $ini, ':fin' => $fin,
    ]);
    $nuevoId = (int) $pdo->lastInsertId();
    guardarProductosDePromocion($pdo, $nuevoId, array_column($original['productos'], 'producto_id'));
    $pdo->commit();
    $codigo = 'duplicado';
  } catch (Throwable $e) {
    if ($pdo->inTransaction()) {
      $pdo->rollBack();
    }
    error_log('[el-rapido] admin promociones duplicar: ' . $e->getMessage());
    $codigo = 'error_accion';
  }
  header('Location: ' . urlListadoPromos(['msg' => $codigo]));
  exit;
}

/* ═══════════════════════════════════════════════════════════
   3) FILTROS + LISTADO
   ═══════════════════════════════════════════════════════════ */
$busqueda        = trim($_GET['q'] ?? '');
$estadoFiltro    = $_GET['estado'] ?? '';
$tipoFiltro      = $_GET['tipo'] ?? '';
$posicionFiltro  = isset($_GET['posicion']) && $_GET['posicion'] !== '' ? (int) $_GET['posicion'] : 0;
$pagina          = max(1, (int) ($_GET['pagina'] ?? 1));
$porPagina       = (int) ($_GET['por_pagina'] ?? 5);
if (!in_array($porPagina, [5, 10, 25], true)) {
  $porPagina = 5;
}

[$where, $params] = construirFiltroPromociones($busqueda, $estadoFiltro, $tipoFiltro, $posicionFiltro);
$totalFiltrado = contarPromocionesFiltradas($pdo, $where, $params);
$totalPaginas  = max(1, (int) ceil($totalFiltrado / $porPagina));
if ($pagina > $totalPaginas) {
  $pagina = $totalPaginas;
}
$offset = ($pagina - 1) * $porPagina;
$promociones = obtenerPromocionesFiltradas($pdo, $where, $params, $porPagina, $offset);

/* ═══════════════════════════════════════════════════════════
   4) ESTADÍSTICAS REALES
   ═══════════════════════════════════════════════════════════ */
$totalPromos     = (int) $pdo->query("SELECT COUNT(*) FROM promociones")->fetchColumn();
$totalActivas    = (int) $pdo->query("SELECT COUNT(*) FROM promociones WHERE estado = 'activa'")->fetchColumn();
$totalPorVencer  = (int) $pdo->query("SELECT COUNT(*) FROM promociones WHERE estado = 'activa' AND fecha_fin BETWEEN CURDATE() AND CURDATE() + INTERVAL 7 DAY")->fetchColumn();
$ventasTotales   = (float) $pdo->query("SELECT COALESCE(SUM(ventas_monto),0) FROM promociones")->fetchColumn();

$mensajesOk = [
  'creado'      => 'Promoción creada correctamente.',
  'actualizado' => 'Promoción actualizada correctamente.',
  'eliminado'   => 'Promoción eliminada correctamente.',
  'duplicado'   => 'Promoción duplicada. La copia quedó PAUSADA: editá sus fechas y reanudala cuando esté lista.',
  'pausado'     => 'Promoción pausada: ya no se muestra en la tienda.',
  'reanudado'   => 'Promoción reanudada: vuelve a mostrarse según sus fechas.',
];
$mensajesError = [
  'no_existe'    => 'La promoción ya no existe (¿la eliminaron desde otra pantalla?).',
  'error_accion' => 'No se pudo completar la acción. No se hizo ningún cambio; intentá de nuevo.',
];
$conflictosAviso = max(0, (int) ($_GET['conf'] ?? 0));
$msg = $_GET['msg'] ?? null;

$rutaImagenesAdmin = '../../assets/img/productos/';
$etiquetasEstado = [
  'activa'      => ['Activa', 'badge-activa'],
  'programada'  => ['Programada', 'badge-programada'],
  'finalizada'  => ['Finalizada', 'badge-finalizada'],
  'pausada'     => ['Pausada', 'badge-pausada'],
];

/* Íconos SVG para los tipos de promoción del panel admin (wizard y filtro).
 * Van acá, separados de etiquetasTipoPromo(), porque esa función también
 * la usa el sitio público y ahí el "ícono" sigue siendo el emoji de la
 * vista previa del banner — no queremos tocar eso. */
$iconosTipoPromo = [
  'oferta'         => 'explosion',
  'combo'          => 'combo',
  '2x1'            => 'numero-2',
  '3x2'            => 'numero-3',
  'descuento_pct'  => 'etiqueta',
  'segunda_unidad' => 'reciclar',
  'oferta_dia'     => 'reloj-alarma',
];

$CSSpage = 'promociones.css';
$tituloPagina = 'Promociones';
$seccionActiva = 'promociones';
include('../Template/sidebar.php');
include('../Template/topbar.php');
?>

    <main class="page-content">

      <!-- Título + Botón nueva promoción -->
      <div class="page-header">
        <div>
          <h1 class="page-title">Promociones</h1>
          <p class="page-subtitle">Crea y gestiona las promociones de tu kiosco</p>
        </div>
        <button type="button" class="btn-rojo" style="display:flex; align-items:center; gap:8px; padding:12px 20px; border:none;" onclick="abrirWizardNuevo()">
          <?= icono('mas', '', 18) ?>
          Nueva promoción
        </button>
      </div>

      <?php if ($msg && isset($mensajesOk[$msg])): ?>
        <div class="alerta alerta-exito"><?= icono('check-circulo', '', 18) ?> <span><?= htmlspecialchars($mensajesOk[$msg]) ?></span></div>
      <?php elseif ($msg && isset($mensajesError[$msg])): ?>
        <div class="alerta alerta-error"><?= icono('alerta', '', 18) ?> <span><?= htmlspecialchars($mensajesError[$msg]) ?></span></div>
      <?php endif; ?>
      <?php if ($conflictosAviso > 0 && in_array($msg, ['creado', 'actualizado'], true)): ?>
        <div class="alerta alerta-aviso"><?= icono('info', '', 18) ?> <span>Atención: hay <?= $conflictosAviso ?> promoción(es) con fechas superpuestas en el mismo banner. En la tienda solo se muestra la de mayor prioridad (número más bajo); si querés que se vean las dos, cambiá la posición o las fechas.</span></div>
      <?php endif; ?>
      <?php if (!empty($errorGuardado)): ?>
        <div class="alerta alerta-error"><?= icono('alerta', '', 18) ?> <span><?= htmlspecialchars($errorGuardado) ?></span></div>
      <?php endif; ?>

      <!-- ======================================
           4 TARJETAS DE ESTADÍSTICAS (reales)
           ====================================== -->
      <div class="stats-grid">

        <div class="stat-card">
          <div class="stat-icon"><?= icono('etiqueta', '', 24) ?></div>
          <div class="stat-info">
            <p class="stat-label">Total promociones</p>
            <p class="stat-value"><?= $totalPromos ?></p>
          </div>
        </div>

        <div class="stat-card">
          <div class="stat-icon tono-verde"><?= icono('calendario', '', 24) ?></div>
          <div class="stat-info">
            <p class="stat-label">Promociones activas</p>
            <p class="stat-value"><?= $totalActivas ?></p>
          </div>
        </div>

        <div class="stat-card">
          <div class="stat-icon tono-naranja"><?= icono('reloj', '', 24) ?></div>
          <div class="stat-info">
            <p class="stat-label">Próximas a vencer</p>
            <p class="stat-value"><?= $totalPorVencer ?></p>
            <a href="<?= htmlspecialchars(urlListadoPromos(['estado' => 'activa'])) ?>" class="stat-link">Ver activas →</a>
          </div>
        </div>

        <div class="stat-card">
          <div class="stat-icon tono-amarillo"><?= icono('grafico', '', 24) ?></div>
          <div class="stat-info">
            <p class="stat-label">Ventas generadas</p>
            <p class="stat-value"><?= formatoMoneda($ventasTotales) ?></p>
          </div>
        </div>

      </div>

      <!-- ======================================
           BARRA DE FILTROS
           ====================================== -->
      <form class="filtros-bar" method="GET" action="promociones.php">

        <div class="filtro-buscar">
          <span class="search-icon"><?= icono('buscar', '', 15) ?></span>
          <input type="text" name="q" aria-label="Buscar promociones" placeholder="Buscar promociones..." value="<?= htmlspecialchars($busqueda) ?>">
        </div>

        <select class="filtro-select" name="tipo" aria-label="Filtrar por tipo" onchange="this.form.submit()">
          <option value="">Todos los tipos</option>
          <?php foreach (etiquetasTipoPromo() as $clave => [$etiqueta, $icono]): ?>
            <option value="<?= $clave ?>" <?= $tipoFiltro === $clave ? 'selected' : '' ?>><?= $etiqueta ?></option>
          <?php endforeach; ?>
        </select>

        <select class="filtro-select" name="estado" aria-label="Filtrar por estado" onchange="this.form.submit()">
          <option value="">Estado</option>
          <?php foreach ($etiquetasEstado as $clave => [$etiqueta, $clase]): ?>
            <option value="<?= $clave ?>" <?= $estadoFiltro === $clave ? 'selected' : '' ?>><?= $etiqueta ?></option>
          <?php endforeach; ?>
        </select>

        <select class="filtro-select" name="posicion" aria-label="Filtrar por posición" onchange="this.form.submit()">
          <option value="">Todas las posiciones</option>
          <option value="1" <?= $posicionFiltro === 1 ? 'selected' : '' ?>>Banner 1</option>
          <option value="2" <?= $posicionFiltro === 2 ? 'selected' : '' ?>>Banner 2</option>
          <option value="3" <?= $posicionFiltro === 3 ? 'selected' : '' ?>>Banner 3</option>
        </select>

        <input type="hidden" name="pagina" value="1">

        <button type="submit" class="btn-filtros"><?= icono('filtrar', '', 15) ?> Filtrar</button>

      </form>

      <!-- ======================================
           TABLA DE PROMOCIONES (datos reales)
           ====================================== -->
      <div class="tabla-wrapper">

        <?php if (empty($promociones)): ?>

          <p class="sin-resultados">No hay promociones que coincidan con estos filtros.</p>

        <?php else: ?>

        <table class="tabla-promociones">
          <thead>
            <tr>
              <th>Promoción</th>
              <th>Tipo</th>
              <th>Vigencia</th>
              <th>Estado</th>
              <th>Posición</th>
              <th>Ventas generadas</th>
              <th>Acciones</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($promociones as $p): ?>
              <?php [$estadoTxt, $estadoClase] = $etiquetasEstado[$p['estado']]; ?>
              <tr>
                <td>
                  <div class="promo-info">
                    <div class="promo-mini"><?= renderBannerPromo($p, $p['productos'], $rutaImagenesAdmin) ?></div>
                    <div>
                      <span class="promo-nombre"><?= htmlspecialchars($p['titulo']) ?></span><br>
                      <span class="promo-desc"><?= htmlspecialchars($p['descripcion'] ?? '') ?></span>
                    </div>
                  </div>
                </td>
                <td><?= htmlspecialchars(etiquetasTipoPromo()[$p['tipo']][0] ?? $p['tipo']) ?></td>
                <td>
                  <span class="vigencia-desde"><?= date('d/m/Y', strtotime($p['fecha_inicio'])) ?></span>
                  <span class="vigencia-hasta"><?= date('d/m/Y', strtotime($p['fecha_fin'])) ?></span>
                </td>
                <td><span class="<?= $estadoClase ?>"><?= $estadoTxt ?></span></td>
                <td>Banner <?= (int) $p['posicion'] ?></td>
                <td>
                  <span class="ventas-monto"><?= formatoMoneda((float) $p['ventas_monto']) ?></span>
                  <span class="ventas-unidades"><?= (int) $p['ventas_unidades'] ?> unidades</span>
                </td>
                <td>
                  <div class="acciones-td">
                    <button type="button" class="btn-accion btn-editar" title="Editar"
                      data-json='<?= htmlspecialchars(json_encode($p, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE) ?: '{}', ENT_QUOTES) ?>'
                      onclick="abrirWizardEditar(this)"><?= icono('lapiz', '', 15) ?></button>
                    <button type="button" class="btn-accion" title="Duplicar" onclick="accionPromo('duplicar', <?= (int) $p['id'] ?>)"><?= icono('copiar', '', 15) ?></button>
                    <button type="button" class="btn-accion" title="<?= $p['estado'] === 'pausada' ? 'Reanudar' : 'Pausar' ?>" onclick="accionPromo('pausar', <?= (int) $p['id'] ?>)"><?= $p['estado'] === 'pausada' ? icono('reproducir', '', 15) : icono('pausa', '', 15) ?></button>
                    <button type="button" class="btn-accion btn-eliminar" title="Eliminar"
                      data-id="<?= (int) $p['id'] ?>" data-titulo="<?= htmlspecialchars($p['titulo'], ENT_QUOTES) ?>"
                      onclick="confirmarEliminarPromo(this)"><?= icono('basura', '', 15) ?></button>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>

        <!-- PAGINACIÓN -->
        <div class="paginacion-wrapper">
          <span>Mostrando <?= $totalFiltrado === 0 ? 0 : $offset + 1 ?> a <?= min($offset + $porPagina, $totalFiltrado) ?> de <?= $totalFiltrado ?> promociones</span>

          <div class="paginacion-botones">
            <?php if ($pagina > 1): ?>
              <a class="pag-btn" href="<?= htmlspecialchars(urlListadoPromos(['pagina' => $pagina - 1])) ?>">‹</a>
            <?php endif; ?>
            <?php foreach (paginasVisibles($pagina, $totalPaginas) as $i): ?>
              <?php if ($i === null): ?>
                <span class="pag-btn" style="pointer-events:none;">…</span>
              <?php else: ?>
                <a class="pag-btn <?= $i === $pagina ? 'activa' : '' ?>" href="<?= htmlspecialchars(urlListadoPromos(['pagina' => $i])) ?>"><?= $i ?></a>
              <?php endif; ?>
            <?php endforeach; ?>
            <?php if ($pagina < $totalPaginas): ?>
              <a class="pag-btn" href="<?= htmlspecialchars(urlListadoPromos(['pagina' => $pagina + 1])) ?>">›</a>
            <?php endif; ?>
          </div>

          <div class="pag-por-pagina">
            <select aria-label="Resultados por página" onchange="location.href = actualizarPorPaginaPromos(this.value)">
              <option value="5"  <?= $porPagina === 5  ? 'selected' : '' ?>>5 por página</option>
              <option value="10" <?= $porPagina === 10 ? 'selected' : '' ?>>10 por página</option>
              <option value="25" <?= $porPagina === 25 ? 'selected' : '' ?>>25 por página</option>
            </select>
          </div>
        </div>

        <?php endif; ?>

      </div>
      <!-- FIN TABLA WRAPPER -->

    </main>

  </div>

</div>


<!-- ============================================================
     MODAL — Wizard de 4 pasos: Nueva / Editar promoción
     ============================================================ -->
<div class="modal-overlay" id="modal-promo"
     data-filtro-q="<?= htmlspecialchars($busqueda) ?>"
     data-filtro-estado="<?= htmlspecialchars($estadoFiltro) ?>"
     data-filtro-tipo="<?= htmlspecialchars($tipoFiltro) ?>"
     data-filtro-posicion="<?= (int) $posicionFiltro ?>"
     data-filtro-pagina="<?= (int) $pagina ?>"
     data-filtro-por-pagina="<?= (int) $porPagina ?>">
  <div class="modal-box modal-box-ancho">
    <!-- Formulario oculto: lo usa accionPromo()/confirmarEliminarPromo() (promociones.js) para eliminar/pausar/duplicar por POST -->
    <form method="POST" action="promociones.php" id="form-accion-promo" style="display:none;">
      <?= csrfCampo() ?>
      <input type="hidden" name="accion" value="">
      <input type="hidden" name="promocion_id" value="">
      <input type="hidden" name="q_actual" value="">
      <input type="hidden" name="estado_actual" value="">
      <input type="hidden" name="tipo_actual" value="">
      <input type="hidden" name="posicion_actual" value="">
      <input type="hidden" name="pagina_actual" value="">
      <input type="hidden" name="por_pagina_actual" value="">
    </form>

    <form method="POST" action="promociones.php" id="form-promo">
<?= csrfCampo() ?>
      <input type="hidden" name="accion" value="guardar">
      <input type="hidden" name="promocion_id" id="campo-promo-id" value="">
      <input type="hidden" name="tipo" id="campo-tipo" value="">
      <input type="hidden" name="producto_id" id="campo-producto-id" value="">
      <input type="hidden" name="producto_id_2" id="campo-producto-id-2" value="">
      <input type="hidden" name="color_banner" id="campo-color-banner" value="rojo">
      <input type="hidden" name="color_hex" id="campo-color-hex" value="">
      <input type="hidden" name="posicion" id="campo-posicion" value="1">
      <!-- conservar filtros actuales -->
      <input type="hidden" name="q_actual" value="<?= htmlspecialchars($busqueda) ?>">
      <input type="hidden" name="estado_actual" value="<?= htmlspecialchars($estadoFiltro) ?>">
      <input type="hidden" name="tipo_actual" value="<?= htmlspecialchars($tipoFiltro) ?>">
      <input type="hidden" name="posicion_actual" value="<?= (int) $posicionFiltro ?>">
      <input type="hidden" name="pagina_actual" value="<?= (int) $pagina ?>">
      <input type="hidden" name="por_pagina_actual" value="<?= (int) $porPagina ?>">

      <div class="modal-header">
        <h2 id="modal-promo-titulo">Nueva promoción</h2>
        <button type="button" class="modal-cerrar" onclick="cerrarModalPromo()" aria-label="Cerrar"><?= icono('x', '', 18) ?></button>
      </div>

      <div class="modal-body">

        <div class="wizard-steps">
          <div class="wizard-step on" id="wtab1" onclick="irPasoWizard(1)">1. Tipo</div>
          <div class="wizard-step" id="wtab2" onclick="irPasoWizard(2)">2. Producto</div>
          <div class="wizard-step" id="wtab3" onclick="irPasoWizard(3)">3. Banner</div>
          <div class="wizard-step" id="wtab4" onclick="irPasoWizard(4)">4. Confirmar</div>
        </div>

        <div class="wizard-layout">
          <!-- COLUMNA FORMULARIO -->
          <div>
            <!-- PASO 1: TIPO -->
            <div class="wizard-panel on" id="wp1">
              <div class="tipos-grid" id="wizardTiposGrid"></div>
            </div>

            <!-- PASO 2: PRODUCTOS -->
            <div class="wizard-panel" id="wp2">
              <label for="wBuscadorProd">Buscar producto del catálogo</label>
              <div class="buscador-prod-wizard">
                <input type="text" id="wBuscadorProd" placeholder="Ej: Coca Cola, Papas Lay's...">
                <div class="prod-resultados" id="wResultadosProd" style="display:none;"></div>
              </div>
              <div id="wProductoElegido"></div>

              <div id="wSegundoProductoWrap" style="display:none;">
                <label for="wBuscadorProd2">Segundo producto del combo</label>
                <div class="buscador-prod-wizard">
                  <input type="text" id="wBuscadorProd2" placeholder="Ej: Papas Lay's...">
                  <div class="prod-resultados" id="wResultadosProd2" style="display:none;"></div>
                </div>
                <div id="wProductoElegido2"></div>
              </div>
            </div>

            <!-- PASO 3: DATOS DEL BANNER -->
            <div class="wizard-panel" id="wp3">
              <div class="campo-grupo">
                <label for="wTitulo">Título del banner</label>
                <input type="text" id="wTitulo" name="titulo" maxlength="60">
              </div>
              <div class="campo-grupo">
                <label for="wDesc">Descripción corta (opcional)</label>
                <input type="text" id="wDesc" name="descripcion" maxlength="120">
              </div>

              <div class="campo-fila">
                <div class="campo-grupo" id="wWrapPrecio">
                  <label for="wPrecio" id="wLblPrecio">Precio promocional</label>
                  <input type="number" id="wPrecio" name="precio_promo" min="0" step="0.01">
                </div>
                <div class="campo-grupo" id="wWrapDescuento" style="display:none;">
                  <label for="wDescuento">Descuento (%)</label>
                  <input type="number" id="wDescuento" name="descuento_pct" min="1" max="90">
                </div>
              </div>

              <div class="campo-grupo">
                <label>Color del banner</label>
                <div class="colores-wizard">
                  <div role="button" tabindex="0" class="color-op sel" style="background:#D91F26" data-color="rojo" data-hex="#D91F26" onclick="elegirColorWizard(this)"></div>
                  <div role="button" tabindex="0" class="color-op" style="background:#FFD600" data-color="amarillo" data-hex="#FFD600" onclick="elegirColorWizard(this)"></div>
                  <div role="button" tabindex="0" class="color-op" style="background:#1a1a2e" data-color="negro" data-hex="#1a1a2e" onclick="elegirColorWizard(this)"></div>
                </div>
              </div>

              <div class="campo-grupo">
                <label>Posición en la portada</label>
                <div class="pos-grid-wizard">
                  <div role="button" tabindex="0" class="pos-op sel" data-pos="1" onclick="elegirPosWizard(this)">Banner 1</div>
                  <div role="button" tabindex="0" class="pos-op" data-pos="2" onclick="elegirPosWizard(this)">Banner 2</div>
                  <div role="button" tabindex="0" class="pos-op" data-pos="3" onclick="elegirPosWizard(this)">Banner 3</div>
                </div>
              </div>

              <div class="campo-fila">
                <div class="campo-grupo">
                  <label for="wPrioridad">Prioridad (1 = más alta)</label>
                  <input type="number" id="wPrioridad" name="prioridad" min="1" max="10" value="5">
                </div>
                <div class="campo-grupo">
                  <label style="visibility:hidden;">.</label>
                  <p style="font-size:11px;color:var(--gris-texto);">Si dos promos activas compiten por el mismo banner, gana la de prioridad más alta.</p>
                </div>
              </div>

              <div class="campo-fila">
                <div class="campo-grupo">
                  <label for="wInicio">Fecha de inicio</label>
                  <input type="date" id="wInicio" name="fecha_inicio">
                </div>
                <div class="campo-grupo">
                  <label for="wFin">Fecha de fin</label>
                  <input type="date" id="wFin" name="fecha_fin">
                </div>
              </div>
            </div>

            <!-- PASO 4: CONFIRMAR -->
            <div class="wizard-panel" id="wp4">
              <p style="font-size:13px;color:var(--gris-texto);">Revisá la vista previa a la derecha antes de guardar.</p>
              <div class="resumen-confirmar" id="wResumen"></div>
            </div>

            <div class="wizard-botones">
              <button type="button" class="btn-cancelar" id="wBtnAtras" onclick="cambiarPasoWizard(-1)">← Atrás</button>
              <button type="button" class="btn-rojo" id="wBtnSiguiente" style="border:none;" onclick="cambiarPasoWizard(1)">Siguiente →</button>
            </div>
          </div>

          <!-- COLUMNA VISTA PREVIA -->
          <div class="wizard-preview-col">
            <div class="wizard-preview-titulo">Vista previa en vivo</div>
            <span class="estado-chip estado-programada" id="wChipEstado" style="display:inline-block;font-size:11px;font-weight:700;padding:3px 10px;border-radius:20px;margin-bottom:12px;">Programada</span>

            <div class="wizard-preview-box" id="wBannerPreview">
              <div class="promo-card promo-card-roja">
                <div>
                  <span class="promo-badge promo-badge-blanco">OFERTA</span>
                  <div class="promo-titulo">ELEGÍ UN PRODUCTO</div>
                  <div class="promo-subtitulo"></div>
                  <div class="promo-precio"></div>
                </div>
                <div class="promo-imagen">🛍️</div>
              </div>
            </div>

            <div class="mini-home-wizard">
              <div class="lbl">ASÍ SE VE EN LA PORTADA</div>
              <div class="mini-slots-wizard">
                <div class="mini-slot-wizard activo" id="wSlot1">Banner 1</div>
                <div class="mini-slot-wizard" id="wSlot2">Banner 2</div>
                <div class="mini-slot-wizard" id="wSlot3">Banner 3</div>
              </div>
            </div>
          </div>
        </div>

      </div>
    </form>
  </div>
</div>


<script>
  // Estos dos vienen de PHP (catálogo de tipos de promoción definido en
  // el backend); todo lo demás vive en promociones.js, sin PHP mezclado.
  // El "ic" ahora es el SVG (helper icono() de Config/iconos.php) y no el
  // emoji de etiquetasTipoPromo(), que sigue existiendo tal cual para el
  // sitio público.
  // Tiene que quedar como OBJETO indexado por clave ({"oferta": {...}, "2x1": {...}}),
  // porque promociones.js hace TIPOS_PROMO[clave]. array_map() con dos arrays
  // devolvía una lista numérica (0,1,2...) y el wizard fallaba al abrirse.
  <?php
    $tiposPromoJs = [];
    foreach (etiquetasTipoPromo() as $clave => $v) {
      $tiposPromoJs[$clave] = ['label' => $v[0], 'ic' => icono($iconosTipoPromo[$clave] ?? 'etiqueta', '', 20)];
    }
  ?>
  window.TIPOS_PROMO = <?= json_encode($tiposPromoJs) ?>;
  window.TIPOS_KEYS = <?= json_encode(array_keys(etiquetasTipoPromo())) ?>;
</script>
<script>
  // Si el guardado falló, se reabre el wizard con lo que había escrito el admin (null = nada que reabrir).
  window.PROMO_ERROR = <?= json_encode($errorGuardado, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
  window.PROMO_REABRIR = <?= json_encode($reabrirPromo, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
</script>
<script src="../assets/js/promociones.js" defer></script>

</body>
</html>

<?php
/**
 * PROMOCIONES — módulo compartido
 * Lo usan TANTO el admin (admin/Seccion/promociones.php) COMO el sitio
 * público (index.php, carrito.php). Por eso vive en /includes y no en
 * /admin: así el banner que arma renderBannerPromo() es EXACTAMENTE el
 * mismo HTML/CSS en la vista previa del admin y en la portada real.
 * Nunca se genera ni se sube una imagen: el banner es siempre este
 * fragmento de HTML, que hereda el CSS del sitio (assets/css/index.css).
 */

/* ═══════════════════════════════════════════════════════════
   RENDER DEL BANNER
   ═══════════════════════════════════════════════════════════ */

function claseColorPromo(string $color): string {
  return [
    'rojo'          => 'promo-card-roja',
    'amarillo'      => 'promo-card-amarilla',
    'negro'         => 'promo-card-verde', // reutiliza la clase oscura ya existente en index.css
    'personalizado' => 'promo-card-personalizada',
  ][$color] ?? 'promo-card-roja';
}

function etiquetasTipoPromo(): array {
  return [
    'oferta'         => ['Oferta', '💥'],
    'combo'          => ['Combo', '🍔'],
    '2x1'            => ['2x1', '2️⃣'],
    '3x2'            => ['3x2', '3️⃣'],
    'descuento_pct'  => ['Descuento %', '🏷️'],
    'segunda_unidad' => ['Segunda unidad', '♻️'],
    'oferta_dia'     => ['Oferta del día', '⏰'],
  ];
}

function badgeTextoPromo(array $promo): string {
  switch ($promo['tipo']) {
    case 'oferta':          return 'OFERTA';
    case 'combo':            return 'COMBO';
    case '2x1':              return '2X1';
    case '3x2':              return '3X2';
    case 'descuento_pct':    return ((int) $promo['descuento_pct']) . '% OFF';
    case 'segunda_unidad':   return '2DA UNIDAD';
    case 'oferta_dia':       return 'OFERTA DEL DÍA';
    default:                 return 'PROMO';
  }
}

function subtituloTextoPromo(array $promo): string {
  if (!empty($promo['descripcion'])) {
    return $promo['descripcion'];
  }
  switch ($promo['tipo']) {
    case '2x1':            return 'Llevá 2 y pagá 1';
    case '3x2':             return 'Llevá 3 y pagá 2';
    case 'segunda_unidad':  return '2da unidad con ' . (int) $promo['descuento_pct'] . '% OFF';
    case 'oferta_dia':      return 'Válido solo hoy';
    default:                return '';
  }
}

function precioTextoPromo(array $promo): string {
  if (in_array($promo['tipo'], ['2x1', '3x2', 'descuento_pct', 'segunda_unidad'], true)) {
    return $promo['descuento_pct'] !== null ? ((int) $promo['descuento_pct']) . '% OFF' : '';
  }
  return $promo['precio_promo'] !== null ? '$' . number_format((float) $promo['precio_promo'], 0, ',', '.') : '';
}

/**
 * Íconos SVG propios de las tarjetas de promoción. Se definen acá (y no se usa icono())
 * porque este archivo lo cargan también el panel admin y los endpoints, donde el set de
 * íconos es otro o no está cargado.
 */
function promoIconoSvg(string $nombre, int $tam = 24): string {
  $trazos = [
    'bolsa'   => '<path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4Z"/><path d="M3 6h18"/><path d="M16 10a4 4 0 0 1-8 0"/>',
    'candado' => '<rect width="18" height="11" x="3" y="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>',
  ];
  return '<svg xmlns="http://www.w3.org/2000/svg" width="' . $tam . '" height="' . $tam . '" viewBox="0 0 24 24" '
       . 'fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" '
       . 'class="icono-svg" aria-hidden="true">' . ($trazos[$nombre] ?? '') . '</svg>';
}

/**
 * Arma el HTML del banner. $productos es el array devuelto por
 * obtenerProductosDePromocion()/obtenerPromocionesFiltradas() (join con
 * promocion_productos + productos). $rutaImagenes se resuelve distinto
 * desde el admin ("../../assets/img/productos/") que desde la home
 * ("assets/img/productos/"), por eso se pasa como parámetro.
 */
function renderBannerPromo(array $promo, array $productos, string $rutaImagenes, bool $conLock = false): string {
  $clase = claseColorPromo($promo['color_banner']);
  $estiloExtra = '';
  if ($promo['color_banner'] === 'personalizado' && !empty($promo['color_hex'])) {
    $estiloExtra = 'style="background:' . htmlspecialchars($promo['color_hex'], ENT_QUOTES) . ';"';
  }

  // Un producto (foto, emoji o ícono) según lo que tenga cargado
  $miniatura = function (array $prod) use ($rutaImagenes): string {
    if (!empty($prod['imagen'])) {
      return '<img src="' . $rutaImagenes . htmlspecialchars($prod['imagen'], ENT_QUOTES) . '" alt="" loading="lazy" decoding="async">';
    }
    return !empty($prod['emoji']) ? '<span class="promo-emoji">' . htmlspecialchars($prod['emoji']) . '</span>' : promoIconoSvg('bolsa', 72);
  };

  $imagenHtml = promoIconoSvg('bolsa', 72);
  $claseImagen = 'promo-imagen';
  if (!empty($productos)) {
    if ($promo['tipo'] === 'combo' && count($productos) >= 2) {
      // Combo: se muestran los DOS productos, uno al lado del otro
      $imagenHtml = $miniatura($productos[0]) . $miniatura($productos[1]);
      $claseImagen .= ' promo-imagen-combo';
    } else {
      $imagenHtml = $miniatura($productos[0]);
    }
  }

  $sub = subtituloTextoPromo($promo);
  $precio = precioTextoPromo($promo);

  // Combo: el título automático guardado solo tenía el 1.º producto. Se muestran los dos.
  $tituloMostrar = (string) $promo['titulo'];
  if ($promo['tipo'] === 'combo' && count($productos) >= 2) {
    $n1 = trim((string) $productos[0]['nombre']);
    $n2 = trim((string) $productos[1]['nombre']);
    $tit = trim($tituloMostrar);
    if (mb_strtoupper($tit) === mb_strtoupper($n1)) {
      $tituloMostrar = mb_strtoupper($n1) . ' + ' . mb_strtoupper($n2);
    } elseif ($sub === '' ) {
      // título propio del admin y sin descripción: se aclaran los dos productos abajo
      $sub = 'Incluye: ' . $n1 . ' + ' . $n2;
    }
  }

  ob_start(); ?>
  <div class="promo-card <?= $clase ?>" <?= $estiloExtra ?>>
    <div>
      <span class="promo-badge promo-badge-blanco"><?= htmlspecialchars(badgeTextoPromo($promo)) ?></span>
      <div class="promo-titulo"><?= nl2br(htmlspecialchars($tituloMostrar)) ?></div>
      <?php if ($sub !== ''): ?><div class="promo-subtitulo"><?= htmlspecialchars($sub) ?></div><?php endif; ?>
      <?php if ($precio !== ''): ?><div class="promo-precio"><?= htmlspecialchars($precio) ?></div><?php endif; ?>
    </div>
    <div class="<?= $claseImagen ?>"><?= $imagenHtml ?></div>
    <?php if ($conLock): ?>
      <a href="login.php" class="promo-lock">
        <span class="promo-lock-icono"><?= promoIconoSvg('candado', 24) ?></span>
        <span class="promo-lock-texto">Iniciá sesión<br>para ver esta promoción</span>
        <span class="promo-lock-cta">Iniciar sesión</span>
      </a>
    <?php endif; ?>
  </div>
  <?php
  return ob_get_clean();
}

/* ═══════════════════════════════════════════════════════════
   ESTADOS (Programada → Activa → Finalizada / Pausada manual)
   ═══════════════════════════════════════════════════════════ */

/** Recalcula el estado de las promos no pausadas según la fecha de hoy.
 *  Se llama al entrar a promociones.php, index.php, etc.: así nunca hace falta un cron.
 *  Primero hace un SELECT (barato, no bloquea filas) y solo ejecuta el UPDATE
 *  si de verdad hay alguna promo con el estado desactualizado; antes se
 *  escribía en la tabla en CADA visita pública. */
function actualizarEstadosPromociones(PDO $pdo): void {
  $calculado = "CASE
      WHEN CURDATE() < fecha_inicio THEN 'programada'
      WHEN CURDATE() > fecha_fin THEN 'finalizada'
      ELSE 'activa'
    END";
  $hayDesactualizadas = $pdo->query("
    SELECT 1 FROM promociones
    WHERE estado <> 'pausada' AND estado <> $calculado
    LIMIT 1
  ")->fetchColumn();
  if ($hayDesactualizadas === false) {
    return;
  }
  $pdo->exec("
    UPDATE promociones
    SET estado = $calculado
    WHERE estado <> 'pausada' AND estado <> $calculado
  ");
}

function calcularEstadoPorFechas(string $inicio, string $fin): string {
  $hoy = date('Y-m-d');
  if ($hoy < $inicio) return 'programada';
  if ($hoy > $fin)    return 'finalizada';
  return 'activa';
}

/* ═══════════════════════════════════════════════════════════
   CONSULTAS PARA EL LISTADO DEL ADMIN
   ═══════════════════════════════════════════════════════════ */

function construirFiltroPromociones(string $busqueda, string $estado, string $tipo, int $posicion): array {
  $where = 'WHERE 1=1';
  $params = [];

  if ($busqueda !== '') {
    $where .= ' AND pr.titulo LIKE :busqueda';
    $params[':busqueda'] = '%' . $busqueda . '%';
  }
  if (in_array($estado, ['activa', 'programada', 'finalizada', 'pausada'], true)) {
    $where .= ' AND pr.estado = :estado';
    $params[':estado'] = $estado;
  }
  if (array_key_exists($tipo, etiquetasTipoPromo())) {
    $where .= ' AND pr.tipo = :tipo';
    $params[':tipo'] = $tipo;
  }
  if ($posicion > 0) {
    $where .= ' AND pr.posicion = :posicion';
    $params[':posicion'] = $posicion;
  }

  return [$where, $params];
}

function contarPromocionesFiltradas(PDO $pdo, string $where, array $params): int {
  $stmt = $pdo->prepare("SELECT COUNT(*) FROM promociones pr $where");
  $stmt->execute($params);
  return (int) $stmt->fetchColumn();
}

/** Trae las promos filtradas + sus productos vinculados (para la miniatura del banner) */
function obtenerPromocionesFiltradas(PDO $pdo, string $where, array $params, ?int $limite = null, int $offset = 0): array {
  $sql = "SELECT pr.* FROM promociones pr $where ORDER BY pr.prioridad ASC, pr.creado_en DESC";
  if ($limite !== null) {
    $sql .= ' LIMIT :limite OFFSET :offset';
  }
  $stmt = $pdo->prepare($sql);
  foreach ($params as $clave => $valor) {
    $stmt->bindValue($clave, $valor);
  }
  if ($limite !== null) {
    $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
  }
  $stmt->execute();
  $promos = $stmt->fetchAll();
  if (empty($promos)) {
    return [];
  }

  $ids = array_column($promos, 'id');
  $placeholders = implode(',', array_fill(0, count($ids), '?'));
  $stmtProd = $pdo->prepare("
    SELECT pp.promocion_id, p.id, p.nombre, p.precio, p.emoji, p.imagen, p.stock, pp.rol
    FROM promocion_productos pp
    JOIN productos p ON p.id = pp.producto_id
    WHERE pp.promocion_id IN ($placeholders)
    ORDER BY pp.rol ASC, pp.id ASC
  ");
  $stmtProd->execute($ids);
  $productosPorPromo = [];
  foreach ($stmtProd->fetchAll() as $row) {
    $productosPorPromo[$row['promocion_id']][] = $row;
  }
  foreach ($promos as &$p) {
    $p['productos'] = $productosPorPromo[$p['id']] ?? [];
  }
  unset($p);
  return $promos;
}

/**
 * $precioConDescuentoProducto = true devuelve en "precio" el precio YA con el
 * descuento propio del producto (igual que precioConDescuento()). Así las promos
 * (2x1, 3x2, %, oferta, combo) se calculan sobre el mismo precio que ve el
 * cliente en el catálogo y una promo nunca resulta más cara que el producto suelto.
 */
function obtenerPromocionPorId(PDO $pdo, int $id, bool $soloProductosActivos = false, bool $precioConDescuentoProducto = false): ?array {
  $stmt = $pdo->prepare("SELECT * FROM promociones WHERE id = :id");
  $stmt->execute([':id' => $id]);
  $promo = $stmt->fetch();
  if (!$promo) {
    return null;
  }
  $filtroActivo = $soloProductosActivos ? 'AND p.activo = 1' : '';
  $colPrecio = $precioConDescuentoProducto
    ? 'ROUND(p.precio * (100 - LEAST(100, p.descuento_pct)) / 100, 2)'
    : 'p.precio';
  $stmtProd = $pdo->prepare("
    SELECT pp.producto_id, p.id, pp.rol, p.nombre, $colPrecio AS precio, p.emoji, p.imagen, p.stock
    FROM promocion_productos pp
    JOIN productos p ON p.id = pp.producto_id
    WHERE pp.promocion_id = :id $filtroActivo
    ORDER BY pp.rol ASC, pp.id ASC
  ");
  $stmtProd->execute([':id' => $id]);
  $promo['productos'] = $stmtProd->fetchAll();
  return $promo;
}

/** Autocompletado de productos del catálogo real, para el buscador del wizard */
function buscarProductosParaPromo(PDO $pdo, string $q, int $limite = 8): array {
  $stmt = $pdo->prepare("
    SELECT id, nombre, precio, emoji, imagen
    FROM productos
    WHERE activo = 1 AND nombre LIKE :q
    ORDER BY nombre ASC
    LIMIT :lim
  ");
  $stmt->bindValue(':q', '%' . $q . '%');
  $stmt->bindValue(':lim', $limite, PDO::PARAM_INT);
  $stmt->execute();
  return $stmt->fetchAll();
}

function guardarProductosDePromocion(PDO $pdo, int $promoId, array $productoIds): void {
  // Sin ceros ni repetidos (un combo no puede tener 2 veces el mismo producto)
  $ids = [];
  foreach ($productoIds as $prodId) {
    $prodId = (int) $prodId;
    if ($prodId > 0 && !in_array($prodId, $ids, true)) {
      $ids[] = $prodId;
    }
  }

  // DELETE + INSERT atómicos: si falla el INSERT, la promo conserva sus productos anteriores
  $propia = !$pdo->inTransaction();
  if ($propia) {
    $pdo->beginTransaction();
  }
  try {
    $pdo->prepare("DELETE FROM promocion_productos WHERE promocion_id = :id")->execute([':id' => $promoId]);
    $stmt = $pdo->prepare("INSERT INTO promocion_productos (promocion_id, producto_id, rol) VALUES (:promo, :prod, :rol)");
    foreach ($ids as $i => $prodId) {
      $stmt->execute([':promo' => $promoId, ':prod' => $prodId, ':rol' => $i === 0 ? 'principal' : 'secundario']);
    }
    if ($propia) {
      $pdo->commit();
    }
  } catch (Throwable $e) {
    if ($propia && $pdo->inTransaction()) {
      $pdo->rollBack();
    }
    throw $e;
  }
}

/* ═══════════════════════════════════════════════════════════
   PORTADA (Banner 1 / 2 / 3)
   ═══════════════════════════════════════════════════════════ */

/** Trae, para cada posición (1,2,3), la promo ACTIVA de mayor prioridad.
 *  Si no hay ninguna promo activa para una posición, esa posición no aparece. */
function obtenerBannersActivosPorPosicion(PDO $pdo): array {
  $stmt = $pdo->query("
    SELECT pr.* FROM promociones pr
    WHERE pr.estado = 'activa'
    ORDER BY pr.posicion ASC, pr.prioridad ASC, pr.creado_en DESC
  ");
  $todas = $stmt->fetchAll();

  $porPosicion = [];
  foreach ($todas as $promo) {
    $pos = (int) $promo['posicion'];
    if (!isset($porPosicion[$pos])) {
      $porPosicion[$pos] = $promo; // la primera que aparece por posición es la de mayor prioridad (ya viene ordenado)
    }
  }
  ksort($porPosicion);

  if (empty($porPosicion)) {
    return [];
  }
  $ids = array_column($porPosicion, 'id');
  $placeholders = implode(',', array_fill(0, count($ids), '?'));
  $stmtProd = $pdo->prepare("
    SELECT pp.promocion_id, p.id, p.nombre,
           ROUND(p.precio * (100 - LEAST(100, p.descuento_pct)) / 100, 2) AS precio,
           p.emoji, p.imagen, p.stock, pp.rol
    FROM promocion_productos pp JOIN productos p ON p.id = pp.producto_id
    WHERE pp.promocion_id IN ($placeholders) AND p.activo = 1
    ORDER BY pp.rol ASC, pp.id ASC
  ");
  $stmtProd->execute($ids);
  $productosPorPromo = [];
  foreach ($stmtProd->fetchAll() as $row) {
    $productosPorPromo[$row['promocion_id']][] = $row;
  }

  foreach ($porPosicion as $pos => &$p) {
    $p['productos'] = $productosPorPromo[$p['id']] ?? [];
    // Si el producto (o uno de los 2, en un combo) se desactivó y no
    // queda nada vendible, no mostramos el banner en esa posición.
    if (empty($p['productos']) || ($p['tipo'] === 'combo' && count($p['productos']) < 2)) {
      unset($porPosicion[$pos]);
    }
  }
  unset($p);
  return $porPosicion;
}

/** Todas las promociones activas (para la categoría "Promociones" del catálogo) */
function obtenerTodasLasPromocionesActivas(PDO $pdo): array {
  $stmt = $pdo->query("SELECT * FROM promociones WHERE estado = 'activa' ORDER BY prioridad ASC, creado_en DESC");
  $promos = $stmt->fetchAll();
  if (empty($promos)) {
    return [];
  }
  $ids = array_column($promos, 'id');
  $placeholders = implode(',', array_fill(0, count($ids), '?'));
  $stmtProd = $pdo->prepare("
    SELECT pp.promocion_id, p.id, p.nombre,
           ROUND(p.precio * (100 - LEAST(100, p.descuento_pct)) / 100, 2) AS precio,
           p.emoji, p.imagen, p.stock, pp.rol
    FROM promocion_productos pp JOIN productos p ON p.id = pp.producto_id
    WHERE pp.promocion_id IN ($placeholders) AND p.activo = 1
    ORDER BY pp.rol ASC, pp.id ASC
  ");
  $stmtProd->execute($ids);
  $productosPorPromo = [];
  foreach ($stmtProd->fetchAll() as $row) {
    $productosPorPromo[$row['promocion_id']][] = $row;
  }
  foreach ($promos as $i => &$p) {
    $p['productos'] = $productosPorPromo[$p['id']] ?? [];
    // Si el producto (o uno de los 2, en un combo) se desactivó y no
    // queda nada vendible, no mostramos la promo en la grilla.
    if (empty($p['productos']) || ($p['tipo'] === 'combo' && count($p['productos']) < 2)) {
      unset($promos[$i]);
    }
  }
  unset($p);
  return array_values($promos);
}

/** producto_id → datos de la promo activa de mayor prioridad que lo incluye
 *  (para el cartelito "🏷️ En promo" sobre las tarjetas de producto normales) */
function obtenerProductosEnPromoActivaMap(PDO $pdo): array {
  $stmt = $pdo->query("
    SELECT pp.producto_id, pr.id AS promocion_id, pr.titulo, pr.tipo
    FROM promocion_productos pp
    JOIN promociones pr ON pr.id = pp.promocion_id
    JOIN productos p ON p.id = pp.producto_id AND p.activo = 1
    WHERE pr.estado = 'activa'
    ORDER BY pr.prioridad ASC
  ");
  $map = [];
  foreach ($stmt->fetchAll() as $row) {
    if (!isset($map[$row['producto_id']])) {
      $map[$row['producto_id']] = $row;
    }
  }
  return $map;
}

function obtenerPromocionActivaPorId(PDO $pdo, int $id): ?array {
  $promo = obtenerPromocionPorId($pdo, $id, soloProductosActivos: true, precioConDescuentoProducto: true);
  if (!$promo || $promo['estado'] === 'pausada') {
    return null;
  }
  // Se decide por las FECHAS de hoy, no por la columna "estado", que solo
  // se refresca al visitar ciertas páginas y puede estar vencida al cobrar.
  return calcularEstadoPorFechas((string) $promo['fecha_inicio'], (string) $promo['fecha_fin']) === 'activa' ? $promo : null;
}

/* ═══════════════════════════════════════════════════════════
   CARRITO — cálculo de líneas de promoción
   ═══════════════════════════════════════════════════════════ */

/** De a cuánto avanza el selector de cantidad según el tipo (2x1 de a pares, 3x2 de a tríos) */
function pasoCantidadPromo(string $tipo): int {
  if ($tipo === '2x1') return 2;
  if ($tipo === '3x2') return 3;
  return 1;
}

/** Cuántas unidades (o combos) como máximo se pueden agregar según el stock real */
function cantidadMaximaPromo(array $promo, array $productos): int {
  if (empty($productos)) {
    return 0;
  }
  if ($promo['tipo'] === 'combo' && count($productos) < 2) {
    // Al combo le falta uno de sus 2 productos (p.ej. se desactivó):
    // ya no se puede armar, aunque el que queda tenga stock de sobra.
    return 0;
  }
  $stockMinimo = min(array_map(fn($p) => (int) $p['stock'], $productos));
  if ($promo['tipo'] === 'combo') {
    return max(0, $stockMinimo); // 1 unidad de cada producto por combo
  }
  $paso = pasoCantidadPromo($promo['tipo']);
  return max(0, intdiv($stockMinimo, $paso) * $paso);
}

/**
 * Calcula precio final y ahorro de una línea de promoción según su tipo
 * y la cantidad elegida. $cantidad significa "unidades" para Oferta/2x1/3x2/
 * Descuento%, y "combos completos" para Combo.
 */
function calcularLineaPromo(array $promo, array $productos, int $cantidad): array {
  $tipo = $promo['tipo'];
  $precioLinea = 0.0;
  $ahorroLinea = 0.0;
  $notaTipo = '';

  if ($tipo === 'combo') {
    $precioListaCombo = array_sum(array_column($productos, 'precio'));
    $precioLinea = (float) $promo['precio_promo'] * $cantidad;
    $ahorroLinea = ($precioListaCombo - (float) $promo['precio_promo']) * $cantidad;
    $notaTipo = 'Combo: ' . implode(' + ', array_column($productos, 'nombre'));
  } elseif ($tipo === '2x1') {
    $pu = (float) $productos[0]['precio'];
    $pares = intdiv($cantidad, 2);
    $precioLinea = $pu * $pares;
    $ahorroLinea = $pu * $pares;
    $notaTipo = $pares . ' pagás, ' . $pares . ' gratis';
  } elseif ($tipo === '3x2') {
    $pu = (float) $productos[0]['precio'];
    $trios = intdiv($cantidad, 3);
    $precioLinea = $pu * $trios * 2;
    $ahorroLinea = $pu * $trios;
    $notaTipo = ($trios * 2) . ' pagás, ' . $trios . ' gratis';
  } elseif ($tipo === 'descuento_pct') {
    $pu = (float) $productos[0]['precio'];
    $puFinal = round($pu * (1 - ((int) $promo['descuento_pct']) / 100), 2);
    $precioLinea = $puFinal * $cantidad;
    $ahorroLinea = ($pu - $puFinal) * $cantidad;
    $notaTipo = '-' . (int) $promo['descuento_pct'] . '% aplicado';
  } elseif ($tipo === 'segunda_unidad') {
    // OJO: esta promo usa descuento_pct (como 2x1/3x2/descuento_pct), NO
    // precio_promo — precio_promo queda NULL para este tipo (ver el wizard
    // del admin). Antes esta rama caía en el "else" de abajo y calculaba
    // con (float) null = 0, así que la promo terminaba costando $0.
    // Además el descuento es solo sobre la 2da unidad de cada par, no
    // sobre todas las unidades por igual.
    $pu = (float) $productos[0]['precio'];
    $pctOff = (int) $promo['descuento_pct'];
    $precioSegunda = round($pu * (1 - $pctOff / 100), 2);
    $pares = intdiv($cantidad, 2);
    $sinPar = $cantidad % 2; // unidad suelta (impar) va a precio completo
    $precioLinea = ($pares * ($pu + $precioSegunda)) + ($sinPar * $pu);
    $ahorroLinea = $pares * ($pu - $precioSegunda);
    $notaTipo = 'Cada 2da unidad con -' . $pctOff . '% OFF';
  } else { // oferta / oferta_dia
    $pu = (float) $productos[0]['precio'];
    $precioLinea = (float) $promo['precio_promo'] * $cantidad;
    $ahorroLinea = ($pu - (float) $promo['precio_promo']) * $cantidad;
    $notaTipo = 'Precio promocional aplicado';
  }

  $etiquetaUnidad = $tipo === 'combo'
    ? ($cantidad === 1 ? '1 combo' : $cantidad . ' combos')
    : ($cantidad === 1 ? '1 unidad' : $cantidad . ' unidades');

  return [
    'precio_linea'    => round($precioLinea, 2),
    'ahorro_linea'    => round(max(0, $ahorroLinea), 2),
    'etiqueta_unidad' => $etiquetaUnidad,
    'nota_tipo'       => $notaTipo,
  ];
}

/**
 * Se lanza cuando, al descontar stock dentro de la transacción del checkout,
 * ya no alcanzan las unidades (otro cliente compró primero, o el mismo
 * producto estaba en el carrito como línea normal Y dentro de una promo).
 */
class StockInsuficienteException extends RuntimeException {
  public string $producto;
  /** 'stock' = no alcanzan las unidades; 'no_disponible' = el producto se desactivó o se eliminó */
  public string $motivo;
  public function __construct(string $producto, string $motivo = 'stock') {
    parent::__construct(($motivo === 'stock' ? 'Stock insuficiente: ' : 'Producto no disponible: ') . $producto);
    $this->producto = $producto;
    $this->motivo = $motivo;
  }
}

/**
 * Cuánto se necesita de CADA producto real para cumplir todas las líneas del
 * carrito (normales + promociones), sumando las que repiten producto.
 * Devuelve [producto_id => ['nombre'=>, 'cantidad'=>, 'stock'=>]] ordenado
 * por id (orden fijo = sin deadlocks entre compras simultáneas).
 * Para promos descompone igual que insertarLineaPromoEnPedido: el combo
 * descuenta 1 unidad de cada producto por combo; el resto, solo el principal.
 */
function demandaPorProducto(array $items): array {
  $demanda = [];
  $sumar = function (array $p, int $cant) use (&$demanda) {
    $id = (int) $p['id'];
    if (!isset($demanda[$id])) {
      $demanda[$id] = ['nombre' => $p['nombre'], 'cantidad' => 0, 'stock' => (int) $p['stock']];
    }
    $demanda[$id]['cantidad'] += $cant;
  };
  foreach ($items as $item) {
    $cant = (int) $item['cantidad'];
    if (empty($item['es_promo'])) {
      $sumar(['id' => $item['id'], 'nombre' => $item['nombre'], 'stock' => $item['stock']], $cant);
    } elseif ($item['tipo'] === 'combo' && count($item['productos']) >= 2) {
      foreach ($item['productos'] as $p) {
        $sumar($p, $cant);
      }
    } else {
      $sumar($item['productos'][0], $cant);
    }
  }
  ksort($demanda);
  return $demanda;
}

/**
 * Descuenta stock de forma ATÓMICA: la condición "stock >= cantidad" va en
 * el propio UPDATE, así que dos compras simultáneas nunca pueden dejar el
 * stock en negativo. Si no alcanzó (0 filas afectadas) lanza
 * StockInsuficienteException para que el checkout haga rollback.
 */
function descontarStockProducto(PDO $pdo, int $productoId, int $cantidad, string $nombre = ''): void {
  $stmt = $pdo->prepare('
    UPDATE productos
    SET stock = stock - :cant1, ventas = ventas + :cant2
    WHERE id = :id AND activo = 1 AND stock >= :cant3
  ');
  $stmt->execute([':cant1' => $cantidad, ':cant2' => $cantidad, ':cant3' => $cantidad, ':id' => $productoId]);
  if ($stmt->rowCount() === 0) {
    $etiqueta = $nombre !== '' ? $nombre : ('#' . $productoId);
    // ¿Falló por falta de unidades o porque el producto ya no está disponible?
    $chk = $pdo->prepare('SELECT activo FROM productos WHERE id = :id');
    $chk->execute([':id' => $productoId]);
    $activo = $chk->fetchColumn();
    throw new StockInsuficienteException($etiqueta, ($activo === false || (int) $activo !== 1) ? 'no_disponible' : 'stock');
  }
}

/**
 * Al confirmar el pedido, una línea de promoción se descompone en filas de
 * pedido_items (una por producto real). El descuento ya queda reflejado en
 * el precio_unitario/subtotal de cada fila y todas llevan promocion_id.
 * El stock NO se toca acá: ya se descontó antes, en bloque, por producto.
 */
function insertarLineaPromoEnPedido(PDO $pdo, PDOStatement $stmtItem, int $pedidoId, array $item): void {
  $productos = $item['productos'];
  $cantidad = (int) $item['cantidad'];
  $subtotalLinea = (float) $item['subtotal'];
  $promoId = (int) $item['id'];
  $etiquetaTipo = etiquetasTipoPromo()[$item['tipo']][0] ?? $item['tipo'];

  if ($item['tipo'] === 'combo' && count($productos) >= 2) {
    $precioListaTotal = array_sum(array_column($productos, 'precio')) ?: 1;
    $acumulado = 0.0;
    $ultimo = count($productos) - 1;
    foreach (array_values($productos) as $i => $p) {
      if ($i === $ultimo) {
        // La última fila absorbe el resto del redondeo: la suma de filas = subtotal exacto de la línea
        $subtotalProd = round($subtotalLinea - $acumulado, 2);
      } else {
        $subtotalProd = round($subtotalLinea * ($p['precio'] / $precioListaTotal), 2);
        $acumulado += $subtotalProd;
      }
      $precioUnitProd = $cantidad > 0 ? round($subtotalProd / $cantidad, 2) : 0;
      $stmtItem->execute([
        ':pedido_id' => $pedidoId, ':producto_id' => $p['id'], ':promocion_id' => $promoId,
        ':nombre' => mb_substr($p['nombre'] . ' (Combo: ' . $item['nombre'] . ')', 0, 150),
        ':emoji' => $p['emoji'], ':precio' => $precioUnitProd, ':cantidad' => $cantidad, ':subtotal' => $subtotalProd,
      ]);
    }
  } else {
    $p = $productos[0];
    $precioUnit = $cantidad > 0 ? round($subtotalLinea / $cantidad, 2) : 0;
    $stmtItem->execute([
      ':pedido_id' => $pedidoId, ':producto_id' => $p['id'], ':promocion_id' => $promoId,
      ':nombre' => mb_substr($p['nombre'] . ' (' . $etiquetaTipo . ')', 0, 150),
      ':emoji' => $p['emoji'], ':precio' => $precioUnit, ':cantidad' => $cantidad, ':subtotal' => $subtotalLinea,
    ]);
  }

  registrarVentaPromo($pdo, $promoId, $cantidad, $subtotalLinea);
}

/* ═══════════════════════════════════════════════════════════
   VENTAS GENERADAS POR PROMOCIÓN
   ═══════════════════════════════════════════════════════════ */

/**
 * Se llama UNA vez por cada línea de promoción comprada (no por producto):
 * suma el monto de la línea y sus unidades (o combos) a ESA promoción.
 * Las compras del mismo producto como artículo normal ya no se acreditan
 * a ninguna promo. Si el pedido se cancela, restaurarStockDePedido()
 * revierte estas cifras usando pedido_items.promocion_id.
 */
function registrarVentaPromo(PDO $pdo, int $promocionId, int $cantidad, float $subtotal): void {
  $stmt = $pdo->prepare("
    UPDATE promociones
    SET ventas_monto = ventas_monto + :monto,
        ventas_unidades = ventas_unidades + :cant
    WHERE id = :id
  ");
  $stmt->execute([':monto' => $subtotal, ':cant' => $cantidad, ':id' => $promocionId]);
}

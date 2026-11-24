<?php
/**
 * FUNCIONES DEL ADMIN
 * Acá viven las funciones que se repiten entre varias páginas del panel
 * (por ejemplo: productos.php y productos_exportar.php usan el mismo
 * filtro; pedidos.php y pedidos_exportar.php también). Cada página
 * incluye este archivo y llama solo a las funciones que necesita.
 */

require_once __DIR__ . '/conexion.php';

/* ═══════════════════════════════════════════════════════════
   UTILIDADES GENERALES
   ═══════════════════════════════════════════════════════════ */

/** Umbral de "stock bajo": único lugar donde se define (lo usan listado, filtro y dashboard) */
const UMBRAL_STOCK_BAJO = 10;

/**
 * Lista de páginas a mostrar en la paginación, con huecos (null = "…") para que
 * no se impriman cientos de números. Siempre incluye primera, última y ±2 de la actual.
 */
function paginasVisibles(int $actual, int $total): array {
  $paginas = [];
  for ($i = 1; $i <= $total; $i++) {
    if ($i === 1 || $i === $total || abs($i - $actual) <= 2) {
      $paginas[] = $i;
    } elseif (end($paginas) !== null) {
      $paginas[] = null;
    }
  }
  return $paginas;
}

/** Formatea un número como precio en pesos: 1234.5 → "$1.235" */
function formatoMoneda(float $valor): string {
  return '$' . number_format($valor, 0, ',', '.');
}

/**
 * Arma una URL de listado conservando los filtros actuales y
 * permitiendo sobrescribir algunos (por ejemplo, al cambiar de página).
 * La usan productos.php (urlConFiltros('productos.php', ...)) y
 * pedidos.php (urlConFiltros('pedidos.php', ...)).
 */
function urlConFiltros(string $archivo, array $filtrosActuales, array $sobrescribir = []): string {
  $actuales = array_filter($filtrosActuales, fn($v) => $v !== '' && $v !== null);
  $finales  = array_merge($actuales, $sobrescribir);
  return $archivo . '?' . http_build_query($finales);
}

/* ═══════════════════════════════════════════════════════════
   PRODUCTOS
   ═══════════════════════════════════════════════════════════ */

/** Trae las categorías para los <select> de filtro y del modal */
function obtenerCategoriasAdmin(PDO $pdo): array {
  return $pdo->query("SELECT id, nombre, icono FROM categorias ORDER BY orden ASC")->fetchAll();
}

/**
 * Arma el WHERE + parámetros para filtrar productos según
 * búsqueda / categoría / estado. La usan productos.php y
 * productos_exportar.php para que el CSV exporte EXACTAMENTE
 * lo mismo que se está viendo filtrado en pantalla.
 */
function construirFiltroProductos(string $busqueda, int $categoriaId, string $estado): array {
  $where  = 'WHERE 1=1';
  $params = [];

  if ($busqueda !== '') {
    $where .= ' AND p.nombre LIKE :busqueda';
    $params[':busqueda'] = '%' . $busqueda . '%';
  }
  if ($categoriaId > 0) {
    $where .= ' AND p.categoria_id = :categoria';
    $params[':categoria'] = $categoriaId;
  }
  if ($estado === 'activo') {
    $where .= ' AND p.activo = 1';
  } elseif ($estado === 'inactivo') {
    $where .= ' AND p.activo = 0';
  } elseif ($estado === 'stock_bajo') {
    // Mismo umbral que las tarjetas de estadísticas (productos.php / inicio.php): activos con stock < 10
    $where .= ' AND p.activo = 1 AND p.stock < ' . UMBRAL_STOCK_BAJO;
  }

  return [$where, $params];
}

/** Cuenta cuántos productos cumplen el filtro (para la paginación) */
function contarProductosFiltrados(PDO $pdo, string $where, array $params): int {
  $stmt = $pdo->prepare("SELECT COUNT(*) FROM productos p $where");
  $stmt->execute($params);
  return (int) $stmt->fetchColumn();
}

/**
 * Trae los productos que cumplen el filtro. Si $limite es null,
 * trae todos (se usa así en la exportación a CSV, que no pagina).
 */
function obtenerProductosFiltrados(PDO $pdo, string $where, array $params, ?int $limite = null, int $offset = 0): array {
  $sql = "
    SELECT p.id, p.nombre, p.precio, p.descuento_pct, p.emoji, p.imagen, p.stock, p.ventas,
           p.destacado, p.activo, c.id AS categoria_id, c.nombre AS categoria_nombre
    FROM productos p
    JOIN categorias c ON c.id = p.categoria_id
    $where
    ORDER BY p.nombre ASC
  ";
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
  return $stmt->fetchAll();
}

/**
 * Sube la foto de un producto ($_FILES['imagen']) a UPLOADS_PATH
 * (definido en includes/config.php) y devuelve el nombre de archivo
 * generado para guardar en la columna "imagen" de la base de datos.
 *
 * Devuelve:
 *   - string  → nombre de archivo guardado (ej: "prod_64f...a1.jpg")
 *   - null    → no se subió ningún archivo (el usuario no eligió foto)
 *   - false   → hubo un error de validación ($error queda con el mensaje)
 */
function subirImagenProducto(array $archivo, ?string &$error = null): string|false|null {
  // Si no se seleccionó ningún archivo, no es un error: simplemente no hay nada que subir
  if (!isset($archivo['error']) || $archivo['error'] === UPLOAD_ERR_NO_FILE) {
    return null;
  }

  if ($archivo['error'] === UPLOAD_ERR_INI_SIZE || $archivo['error'] === UPLOAD_ERR_FORM_SIZE) {
    $error = 'La imagen supera el tamaño máximo que acepta el servidor (' . ini_get('upload_max_filesize') . '). Usá una más liviana (máximo 5 MB).';
    return false;
  }
  if ($archivo['error'] !== UPLOAD_ERR_OK) {
    $error = 'Hubo un problema al subir la imagen. Probá de nuevo.';
    return false;
  }

  $tamañoMaximo = 5 * 1024 * 1024; // 5 MB
  if ($archivo['size'] > $tamañoMaximo) {
    $error = 'La imagen es muy pesada (máximo 5 MB).';
    return false;
  }

  $extensionesPermitidas = [
    'image/jpeg' => 'jpg',
    'image/png'  => 'png',
    'image/webp' => 'webp',
    'image/gif'  => 'gif',
  ];

  // Detectamos el tipo real del archivo (no confiamos solo en el nombre/extensión
  // que manda el navegador, porque se puede falsificar fácilmente)
  $finfo    = finfo_open(FILEINFO_MIME_TYPE);
  $mimeReal = finfo_file($finfo, $archivo['tmp_name']);
  finfo_close($finfo);

  if (!isset($extensionesPermitidas[$mimeReal])) {
    $error = 'Formato no permitido. Subí una imagen JPG, PNG, WEBP o GIF.';
    return false;
  }

  // Segunda verificación: que el contenido sea realmente una imagen decodificable
  // (descarta archivos con cabecera de imagen y código pegado detrás).
  if (@getimagesize($archivo['tmp_name']) === false) {
    $error = 'El archivo no es una imagen válida.';
    return false;
  }

  if (!is_dir(UPLOADS_PATH)) {
    mkdir(UPLOADS_PATH, 0755, true);
  }

  $nombreArchivo = 'prod_' . bin2hex(random_bytes(8)) . '.' . $extensionesPermitidas[$mimeReal];
  $destino       = UPLOADS_PATH . '/' . $nombreArchivo;

  // Se intenta guardar una versión liviana (máx. 640 px de lado, recomprimida): el catálogo
  // muestra 12 fotos por página y una foto de celular pesa varios MB. Si no se puede optimizar
  // (sin extensión GD, GIF, imagen enorme, error al procesar) se guarda la original, como antes.
  if (!optimizarImagenProducto($archivo['tmp_name'], $destino, $mimeReal)) {
    if (!move_uploaded_file($archivo['tmp_name'], $destino)) {
      $error = 'No se pudo guardar la imagen en el servidor.';
      return false;
    }
  }

  return $nombreArchivo;
}

/**
 * Convierte "512M", "1G", "128K" o "-1" (de php.ini) a bytes. -1 = sin límite.
 */
function bytesDeIni(string $valor): int {
  $valor = trim($valor);
  if ($valor === '') {
    return 0;
  }
  $numero = (int) $valor;
  switch (strtolower(substr($valor, -1))) {
    case 'g': $numero *= 1024; // sin break: sigue multiplicando
    case 'm': $numero *= 1024;
    case 'k': $numero *= 1024;
  }
  return $numero;
}

/**
 * Optimiza la foto de un producto: la achica a un máximo de 640 px de lado, respeta la
 * orientación de las fotos de celular (EXIF) y la vuelve a comprimir.
 * Escribe el resultado en $destino y devuelve true. Devuelve false SIN dejar archivos a medias
 * cuando no conviene o no se puede optimizar; en ese caso quien llama guarda la original.
 *   - sin la extensión GD, o GIF (puede ser animado) → false
 *   - imagen tan grande que decodificarla agotaría la memoria de PHP → false
 *   - imagen ya liviana (≤ 640 px y ≤ 200 KB) → false (no se recomprime para no perder calidad)
 *   - la versión optimizada pesa más que la original y no hizo falta girarla → false
 */
function optimizarImagenProducto(string $origen, string $destino, string $mime): bool {
  $maxLado = 640;
  $calidad = 80;

  try {
    if (!extension_loaded('gd') || $mime === 'image/gif') {
      return false;
    }

    $info = @getimagesize($origen);
    if ($info === false || $info[0] < 1 || $info[1] < 1) {
      return false;
    }
    [$anchoOrig, $altoOrig] = $info;
    $pesoOrig = (int) @filesize($origen);

    if (max($anchoOrig, $altoOrig) <= $maxLado && $pesoOrig <= 200 * 1024) {
      return false;
    }

    // GD decodifica a ~4 bytes por píxel. Si no entra en la memoria que le queda a PHP,
    // se descarta ANTES de intentarlo (quedarse sin memoria es un error fatal, no se puede atrapar).
    $pixeles = $anchoOrig * $altoOrig;
    $limiteMem = bytesDeIni((string) ini_get('memory_limit'));
    if ($pixeles > 40000000 || ($limiteMem > 0 && $pixeles * 5 > ($limiteMem - memory_get_usage(true)) * 0.8)) {
      return false;
    }

    $img = false;
    if ($mime === 'image/jpeg' && function_exists('imagecreatefromjpeg')) {
      $img = @imagecreatefromjpeg($origen);
    } elseif ($mime === 'image/png' && function_exists('imagecreatefrompng')) {
      $img = @imagecreatefrompng($origen);
    } elseif ($mime === 'image/webp' && function_exists('imagecreatefromwebp')) {
      $img = @imagecreatefromwebp($origen);
    }
    if ($img === false) {
      return false;
    }

    // Las fotos de celular traen la rotación en los metadatos (EXIF) y al recomprimir se pierden:
    // se aplica sobre los píxeles para que la foto no quede de costado.
    $giro = false;
    if ($mime === 'image/jpeg' && function_exists('exif_read_data') && function_exists('imagerotate')) {
      $exif = @exif_read_data($origen);
      $orientacion = is_array($exif) ? (int) ($exif['Orientation'] ?? 1) : 1;
      $grados = [3 => 180, 6 => -90, 8 => 90][$orientacion] ?? 0;
      if ($grados !== 0) {
        $girada = @imagerotate($img, $grados, 0);
        if ($girada !== false) {
          $img = $girada;
          $giro = true;
        }
      }
    }

    $ancho = imagesx($img);
    $alto  = imagesy($img);
    $escala = min(1.0, $maxLado / max($ancho, $alto));

    if ($escala < 1.0) {
      $nuevoAncho = max(1, (int) round($ancho * $escala));
      $nuevoAlto  = max(1, (int) round($alto * $escala));
      $nueva = imagecreatetruecolor($nuevoAncho, $nuevoAlto);
      if ($nueva === false) {
        return false;
      }
      if ($mime !== 'image/jpeg') { // PNG/WebP: conservar la transparencia
        imagealphablending($nueva, false);
        imagesavealpha($nueva, true);
        imagefilledrectangle($nueva, 0, 0, $nuevoAncho, $nuevoAlto, imagecolorallocatealpha($nueva, 0, 0, 0, 127));
      }
      imagecopyresampled($nueva, $img, 0, 0, 0, 0, $nuevoAncho, $nuevoAlto, $ancho, $alto);
      $img = $nueva;
    } elseif ($mime !== 'image/jpeg') {
      imagesavealpha($img, true);
    }

    $guardada = false;
    if ($mime === 'image/jpeg') {
      imageinterlace($img, true); // JPEG progresivo: se ve más rápido al cargar
      $guardada = imagejpeg($img, $destino, $calidad);
    } elseif ($mime === 'image/png') {
      $guardada = imagepng($img, $destino, 9);
    } elseif ($mime === 'image/webp' && function_exists('imagewebp')) {
      $guardada = imagewebp($img, $destino, $calidad);
    }
    unset($img, $nueva, $girada); // libera la memoria de GD

    if (!$guardada || !is_file($destino)) {
      @unlink($destino);
      return false;
    }
    // Si quedó más pesada que la original (puede pasar con PNG ya optimizados) y no hubo que
    // girarla, se descarta y se usa la original.
    if (!$giro && (int) @filesize($destino) >= $pesoOrig) {
      @unlink($destino);
      return false;
    }
    return true;
  } catch (Throwable $e) {
    error_log('[el-rapido] no se pudo optimizar la imagen: ' . $e->getMessage());
    @unlink($destino);
    return false;
  }
}

/**
 * Cuántas cosas dependen de cada producto (para decidir si se puede borrar de verdad y
 * para avisar al admin antes de confirmar). Devuelve [id => ['ventas'=>n, 'promos'=>n, 'listas'=>n]]
 *  - ventas: filas de pedido_items (historial de pedidos);
 *  - promos: promociones que lo incluyen (al borrarlo desaparecerían de la promo);
 *  - listas: listas de "Compra semanal" de clientes que lo tienen.
 */
function obtenerReferenciasProductos(PDO $pdo, array $ids): array {
  $ids = array_values(array_unique(array_filter(array_map('intval', $ids), fn($i) => $i > 0)));
  $refs = [];
  foreach ($ids as $id) {
    $refs[$id] = ['ventas' => 0, 'promos' => 0, 'listas' => 0];
  }
  if (empty($ids)) {
    return $refs;
  }
  $marcadores = implode(',', array_fill(0, count($ids), '?'));
  $consultas = [
    'ventas' => "SELECT producto_id, COUNT(*) FROM pedido_items WHERE producto_id IN ($marcadores) GROUP BY producto_id",
    'promos' => "SELECT producto_id, COUNT(*) FROM promocion_productos WHERE producto_id IN ($marcadores) GROUP BY producto_id",
    'listas' => "SELECT producto_id, COUNT(*) FROM compra_semanal_items WHERE producto_id IN ($marcadores) GROUP BY producto_id",
  ];
  foreach ($consultas as $clave => $sql) {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($ids);
    foreach ($stmt->fetchAll(PDO::FETCH_NUM) as [$productoId, $cantidad]) {
      $refs[(int) $productoId][$clave] = (int) $cantidad;
    }
  }
  return $refs;
}

/**
 * Neutraliza "inyección de fórmulas" en CSV: si un texto escrito por un cliente
 * empieza con = + - @ (o tabulación / retorno), Excel y LibreOffice lo ejecutan
 * como fórmula al abrir el archivo. Anteponemos un apóstrofo para que se vea
 * como texto. Los números y fechas generados por el sistema no necesitan esto.
 */
function csvSeguro($valor) {
  if (!is_string($valor) || $valor === '') {
    return $valor;
  }
  // Un teléfono internacional ("+54 9 3775 12-3456") empieza con "+" pero no es una fórmula:
  // solo tiene dígitos, espacios, paréntesis y guiones, así que se deja tal cual.
  if (preg_match('/^\+[0-9][0-9 ()\-]*$/', $valor)) {
    return $valor;
  }
  return preg_match('/^[=+\-@\t\r]/', $valor) ? "'" . $valor : $valor;
}

/**
 * Borra del disco la foto de un producto (al reemplazarla o al borrar el producto).
 * SEGURIDAD: solo acepta nombres con el formato exacto que genera subirImagenProducto()
 * ("prod_" + 16 hex + extensión). Cualquier otra cosa ("../config.php", rutas, etc.)
 * se ignora, así que esta función nunca puede borrar un archivo fuera de la carpeta
 * de fotos aunque el nombre llegue manipulado.
 */
function esNombreImagenProductoValido(?string $nombre): bool {
  return is_string($nombre) && preg_match('/^prod_[a-f0-9]{16}\.(jpg|png|webp|gif)\z/', $nombre) === 1;
}

function eliminarImagenProducto(?string $nombreArchivo): void {
  if (!esNombreImagenProductoValido($nombreArchivo)) {
    return;
  }
  $ruta = UPLOADS_PATH . '/' . basename($nombreArchivo); // basename: nunca salir de la carpeta de imágenes
  if (is_file($ruta)) {
    unlink($ruta);
  }
}

/* ═══════════════════════════════════════════════════════════
   PEDIDOS
   ═══════════════════════════════════════════════════════════ */

/**
 * Etiquetas de texto + clase CSS para cada estado de pedido.
 * Incluye los 6 estados del ENUM de la tabla "pedidos". Es la ÚNICA
 * fuente de estados: el selector, el filtro y la validación de
 * pedidos.php (y el chatbot) salen de acá.
 */
function etiquetasEstadoPedido(): array {
  return [
    'pendiente'    => ['Pendiente', 'badge-pendiente'],
    'preparando'   => ['Preparando', 'badge-preparando'],
    'listo_retiro' => ['Listo para retirar', 'badge-listo-retiro'],
    'en_camino'    => ['En camino', 'badge-en-camino'],
    'entregado'    => ['Entregado', 'badge-entregado'],
    'cancelado'    => ['Cancelado', 'badge-cancelado'],
  ];
}

function estadosValidosPedido(): array {
  return array_keys(etiquetasEstadoPedido());
}

/** Etiquetas de texto + clase CSS para cada método de pago */
function etiquetasMetodoPago(): array {
  return [
    'efectivo'    => ['Efectivo', 'pago-efectivo'],
    'mercadopago' => ['Mercado Pago', 'pago-mercadopago'],
    'tarjeta'     => ['Tarjeta', 'pago-tarjeta'],
  ];
}

/**
 * Arma el WHERE + parámetros para filtrar pedidos según
 * búsqueda / estado / fecha. La usan pedidos.php y
 * pedidos_exportar.php.
 */
function construirFiltroPedidos(string $busqueda, string $estado, string $fecha): array {
  $where  = 'WHERE 1=1';
  $params = [];

  if ($busqueda !== '') {
    $idBuscado = ltrim($busqueda, '#');
    if (ctype_digit($idBuscado)) {
      // "# de pedido": comparación exacta por PK (usa índice, no recorre la tabla)
      $where .= ' AND (p.id = :busqueda OR u.nombre LIKE :busqueda2)';
      $params[':busqueda']  = (int) $idBuscado;
    } else {
      $where .= ' AND u.nombre LIKE :busqueda2';
    }
    $params[':busqueda2'] = '%' . $busqueda . '%';
  }
  if (in_array($estado, estadosValidosPedido(), true)) {
    $where .= ' AND p.estado = :estado';
    $params[':estado'] = $estado;
  }
  if ($fecha === 'hoy') {
    $where .= ' AND p.creado_en >= CURDATE() AND p.creado_en < CURDATE() + INTERVAL 1 DAY';
  } elseif ($fecha === 'ayer') {
    $where .= ' AND p.creado_en >= CURDATE() - INTERVAL 1 DAY AND p.creado_en < CURDATE()';
  } elseif ($fecha === 'semana') {
    $where .= ' AND p.creado_en >= CURDATE() - INTERVAL 6 DAY'; // últimos 7 días contando hoy
  } elseif ($fecha === 'mes') {
    $where .= ' AND p.creado_en >= CURDATE() - INTERVAL 29 DAY'; // últimos 30 días contando hoy
  }

  return [$where, $params];
}

/** Cuenta cuántos pedidos cumplen el filtro (para la paginación) */
function contarPedidosFiltrados(PDO $pdo, string $where, array $params): int {
  $stmt = $pdo->prepare("SELECT COUNT(*) FROM pedidos p JOIN usuarios u ON u.id = p.usuario_id $where");
  $stmt->execute($params);
  return (int) $stmt->fetchColumn();
}

/**
 * Trae los pedidos que cumplen el filtro. Si $limite es null,
 * trae todos (se usa así en la exportación a CSV).
 */
function obtenerPedidosFiltrados(PDO $pdo, string $where, array $params, ?int $limite = null, int $offset = 0): array {
  $sql = "
    SELECT p.id, p.telefono_contacto, p.entrega_nombre, p.entrega_apellido,
           p.tipo_entrega, p.direccion, p.entrega_calle, p.entrega_numero,
           p.entrega_barrio, p.entrega_referencia, p.recargo_envio,
           p.metodo_pago, p.estado, p.total, p.creado_en, u.nombre AS cliente_nombre
    FROM pedidos p
    JOIN usuarios u ON u.id = p.usuario_id
    $where
    ORDER BY p.creado_en DESC
  ";
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
  return $stmt->fetchAll();
}

/** Trae, en un solo query, los items de todos los pedidos pasados por id */
function obtenerItemsPorPedido(PDO $pdo, array $idsPedidos): array {
  if (empty($idsPedidos)) {
    return [];
  }
  $placeholders = implode(',', array_fill(0, count($idsPedidos), '?'));
  $stmt = $pdo->prepare("SELECT pedido_id, nombre_producto, emoji, precio_unitario, cantidad, subtotal FROM pedido_items WHERE pedido_id IN ($placeholders)");
  $stmt->execute($idsPedidos);

  $itemsPorPedido = [];
  foreach ($stmt->fetchAll() as $item) {
    $itemsPorPedido[$item['pedido_id']][] = $item;
  }
  return $itemsPorPedido;
}

/**
 * Le devuelve al stock las unidades de un pedido, le resta las ventas y
 * revierte lo que ese pedido había sumado a las estadísticas de sus
 * promociones. DEBE llamarse dentro de una transacción abierta por quien
 * la usa (cambiarEstadoPedido / eliminarPedidoAdmin) y solo para pedidos
 * que todavía NO estaban cancelados (si ya lo estaban, el stock ya se
 * había devuelto).
 */
function restaurarStockDePedido(PDO $pdo, int $pedidoId): void {
  $stmt = $pdo->prepare('SELECT producto_id, cantidad FROM pedido_items WHERE pedido_id = :id');
  $stmt->execute([':id' => $pedidoId]);
  $stmtRestaurar = $pdo->prepare('UPDATE productos SET stock = stock + :cant1, ventas = GREATEST(0, ventas - :cant2) WHERE id = :id');
  $items = $stmt->fetchAll();
  // Orden fijo por producto: evita deadlocks entre dos cancelaciones simultáneas
  usort($items, fn($a, $b) => (int) $a['producto_id'] <=> (int) $b['producto_id']);
  foreach ($items as $item) {
    if ($item['producto_id'] === null) {
      continue; // el producto fue eliminado del catálogo, no hay a quién devolverle stock
    }
    $stmtRestaurar->execute([
      ':cant1' => $item['cantidad'],
      ':cant2' => $item['cantidad'],
      ':id'    => $item['producto_id'],
    ]);
  }

  // Estadísticas de promociones: una línea de promo = filas con el mismo
  // promocion_id. Monto = suma de subtotales; unidades/combos = la cantidad
  // de la línea (igual en todas sus filas, por eso MAX).
  $stmtPromos = $pdo->prepare('
    SELECT promocion_id, SUM(subtotal) AS monto, MAX(cantidad) AS unidades
    FROM pedido_items
    WHERE pedido_id = :id AND promocion_id IS NOT NULL
    GROUP BY promocion_id
  ');
  $stmtPromos->execute([':id' => $pedidoId]);
  $stmtRevertir = $pdo->prepare('
    UPDATE promociones
    SET ventas_monto = GREATEST(0, ventas_monto - :monto),
        ventas_unidades = GREATEST(0, ventas_unidades - :unid)
    WHERE id = :id
  ');
  foreach ($stmtPromos->fetchAll() as $fila) {
    $stmtRevertir->execute([
      ':monto' => $fila['monto'],
      ':unid'  => (int) $fila['unidades'],
      ':id'    => (int) $fila['promocion_id'],
    ]);
  }
}

/**
 * Estados a los que SÍ se puede pasar desde el estado actual de un pedido
 * (incluye el propio estado actual). Es la única fuente de reglas: la usan
 * el selector de pedidos.php (para deshabilitar opciones) y cambiarEstadoPedido()
 * (para validar en el servidor, porque el navegador no es de fiar).
 *  - Un pedido cancelado o entregado es definitivo.
 *  - "Listo para retirar" solo existe en pedidos de retiro y "En camino" solo en delivery.
 */
function estadosPermitidosPedido(string $estadoActual, string $tipoEntrega): array {
  if ($estadoActual === 'cancelado' || $estadoActual === 'entregado') {
    return [$estadoActual];
  }
  $permitidos = estadosValidosPedido();
  $excluir = $tipoEntrega === 'delivery' ? 'listo_retiro' : 'en_camino';
  return array_values(array_filter($permitidos, fn($e) => $e !== $excluir || $e === $estadoActual));
}

/**
 * Texto del aviso que ve el cliente cuando su pedido pasa a $estado.
 * Devuelve [tipo, título, mensaje] o null si ese estado no genera aviso
 * (por ejemplo "pendiente": es el estado con el que nace el pedido).
 */
function textoNotificacionPedido(string $estado, int $pedidoId): ?array {
  $n = '#' . $pedidoId;
  return match ($estado) {
    'preparando'   => ['preparando', 'Estamos preparando tu pedido', "Tu pedido $n ya está en preparación."],
    'listo_retiro' => ['listo',      '¡Tu pedido está listo!',       "Tu pedido $n está listo. Pasá a retirarlo por el local."],
    'en_camino'    => ['en_camino',  '¡Tu pedido va en camino!',     "Tu pedido $n ya salió del local. Preparate para recibirlo."],
    'entregado'    => ['entregado',  'Pedido entregado',             "Tu pedido $n fue entregado. ¡Gracias por elegir El Rápido!"],
    'cancelado'    => ['cancelado',  'Pedido cancelado',             "Tu pedido $n fue cancelado. Si tenés dudas, escribinos por WhatsApp."],
    default        => null,
  };
}

/**
 * Guarda el aviso para el cliente. Es "de mejor esfuerzo": si falla (por ejemplo, falta correr
 * database/migracion_006_notificaciones.sql) solo se registra en el log; el cambio de estado
 * del pedido ya está confirmado y nunca se deshace por esto.
 */
function crearNotificacionPedido(PDO $pdo, int $usuarioId, int $pedidoId, string $estado): void {
  $texto = textoNotificacionPedido($estado, $pedidoId);
  if ($texto === null || $usuarioId <= 0) {
    return;
  }
  try {
    $pdo->prepare('INSERT INTO notificaciones (usuario_id, pedido_id, tipo, titulo, mensaje) VALUES (:u, :p, :t, :ti, :m)')
        ->execute([':u' => $usuarioId, ':p' => $pedidoId, ':t' => $texto[0], ':ti' => $texto[1], ':m' => $texto[2]]);
  } catch (Throwable $e) {
    error_log('[el-rapido] crearNotificacionPedido #' . $pedidoId . ': ' . $e->getMessage());
  }
}

/**
 * ÚNICO punto por donde cambia el estado de un pedido (selector, botón
 * "Cancelar", etc.). Todo ocurre en una transacción con el pedido bloqueado:
 *  - valida que el estado exista en el ENUM y que la transición tenga sentido
 *    (ver estadosPermitidosPedido());
 *  - al pasar a "cancelado" devuelve el stock UNA sola vez;
 *  - un pedido cancelado o entregado no se puede reabrir ni cancelar: la mercadería
 *    entregada no vuelve al inventario y reabrir uno cancelado dejaría el stock inflado
 *    (si el cliente quiere comprar de nuevo, se hace un pedido nuevo).
 * Devuelve el código de mensaje para mostrar en pedidos.php.
 */
function cambiarEstadoPedido(PDO $pdo, int $pedidoId, string $nuevoEstado): string {
  if ($pedidoId <= 0 || !in_array($nuevoEstado, estadosValidosPedido(), true)) {
    return 'estado_invalido';
  }

  try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare('SELECT estado, tipo_entrega, usuario_id FROM pedidos WHERE id = :id FOR UPDATE');
    $stmt->execute([':id' => $pedidoId]);
    $fila = $stmt->fetch();

    if ($fila === false) {
      $pdo->rollBack();
      return 'pedido_inexistente';
    }
    $estadoActual = $fila['estado'];

    if ($estadoActual === $nuevoEstado) {
      $pdo->rollBack();
      return 'estado_sin_cambios';
    }
    if ($estadoActual === 'cancelado') {
      $pdo->rollBack();
      return 'pedido_cancelado_bloqueado';
    }
    if ($estadoActual === 'entregado') {
      $pdo->rollBack();
      return 'pedido_entregado_bloqueado';
    }
    if (!in_array($nuevoEstado, estadosPermitidosPedido($estadoActual, (string) $fila['tipo_entrega']), true)) {
      $pdo->rollBack();
      return 'estado_incompatible';
    }

    if ($nuevoEstado === 'cancelado') {
      restaurarStockDePedido($pdo, $pedidoId);
    }
    $upd = $pdo->prepare('UPDATE pedidos SET estado = :estado WHERE id = :id');
    $upd->execute([':estado' => $nuevoEstado, ':id' => $pedidoId]);

    $pdo->commit();
    // Aviso al cliente (después del commit y con su propio try/catch: nunca afecta al cambio de estado)
    crearNotificacionPedido($pdo, (int) $fila['usuario_id'], $pedidoId, $nuevoEstado);
    return $nuevoEstado === 'cancelado' ? 'pedido_cancelado' : 'estado_actualizado';
  } catch (Throwable $e) {
    if ($pdo->inTransaction()) {
      $pdo->rollBack();
    }
    error_log('[el-rapido] cambiarEstadoPedido #' . $pedidoId . ': ' . $e->getMessage());
    return 'error_estado';
  }
}

/**
 * Elimina un pedido (los items se borran por ON DELETE CASCADE).
 *  - Pendiente / en curso: antes devuelve el stock (todo atómico).
 *  - Cancelado: el stock ya se había devuelto, no se toca.
 *  - Entregado: la mercadería ya salió del local, así que NO se devuelve stock
 *    (antes se sumaba de nuevo y el inventario quedaba inflado).
 */
function eliminarPedidoAdmin(PDO $pdo, int $pedidoId): string {
  try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare('SELECT estado FROM pedidos WHERE id = :id FOR UPDATE');
    $stmt->execute([':id' => $pedidoId]);
    $estadoActual = $stmt->fetchColumn();

    if ($estadoActual === false) {
      $pdo->rollBack();
      return 'pedido_inexistente';
    }
    if ($estadoActual !== 'cancelado' && $estadoActual !== 'entregado') {
      restaurarStockDePedido($pdo, $pedidoId);
    }
    $pdo->prepare('DELETE FROM pedidos WHERE id = :id')->execute([':id' => $pedidoId]);

    $pdo->commit();
    return 'pedido_eliminado';
  } catch (Throwable $e) {
    if ($pdo->inTransaction()) {
      $pdo->rollBack();
    }
    error_log('[el-rapido] eliminarPedidoAdmin #' . $pedidoId . ': ' . $e->getMessage());
    return 'error_estado';
  }
}

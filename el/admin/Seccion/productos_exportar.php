<?php
// Descarga CSV: se transmite directo, sin acumular todo en el búfer de errores (ver includes/errores.php)
define('EL_RAPIDO_SIN_BUFFER', true);
require_once '../Config/funciones.php';
require_once '../Config/auth.php';
requerirAdmin();
$pdo = obtenerConexion();

/* Mismos filtros que en productos.php, para exportar exactamente lo que se está viendo */
$busqueda        = trim($_GET['q'] ?? '');
$categoriaFiltro = isset($_GET['cat']) && $_GET['cat'] !== '' ? (int) $_GET['cat'] : 0;
$estadoFiltro    = $_GET['estado'] ?? '';

[$where, $params] = construirFiltroProductos($busqueda, $categoriaFiltro, $estadoFiltro);
$productos = obtenerProductosFiltrados($pdo, $where, $params); // sin límite: exporta todo lo filtrado

/* ═══════════════════════════════════════════════════════════
   Generar el archivo CSV
   ═══════════════════════════════════════════════════════════ */
$nombreArchivo = 'productos_' . date('Y-m-d_His') . '.csv';

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $nombreArchivo . '"');

$salida = fopen('php://output', 'w');
fwrite($salida, "\xEF\xBB\xBF"); // BOM UTF-8 para tildes/ñ en Excel

// Mismo criterio que en pedidos_exportar.php: Excel en español separa
// los valores del CSV con ';' (usa la ',' como separador decimal), así
// que si el archivo viene separado por comas termina todo apretado en
// una sola columna al abrirlo.
const CSV_DELIM = ';';

fputcsv($salida, [
  'ID', 'Producto', 'Categoría', 'Precio', 'Descuento %',
  'Stock', 'Ventas', 'Destacado', 'Estado',
], CSV_DELIM);

foreach ($productos as $p) {
  fputcsv($salida, [
    $p['id'],
    csvSeguro($p['nombre']),
    csvSeguro($p['categoria_nombre']),
    number_format((float) $p['precio'], 2, ',', ''),
    $p['descuento_pct'],
    $p['stock'],
    $p['ventas'],
    $p['destacado'] ? 'Sí' : 'No',
    $p['activo'] ? 'Activo' : 'Inactivo',
  ], CSV_DELIM);
}

fclose($salida);
exit;

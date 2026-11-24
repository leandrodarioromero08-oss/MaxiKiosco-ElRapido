<?php
// Descarga CSV: se transmite directo, sin acumular todo en el búfer de errores (ver includes/errores.php)
define('EL_RAPIDO_SIN_BUFFER', true);
require_once '../Config/funciones.php';
require_once '../Config/auth.php';
requerirAdmin();
$pdo = obtenerConexion();

/* Mismos filtros que en pedidos.php, para exportar exactamente lo que se está viendo */
$busqueda     = trim($_GET['q'] ?? '');
$estadoFiltro = $_GET['estado'] ?? '';
$fechaFiltro  = $_GET['fecha'] ?? '';

[$where, $params] = construirFiltroPedidos($busqueda, $estadoFiltro, $fechaFiltro);
$pedidos = obtenerPedidosFiltrados($pdo, $where, $params); // sin límite: exporta todo lo filtrado

$etiquetasEstado  = etiquetasEstadoPedido();
$etiquetasPago    = etiquetasMetodoPago();
$etiquetasEntrega = ['retiro' => 'Retiro en local', 'delivery' => 'Delivery'];

/* ═══════════════════════════════════════════════════════════
   Generar el archivo CSV
   ═══════════════════════════════════════════════════════════ */
$nombreArchivo = 'pedidos_' . date('Y-m-d_His') . '.csv';

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $nombreArchivo . '"');

$salida = fopen('php://output', 'w');
fwrite($salida, "\xEF\xBB\xBF"); // BOM UTF-8 para tildes/ñ en Excel

// Excel en español (Argentina incluida) usa la coma como separador
// decimal, así que al abrir un CSV separado por comas mete todo en una
// sola columna. El separador de lista que realmente usa ahí es el
// punto y coma, por eso separamos con ';' (fputcsv lo declara con el
// 4to parámetro; el resto de las comillas/escapes los sigue resolviendo solo).
const CSV_DELIM = ';';

fputcsv($salida, [
  'Pedido', 'Cliente', 'Teléfono', 'Entrega', 'Dirección',
  'Método de pago', 'Estado', 'Recargo envío', 'Total', 'Fecha',
  // Columnas nuevas al final, para no romper el orden de un CSV que
  // alguien ya esté procesando de forma automática:
  'Nombre entrega', 'Apellido entrega', 'Calle', 'Número', 'Barrio', 'Referencia',
], CSV_DELIM);

foreach ($pedidos as $p) {
  fputcsv($salida, [
    '#' . $p['id'],
    csvSeguro($p['cliente_nombre']),
    csvSeguro($p['telefono_contacto'] ?? ''),
    $etiquetasEntrega[$p['tipo_entrega']] ?? $p['tipo_entrega'],
    csvSeguro($p['direccion'] ?? ''),
    $etiquetasPago[$p['metodo_pago']][0] ?? $p['metodo_pago'],
    $etiquetasEstado[$p['estado']][0] ?? $p['estado'],
    number_format((float) $p['recargo_envio'], 2, ',', ''),
    number_format((float) $p['total'], 2, ',', ''),
    date('d/m/Y H:i', strtotime($p['creado_en'])),
    csvSeguro($p['entrega_nombre'] ?? ''),
    csvSeguro($p['entrega_apellido'] ?? ''),
    csvSeguro($p['entrega_calle'] ?? ''),
    csvSeguro($p['entrega_numero'] ?? ''),
    csvSeguro($p['entrega_barrio'] ?? ''),
    csvSeguro($p['entrega_referencia'] ?? ''),
  ], CSV_DELIM);
}

fclose($salida);
exit;

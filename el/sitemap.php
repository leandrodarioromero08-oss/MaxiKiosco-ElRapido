<?php
/**
 * sitemap.xml dinámico (Paso 11).
 * Se sirve en /sitemap.xml gracias a la regla de reescritura del .htaccess (o directamente como /sitemap.php).
 * Lista solo páginas públicas e indexables: inicio, catálogo, una URL por categoría con productos
 * y las páginas informativas. Las pantallas privadas (carrito, mi-cuenta, login...) no van.
 * Si la base de datos no responde, igual se devuelven las páginas fijas.
 */
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/funciones.php';

$sitio = rtrim(SITE_URL, '/');
$urls = [
  $sitio . '/',
  $sitio . '/productos.php',
  $sitio . '/nosotros.php',
  $sitio . '/preguntas-frecuentes.php',
  $sitio . '/politicas.php',
  $sitio . '/terminos.php',
];

try {
  foreach (obtenerCategoriasPublico(obtenerConexion()) as $cat) {
    if ((int) ($cat['cantidad'] ?? 0) > 0) {
      $urls[] = $sitio . '/productos.php?cat=' . urlencode($cat['slug']);
    }
  }
} catch (Throwable $e) {
  error_log('[el-rapido] sitemap: ' . $e->getMessage());
}

// Descarta cualquier salida acumulada antes de escribir el XML
while (ob_get_level() > 0) { @ob_end_clean(); }
header('Content-Type: application/xml; charset=utf-8');

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
foreach ($urls as $u) {
  echo '  <url><loc>' . htmlspecialchars($u, ENT_XML1 | ENT_QUOTES, 'UTF-8') . '</loc></url>' . "\n";
}
echo '</urlset>' . "\n";

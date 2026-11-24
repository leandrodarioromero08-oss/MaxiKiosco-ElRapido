<?php
/**
 * robots.txt dinámico (Paso 11).
 * Se sirve en /robots.txt gracias a la regla de reescritura del .htaccess; si el servidor no tiene
 * mod_rewrite también se puede abrir directamente como /robots.php.
 *
 * Usa SITE_URL (includes/config.php / config.local.php) para indicar la URL absoluta del sitemap,
 * así no hay que editar nada a mano al pasar de localhost al dominio real.
 *
 * Qué NO se bloquea a propósito: las pantallas privadas (carrito, mi-cuenta, login...). Esas ya
 * llevan <meta name="robots" content="noindex">; si además se bloquearan acá, el buscador no podría
 * leer esa instrucción. Tampoco se bloquean las variantes "?orden=" del catálogo: cada página del
 * catálogo declara su URL canónica, que es la forma correcta de evitar contenido duplicado.
 * Solo se bloquean los scripts de acción, que no muestran ninguna página.
 */
require_once __DIR__ . '/includes/config.php';

$base = urlBaseSitio(); // "" si el sitio está en la raíz del dominio, "/carpeta" si no

if (!headers_sent()) {
  header('Content-Type: text/plain; charset=utf-8');
}

$reglas = [
  'User-agent: *',
  'Disallow: ' . $base . '/carrito_agregar.php',
  'Disallow: ' . $base . '/carrito_agregar_promo.php',
  'Disallow: ' . $base . '/guardar_compra_semanal.php',
  'Disallow: ' . $base . '/pedido_repetir.php',
  'Disallow: ' . $base . '/chatbot.php',
  'Disallow: ' . $base . '/logout.php',
  'Disallow: ' . $base . '/admin/',
  '',
  'Sitemap: ' . rtrim(SITE_URL, '/') . '/sitemap.xml',
];
echo implode("\n", $reglas), "\n";

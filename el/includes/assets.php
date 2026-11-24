<?php
/* ===== ASSETS.PHP - EL RÁPIDO =====
 * Rendimiento de recursos estáticos (CSS / JS):
 *
 *  1) assetUrl('assets/js/global.js')
 *     Devuelve la ruta con "?v=<fecha de modificación del archivo>". Mientras el archivo no cambie, la URL es
 *     la misma y el navegador (y el service worker) la sirven desde caché SIN preguntarle nada al servidor;
 *     en cuanto editás el archivo, la URL cambia sola y todos reciben la versión nueva. No hay que subir
 *     ningún número de versión a mano.
 *
 *  2) cssLinks([...archivos])
 *     Junta varias hojas de estilo en UN solo archivo minificado (assets/css/_b-xxxx.css) y devuelve su <link>.
 *     Pasa de ~10 pedidos que bloquean el pintado a 1. El orden de las hojas se respeta (la cascada no cambia).
 *     Si no se puede escribir el archivo (permisos del hosting), devuelve los <link> sueltos de siempre:
 *     el sitio se ve igual, solo que sin esta optimización.
 *     El paquete se regenera solo cuando cambia alguna de las hojas.
 */

function assetUrl(string $ruta): string {
  $ruta = ltrim($ruta, '/');
  $archivo = ROOT_PATH . '/' . $ruta;
  $mtime = is_file($archivo) ? (int) @filemtime($archivo) : 0;
  return $ruta . ($mtime ? '?v=' . $mtime : '');
}

/** Minificación conservadora de CSS: respeta textos y data: URLs, quita comentarios y espacios sobrantes. */
function cssMinificar(string $css): string {
  $css = preg_replace('/^\xEF\xBB\xBF/', '', $css);            // BOM
  $guardados = [];
  // Una sola pasada de izquierda a derecha: así un apóstrofe dentro de un comentario no se confunde con un texto.
  $css = preg_replace_callback(
    '~/\*.*?\*/|"(?:[^"\\\\]|\\\\.)*"|\'(?:[^\'\\\\]|\\\\.)*\'~s',
    function ($m) use (&$guardados) {
      if ($m[0][0] === '/') return '';                           // comentario
      $guardados[] = $m[0];
      return "\x01" . (count($guardados) - 1) . "\x02";
    },
    $css
  );
  $css = preg_replace('/\s+/', ' ', $css);
  $css = preg_replace('/\s*([{};])\s*/', '$1', $css);
  $css = preg_replace('/,\s+/', ',', $css);
  $css = str_replace(';}', '}', $css);
  $css = preg_replace_callback('/\x01(\d+)\x02/', function ($m) use ($guardados) { return $guardados[(int) $m[1]]; }, $css);
  return trim($css);
}

/** @param string[] $archivos rutas relativas al sitio, p. ej. 'assets/css/global.css' */
function cssLinks(array $archivos): string {
  $archivos = array_values(array_filter($archivos));
  $sueltos = function () use ($archivos) {
    $h = '';
    foreach ($archivos as $a) $h .= '  <link rel="stylesheet" href="' . htmlspecialchars(assetUrl($a)) . '">' . "\n";
    return $h;
  };
  if (!$archivos) return '';

  $dir = ROOT_PATH . '/assets/css';
  $firma = substr(md5(implode('|', $archivos)), 0, 8);
  $sellos = '';
  foreach ($archivos as $a) {
    $f = ROOT_PATH . '/' . ltrim($a, '/');
    if (!is_file($f)) return $sueltos();                         // algo raro: no se arriesga
    $sellos .= $a . ':' . filemtime($f) . ':' . filesize($f) . ';';
  }
  $nombre = '_b-' . $firma . '-' . substr(md5($sellos), 0, 8) . '.css';
  $destino = $dir . '/' . $nombre;

  if (!is_file($destino)) {
    $css = '';
    foreach ($archivos as $a) {
      $c = @file_get_contents(ROOT_PATH . '/' . ltrim($a, '/'));
      if ($c === false) return $sueltos();
      $css .= cssMinificar($c) . "\n";
    }
    $tmp = $destino . '.' . getmypid() . '.tmp';
    if (!is_dir($dir) || !is_writable($dir) || @file_put_contents($tmp, $css) === false) {
      @unlink($tmp);
      return $sueltos();
    }
    if (!@rename($tmp, $destino)) { @unlink($tmp); return $sueltos(); }
    // Limpieza: paquetes viejos de esta misma combinación de hojas
    foreach (glob($dir . '/_b-' . $firma . '-*.css') ?: [] as $viejo) {
      if ($viejo !== $destino) @unlink($viejo);
    }
  }
  return '  <link rel="stylesheet" href="assets/css/' . $nombre . '">' . "\n";
}

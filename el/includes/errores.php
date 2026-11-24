<?php
/**
 * ERRORES — RED DE SEGURIDAD GLOBAL
 * ------------------------------------------------------------
 * Se carga desde includes/config.php, así que cualquier página del sitio
 * (pública o del panel admin) queda cubierta.
 *
 * Antes: una excepción no capturada (consulta que falla, tabla faltante,
 * deadlock, MySQL que se cae a mitad de página...) terminaba en una pantalla
 * en blanco, o en una página a medio dibujar si header.php ya había impreso
 * el menú.
 *
 * Ahora:
 *  - Toda la salida HTML se acumula en un búfer (ob_start). Si algo falla, el
 *    búfer se descarta y se muestra UNA página de error limpia, con código 500.
 *  - Las excepciones no capturadas y los errores fatales de PHP se registran en
 *    el log del servidor y muestran esa página (en desarrollo, con el detalle).
 *  - Si la petición esperaba JSON (chatbot, buscador del admin), se responde
 *    JSON con el código de error en lugar de HTML.
 *  - 404.php, 500.php y db.php reutilizan la misma página de error.
 *
 * Los scripts que transmiten archivos grandes (exportaciones CSV) definen
 * EL_RAPIDO_SIN_BUFFER antes de cargar config.php para no acumular todo en memoria.
 */

/**
 * Ruta web de la carpeta raíz del sitio ("" si está en la raíz del dominio,
 * "/el-rapido" si está en una subcarpeta). Se calcula a partir del disco para
 * que los enlaces de la página de error funcionen desde cualquier carpeta
 * (incluso admin/Seccion/) y aunque SITE_URL no esté bien configurado.
 */
function urlBaseSitio(): string {
  $doc  = rtrim(str_replace('\\', '/', (string) ($_SERVER['DOCUMENT_ROOT'] ?? '')), '/');
  $raiz = rtrim(str_replace('\\', '/', ROOT_PATH), '/');
  if ($doc !== '' && stripos($raiz, $doc) === 0) {
    return substr($raiz, strlen($doc));
  }
  return rtrim((string) parse_url(SITE_URL, PHP_URL_PATH), '/');
}

/** Descarta cualquier salida acumulada (una página a medio dibujar no sirve de nada). */
function descartarSalidaPendiente(): void {
  while (ob_get_level() > 0) {
    @ob_end_clean();
  }
}

/** true si la respuesta que se estaba armando era JSON (chatbot, endpoints del admin) */
function respuestaEsJson(): bool {
  foreach (headers_list() as $cabecera) {
    if (stripos($cabecera, 'content-type: application/json') === 0) {
      return true;
    }
  }
  return false;
}

/**
 * Responde con un error al navegador y deja todo listo para que el script termine
 * (quien la llama debe hacer exit). No usa la base de datos ni funciones.php,
 * así que funciona aunque lo que haya fallado sea justamente MySQL.
 *
 * @param int         $codigo  Código HTTP (404, 500, 503...)
 * @param string      $titulo  Título visible
 * @param string      $mensaje Explicación para el cliente
 * @param string|null $detalle Detalle técnico: SOLO se muestra en desarrollo
 */
function responderError(int $codigo, string $titulo, string $mensaje, ?string $detalle = null): void {
  $esJson = respuestaEsJson();
  descartarSalidaPendiente();

  if (!headers_sent()) {
    http_response_code($codigo);
    header('Cache-Control: no-store');
    // Si el script ya había declarado una descarga (CSV), el error no debe bajarse como archivo.
    header_remove('Content-Disposition');
    header_remove('Content-Length');
    header('Content-Type: ' . ($esJson ? 'application/json' : 'text/html') . '; charset=utf-8');
  }

  if ($esJson) {
    echo json_encode(['error' => $mensaje, 'codigo' => $codigo], JSON_UNESCAPED_UNICODE);
    return;
  }

  $base     = htmlspecialchars(urlBaseSitio(), ENT_QUOTES, 'UTF-8');
  $negocio  = htmlspecialchars(defined('NEGOCIO_NOMBRE') ? NEGOCIO_NOMBRE : 'El Rápido', ENT_QUOTES, 'UTF-8');
  $tituloH  = htmlspecialchars($titulo, ENT_QUOTES, 'UTF-8');
  $mensajeH = htmlspecialchars($mensaje, ENT_QUOTES, 'UTF-8');
  // Ícono SVG lineal (mismo estilo que el sitio) en vez de emoji; esta página es independiente y no carga iconos.php
  $trazosIcono = $codigo === 404
    ? '<circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/>'
    : ($codigo === 503
      ? '<path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/>'
      : '<path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"/><path d="M12 9v4"/><path d="M12 17h.01"/>');
  $emoji    = '<svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="#D91F26" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">' . $trazosIcono . '</svg>';
  $mostrarDetalle = $detalle !== null && $detalle !== '' && defined('ENV') && ENV === 'development';
  $detalleH = $mostrarDetalle ? htmlspecialchars($detalle, ENT_QUOTES, 'UTF-8') : '';
  $botonSecundario = $codigo === 404
    ? '<a class="btn btn-sec" href="' . $base . '/productos.php">Ver productos</a>'
    : '<a class="btn btn-sec" href="">Reintentar</a>';

  echo '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8">'
     . '<meta name="viewport" content="width=device-width, initial-scale=1.0">'
     . '<meta name="robots" content="noindex">'
     . '<link rel="icon" type="image/png" href="' . $base . '/assets/img/icono-el-rapido.png">'
     . '<title>' . $codigo . ' — ' . $negocio . '</title>'
     . '<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Montserrat:wght@400;600;700&display=swap">'
     . '<style>'
     . ':root{--rojo:#D91F26;--rojo-hover:#b81416;--amarillo:#FFD600;--negro:#1a1a1a}'
     . '*{box-sizing:border-box}'
     . 'body{margin:0;font-family:Montserrat,Arial,sans-serif;background:#f5f5f5;color:var(--negro);min-height:100vh;display:flex;flex-direction:column}'
     . '.hero{background:var(--negro);padding:40px 24px 36px;text-align:center;position:relative}'
     . '.hero::after{content:"";position:absolute;bottom:0;left:50%;transform:translateX(-50%);width:64px;height:4px;background:var(--amarillo);border-radius:4px}'
     . '.marca{font-family:"Bebas Neue",Impact,sans-serif;font-size:28px;letter-spacing:1px;color:#fff;text-decoration:none}'
     . '.card{background:#fff;max-width:520px;width:calc(100% - 32px);margin:32px auto;padding:36px 28px;border-radius:12px;box-shadow:0 2px 10px rgba(0,0,0,.08);text-align:center}'
     . '.emoji{line-height:1;display:flex;justify-content:center}'
     . '.codigo{font-family:"Bebas Neue",Impact,sans-serif;font-size:72px;line-height:1;color:var(--rojo);margin:8px 0 0}'
     . 'h1{font-size:20px;margin:8px 0 10px}'
     . 'p{color:#555;font-size:15px;line-height:1.55;margin:0 0 22px}'
     . '.btn{display:inline-block;padding:12px 22px;border-radius:8px;font-weight:700;font-size:14px;text-decoration:none;margin:4px}'
     . '.btn-pri{background:var(--rojo);color:#fff}.btn-pri:hover{background:var(--rojo-hover)}'
     . '.btn-sec{background:#fff;color:var(--negro);border:1px solid #ccc}'
     . 'a:focus-visible{outline:3px solid var(--rojo);outline-offset:2px}'
     . 'pre{text-align:left;background:#fff4e5;border:1px solid #f5c98a;color:#8a4b00;padding:12px;border-radius:8px;font-size:12px;white-space:pre-wrap;word-break:break-word;margin:18px 0 0}'
     . '</style></head><body>'
     . '<header class="hero"><a class="marca" href="' . $base . '/index.php">' . $negocio . '</a></header>'
     . '<main class="card"><div class="emoji" aria-hidden="true">' . $emoji . '</div><div class="codigo">' . $codigo . '</div>'
     . '<h1>' . $tituloH . '</h1><p>' . $mensajeH . '</p>'
     . '<a class="btn btn-pri" href="' . $base . '/index.php">Volver al inicio</a>' . $botonSecundario
     . ($mostrarDetalle ? '<pre>' . $detalleH . '</pre>' : '')
     . '</main></body></html>';
}

/** Excepción que nadie capturó: se registra y se muestra la página de error 500 */
function manejarExcepcionNoCapturada(Throwable $e): void {
  error_log(sprintf('[el-rapido] Excepción no capturada %s: %s en %s:%d', get_class($e), $e->getMessage(), $e->getFile(), $e->getLine()));
  responderError(
    500,
    'Algo salió mal de nuestro lado',
    'Tuvimos un problema al procesar tu solicitud. Ya quedó registrado. Probá de nuevo en un momento.',
    get_class($e) . ': ' . $e->getMessage() . "\n" . $e->getFile() . ':' . $e->getLine()
  );
}

/** Errores fatales de PHP (memoria agotada, etc.): PHP ya los registra, acá solo se muestra la página */
function manejarCierreConErrorFatal(): void {
  $error = error_get_last();
  if ($error === null || !in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
    return;
  }
  responderError(
    500,
    'Algo salió mal de nuestro lado',
    'Tuvimos un problema al procesar tu solicitud. Ya quedó registrado. Probá de nuevo en un momento.',
    $error['message'] . "\n" . $error['file'] . ':' . $error['line']
  );
}

/** Activa el búfer de salida y los manejadores globales (se llama una vez desde config.php) */
function registrarManejadoresDeError(): void {
  if (PHP_SAPI !== 'cli' && !defined('EL_RAPIDO_SIN_BUFFER')) {
    ob_start();
  }
  set_exception_handler('manejarExcepcionNoCapturada');
  register_shutdown_function('manejarCierreConErrorFatal');
}

<?php
/**
 * Sistema de íconos del panel de administración.
 *
 * Mismo criterio que includes/iconos.php del sitio público: reemplaza
 * los emojis usados como íconos (📦 🚚 ✅ ⚠️ 🗑️ etc.) por un set de
 * SVG lineales (un solo grosor de trazo, sin relleno, stroke-linecap
 * y stroke-linejoin "round") que heredan el color vía currentColor,
 * en vez de depender de cómo el sistema operativo dibuja el emoji.
 *
 * Uso: <?= icono('pedidos') ?>  o  <?= icono('pedidos', 'mi-clase', 24) ?>
 *
 * OJO: esto es solo para íconos de INTERFAZ (navegación, botones,
 * tarjetas de estadísticas, badges, alertas). El emoji que el admin
 * elige como "ícono de respaldo" de un producto (campo `emoji` en la
 * tabla `productos`) es un dato del negocio, no un ícono del sistema,
 * así que ESE no se toca acá.
 */

function icono(string $nombre, string $clase = '', int $tam = 20): string {
  // "static": el array de paths se arma UNA sola vez por request, la
  // primera vez que se llama a icono(). Las llamadas siguientes (por
  // ejemplo, una por fila en una tabla de 40 productos) reutilizan el
  // mismo array en vez de reconstruirlo entero cada vez.
  static $trazos = [
    // --- Navegación / secciones ---
    'inicio'          => '<path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9,22 9,12 15,12 15,22"/>',
    'productos'       => '<path d="M11 21.73a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73Z"/><path d="M12 22V12"/><path d="M3.29 7 12 12l8.71-5"/><path d="m7.5 4.27 9 5.15"/>',
    'pedidos'         => '<circle cx="8" cy="21" r="1"/><circle cx="19" cy="21" r="1"/><path d="M2.05 2.05h2l2.66 12.42a2 2 0 0 0 2 1.58h9.78a2 2 0 0 0 2-1.58l1.65-7.42H5.12"/>',
    'promociones'     => '<path d="M12.586 2.586A2 2 0 0 0 11.172 2H4a2 2 0 0 0-2 2v7.172a2 2 0 0 0 .586 1.414l8.704 8.704a2.426 2.426 0 0 0 3.42 0l6.58-6.58a2.426 2.426 0 0 0 0-3.42z"/><circle cx="7.5" cy="7.5" r="1.5" fill="currentColor" stroke="none"/>',
    'configuracion'   => '<path d="M12.22 2h-.44a2 2 0 0 0-2 2v.18a2 2 0 0 1-1 1.73l-.43.25a2 2 0 0 1-2 0l-.15-.08a2 2 0 0 0-2.73.73l-.22.38a2 2 0 0 0 .73 2.73l.15.1a2 2 0 0 1 1 1.72v.51a2 2 0 0 1-1 1.74l-.15.09a2 2 0 0 0-.73 2.73l.22.38a2 2 0 0 0 2.73.73l.15-.08a2 2 0 0 1 2 0l.43.25a2 2 0 0 1 1 1.73V20a2 2 0 0 0 2 2h.44a2 2 0 0 0 2-2v-.18a2 2 0 0 1 1-1.73l.43-.25a2 2 0 0 1 2 0l.15.08a2 2 0 0 0 2.73-.73l.22-.39a2 2 0 0 0-.73-2.73l-.15-.08a2 2 0 0 1-1-1.74v-.5a2 2 0 0 1 1-1.74l.15-.09a2 2 0 0 0 .73-2.73l-.22-.38a2 2 0 0 0-2.73-.73l-.15.08a2 2 0 0 1-2 0l-.43-.25a2 2 0 0 1-1-1.73V4a2 2 0 0 0-2-2z"/><circle cx="12" cy="12" r="3"/>',
    'salir'           => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" x2="9" y1="12" y2="12"/>',
    'cohete'          => '<path d="M4.5 16.5c-1.5 1.26-2 5-2 5s3.74-.5 5-2c.71-.84.7-2.13-.09-2.91a2.18 2.18 0 0 0-2.91-.09z"/><path d="m12 15-3-3a22 22 0 0 1 2-3.95A12.88 12.88 0 0 1 22 2c0 2.72-.78 7.5-6 11a22.35 22.35 0 0 1-4 2z"/><path d="M9 12H4s.55-3.03 2-4c1.62-1.08 5 0 5 0"/><path d="M12 15v5s3.03-.55 4-2c1.08-1.62 0-5 0-5"/>',

    // --- Topbar ---
    'menu'            => '<line x1="4" x2="20" y1="6" y2="6"/><line x1="4" x2="20" y1="12" y2="12"/><line x1="4" x2="20" y1="18" y2="18"/>',
    'buscar'          => '<circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/>',
    'campana'         => '<path d="M10.268 21a2 2 0 0 0 3.464 0"/><path d="M3.262 15.326A1 1 0 0 0 4 17h16a1 1 0 0 0 .74-1.673C19.41 13.956 18 12.499 18 8A6 6 0 0 0 6 8c0 4.499-1.411 5.956-2.738 7.326"/>',
    'usuario'         => '<path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>',
    'chevron-abajo'   => '<path d="m6 9 6 6 6-6"/>',

    // --- Acciones de tabla / botones ---
    'lapiz'           => '<path d="M21.174 6.812a1 1 0 0 0-3.986-3.987L3.842 16.174a2 2 0 0 0-.5.83l-1.321 4.352a.5.5 0 0 0 .623.622l4.353-1.32a2 2 0 0 0 .83-.497z"/><path d="m15 5 4 4"/>',
    'basura'          => '<path d="M3 6h18"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"/><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><line x1="10" x2="10" y1="11" y2="17"/><line x1="14" x2="14" y1="11" y2="17"/>',
    'ojo'             => '<path d="M2.062 12.348a1 1 0 0 1 0-.696 10.75 10.75 0 0 1 19.876 0 1 1 0 0 1 0 .696 10.75 10.75 0 0 1-19.876 0"/><circle cx="12" cy="12" r="3"/>',
    'copiar'          => '<rect width="14" height="14" x="8" y="8" rx="2" ry="2"/><path d="M4 16c-1.1 0-2-.9-2-2V4c0-1.1.9-2 2-2h10c1.1 0 2 .9 2 2"/>',
    'pausa'           => '<rect x="14" y="4" width="4" height="16" rx="1"/><rect x="6" y="4" width="4" height="16" rx="1"/>',
    'reproducir'      => '<polygon points="6 3 20 12 6 21 6 3"/>',
    'mas-opciones'    => '<circle cx="12" cy="12" r="1.25" fill="currentColor" stroke="none"/><circle cx="12" cy="5" r="1.25" fill="currentColor" stroke="none"/><circle cx="12" cy="19" r="1.25" fill="currentColor" stroke="none"/>',
    'prohibido'       => '<circle cx="12" cy="12" r="10"/><path d="m4.9 4.9 14.2 14.2"/>',
    'mas'             => '<path d="M5 12h14"/><path d="M12 5v14"/>',
    'x'               => '<path d="M18 6 6 18"/><path d="m6 6 12 12"/>',

    // --- Filtros / exportar ---
    'filtrar'         => '<polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/>',
    'descargar'       => '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" x2="12" y1="15" y2="3"/>',

    // --- Tarjetas de estadísticas ---
    'carrito'         => '<circle cx="8" cy="21" r="1"/><circle cx="19" cy="21" r="1"/><path d="M2.05 2.05h2l2.66 12.42a2 2 0 0 0 2 1.58h9.78a2 2 0 0 0 2-1.58l1.65-7.42H5.12"/>',
    'dinero'          => '<circle cx="12" cy="12" r="10"/><path d="M16 8h-6a2 2 0 1 0 0 4h4a2 2 0 1 1 0 4H8"/><path d="M12 18V6"/>',
    'paquete'         => '<path d="M11 21.73a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73Z"/><path d="M12 22V12"/><path d="M3.29 7 12 12l8.71-5"/><path d="m7.5 4.27 9 5.15"/>',
    'fuego'           => '<path d="M8.5 14.5A2.5 2.5 0 0 0 11 12c0-1.38-.5-2-1-3-1.072-2.143-.224-4.054 2-6 .5 2.5 2 4.9 4 6.5 2 1.6 3 3.5 3 5.5a7 7 0 1 1-14 0c0-1.153.433-2.294 1-3a2.5 2.5 0 0 0 2.5 2.5z"/>',
    'check-circulo'   => '<circle cx="12" cy="12" r="10"/><path d="m9 12 2 2 4-4"/>',
    'check'           => '<path d="M20 6 9 17l-5-5"/>',
    'etiqueta'        => '<path d="M12.586 2.586A2 2 0 0 0 11.172 2H4a2 2 0 0 0-2 2v7.172a2 2 0 0 0 .586 1.414l8.704 8.704a2.426 2.426 0 0 0 3.42 0l6.58-6.58a2.426 2.426 0 0 0 0-3.42z"/><circle cx="7.5" cy="7.5" r="1.5" fill="currentColor" stroke="none"/>',
    'calendario'      => '<path d="M8 2v4"/><path d="M16 2v4"/><rect width="18" height="18" x="3" y="4" rx="2"/><path d="M3 10h18"/>',
    'reloj'           => '<circle cx="12" cy="12" r="10"/><polyline points="12,6 12,12 16,14"/>',
    'grafico'         => '<path d="M3 3v16a2 2 0 0 0 2 2h16"/><path d="M18 17V9"/><path d="M13 17V5"/><path d="M8 17v-3"/>',

    // --- Pedidos / entrega ---
    'camion'          => '<path d="M14 18V6a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2v11a1 1 0 0 0 1 1h2"/><path d="M15 18H9"/><path d="M19 18h2a1 1 0 0 0 1-1v-3.65a1 1 0 0 0-.22-.624l-3.48-4.35A1 1 0 0 0 17.52 8H14"/><circle cx="17" cy="18" r="2"/><circle cx="7" cy="18" r="2"/>',
    'casa'            => '<path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9,22 9,12 15,12 15,22"/>',
    'telefono'        => '<path d="M13.832 16.568a1 1 0 0 0 1.213-.303l.355-.465a2 2 0 0 1 2.5-.635l4.503 2.259a1 1 0 0 1 .555 1.1l-.8 3.995a1 1 0 0 1-.999.801C10.253 23.32.68 13.747.68 2.847a1 1 0 0 1 .8-.999l4-.8a1 1 0 0 1 1.1.555L8.839 6.1a2 2 0 0 1-.635 2.5l-.465.355a1 1 0 0 0-.303 1.213 11.5 11.5 0 0 0 6.396 6.4"/>',
    'mapa'            => '<path d="M14.106 5.553a2 2 0 0 0 1.788 0l3.659-1.83A1 1 0 0 1 21 4.619v12.764a1 1 0 0 1-.553.894l-4.553 2.277a2 2 0 0 1-1.788 0l-4.212-2.106a2 2 0 0 0-1.788 0l-3.659 1.83A1 1 0 0 1 3 19.381V6.618a1 1 0 0 1 .553-.894l4.553-2.277a2 2 0 0 1 1.788 0z"/><path d="M15 5.764v15"/><path d="M9 3.236v15"/>',

    // --- Alertas / mensajes de estado ---
    'alerta'          => '<path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"/><path d="M12 9v4"/><path d="M12 17h.01"/>',
    'info'            => '<circle cx="12" cy="12" r="10"/><path d="M12 16v-4"/><path d="M12 8h.01"/>',

    // --- Tipos de promoción (wizard "Nueva promoción") ---
    'explosion'       => '<path d="m14 4-3 9L2.5 8.5 5 21l6.5-4.5L14 21l3-9 8.5 4.5L23 4l-6.5 4.5z"/>',
    'combo'           => '<path d="M17 3a2.83 2.83 0 0 1 4 4L7.5 20.5 2 22l1.5-5.5Z"/>',
    'reciclar'        => '<path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/>',
    'numero-2'        => '<circle cx="12" cy="12" r="10"/><path d="M9.5 9a2.5 2.5 0 0 1 5 0c0 1.4-1.2 2.2-2.2 3L9.5 15h5"/>',
    'numero-3'        => '<circle cx="12" cy="12" r="10"/><path d="M9.8 9.2a2.3 2.3 0 0 1 4.4.8 2 2 0 0 1-2 2 2 2 0 0 1 2 2 2.3 2.3 0 0 1-4.4.8"/>',
    'reloj-alarma'    => '<circle cx="12" cy="13" r="8"/><path d="M12 9v4l2 2"/><path d="M5 3 2 6"/><path d="m22 6-3-3"/><path d="M6.38 18.7 4 21"/><path d="M17.64 18.67 20 21"/>',

    // --- Configuración ---
    'correo'          => '<rect width="20" height="16" x="2" y="4" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/>',
    'escudo'          => '<path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z"/>',
    'candado'         => '<rect width="18" height="11" x="3" y="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>',
  ];

  $contenido = $trazos[$nombre] ?? '';
  $claseAttr = $clase !== '' ? ' ' . htmlspecialchars($clase) : '';

  return '<svg xmlns="http://www.w3.org/2000/svg" width="' . $tam . '" height="' . $tam . '" viewBox="0 0 24 24" '
       . 'fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" '
       . 'class="icono-svg' . $claseAttr . '" aria-hidden="true">' . $contenido . '</svg>';
}

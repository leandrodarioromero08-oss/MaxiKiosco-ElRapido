<?php
/**
 * Sistema de íconos del sitio público.
 *
 * Reemplaza los emojis por un set de íconos SVG consistente (estilo
 * lineal, un solo grosor de trazo, sin relleno) que hereda el color del
 * texto vía currentColor — así se puede controlar el color por CSS
 * igual que cualquier texto, sin depender de cómo cada sistema
 * operativo dibuja los emojis.
 *
 * Uso: <?= icono('carrito') ?>  o  <?= icono('carrito', 'mi-clase', 24) ?>
 *
 * Se va ampliando a medida que se rediseña cada página (no todos los
 * emojis del sitio tienen ícono todavía).
 */

function icono(string $nombre, string $clase = '', int $tam = 20): string {
  $trazos = [
    'carrito'        => '<circle cx="8" cy="21" r="1"/><circle cx="19" cy="21" r="1"/><path d="M2.05 2.05h2l2.66 12.42a2 2 0 0 0 2 1.58h9.78a2 2 0 0 0 2-1.58l1.65-7.42H5.12"/>',
    'camion'         => '<path d="M14 18V6a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2v11a1 1 0 0 0 1 1h2"/><path d="M15 18H9"/><path d="M19 18h2a1 1 0 0 0 1-1v-3.65a1 1 0 0 0-.22-.624l-3.48-4.35A1 1 0 0 0 17.52 8H14"/><circle cx="17" cy="18" r="2"/><circle cx="7" cy="18" r="2"/>',
    'casa'           => '<path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9,22 9,12 15,12 15,22"/>',
    'mensaje'        => '<path d="M7.9 20A9 9 0 1 0 4 16.1L2 22Z"/>',
    'escudo-check'   => '<path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z"/><path d="m9 12 2 2 4-4"/>',
    'candado'        => '<rect width="18" height="11" x="3" y="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>',
    'basura'         => '<path d="M3 6h18"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"/><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><line x1="10" x2="10" y1="11" y2="17"/><line x1="14" x2="14" y1="11" y2="17"/>',
    'check-circulo'  => '<circle cx="12" cy="12" r="10"/><path d="m9 12 2 2 4-4"/>',
    'calendario'     => '<path d="M8 2v4"/><path d="M16 2v4"/><rect width="18" height="18" x="3" y="4" rx="2"/><path d="M3 10h18"/>',
    'alerta'         => '<path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"/><path d="M12 9v4"/><path d="M12 17h.01"/>',
    'info'           => '<circle cx="12" cy="12" r="10"/><path d="M12 16v-4"/><path d="M12 8h.01"/>',
    'flecha-izq'     => '<path d="m12 19-7-7 7-7"/><path d="M19 12H5"/>',
    'flecha-der'     => '<path d="M5 12h14"/><path d="m12 5 7 7-7 7"/>',
    'mas'            => '<path d="M5 12h14"/><path d="M12 5v14"/>',
    'menos'          => '<path d="M5 12h14"/>',
    'ubicacion'      => '<path d="M20 10c0 4.993-5.539 10.193-7.399 11.799a1 1 0 0 1-1.202 0C9.539 20.193 4 14.993 4 10a8 8 0 0 1 16 0"/><circle cx="12" cy="10" r="3"/>',
    'luna'           => '<path d="M12 3a6 6 0 0 0 9 9 9 9 0 1 1-9-9Z"/>',
    'sol'            => '<circle cx="12" cy="12" r="4"/><path d="M12 2v2"/><path d="M12 20v2"/><path d="m4.93 4.93 1.41 1.41"/><path d="m17.66 17.66 1.41 1.41"/><path d="M2 12h2"/><path d="M20 12h2"/><path d="m6.34 17.66-1.41 1.41"/><path d="m19.07 4.93-1.41 1.41"/>',
    'corazon'        => '<path d="M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.3 1.5 4.05 3 5.5l7 7Z"/>',
    'etiqueta'       => '<path d="M12.586 2.586A2 2 0 0 0 11.172 2H4a2 2 0 0 0-2 2v7.172a2 2 0 0 0 .586 1.414l8.704 8.704a2.426 2.426 0 0 0 3.42 0l6.58-6.58a2.426 2.426 0 0 0 0-3.42z"/><circle cx="7.5" cy="7.5" r="1.5" fill="currentColor" stroke="none"/>',
    'paquete'        => '<path d="M11 21.73a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73Z"/><path d="M12 22V12"/><path d="M3.29 7 12 12l8.71-5"/><path d="m7.5 4.27 9 5.15"/>',
    'tienda'         => '<path d="M2 7h20"/><path d="M3 7v11a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V7"/><path d="M6 3h12l2 4H4z"/><path d="M10 12h4"/>',
    'reloj'          => '<circle cx="12" cy="12" r="10"/><polyline points="12,6 12,12 16,14"/>',
    'telefono'       => '<path d="M13.832 16.568a1 1 0 0 0 1.213-.303l.355-.465a2 2 0 0 1 2.5-.635l4.503 2.259a1 1 0 0 1 .555 1.1l-.8 3.995a1 1 0 0 1-.999.801C10.253 23.32.68 13.747.68 2.847a1 1 0 0 1 .8-.999l4-.8a1 1 0 0 1 1.1.555L8.839 6.1a2 2 0 0 1-.635 2.5l-.465.355a1 1 0 0 0-.303 1.213 11.5 11.5 0 0 0 6.396 6.4"/>',
    'buscar'         => '<circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/>',
    'cheq-doble'     => '<path d="M18 6 7 17l-5-5"/><path d="m22 10-7.5 7.5L13 16"/>',
    'x'              => '<path d="M18 6 6 18"/><path d="m6 6 12 12"/>',
    'usuario'        => '<path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>',
    'chevron-abajo'  => '<path d="m6 9 6 6 6-6"/>',
    'salir'          => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" x2="9" y1="12" y2="12"/>',
    'llave'          => '<path d="m15.5 7.5 2.3 2.3a1 1 0 0 0 1.4 0l2.1-2.1a1 1 0 0 0 0-1.4L19 4"/><path d="m21 2-9.6 9.6"/><circle cx="7.5" cy="15.5" r="5.5"/>',
    'lapiz'          => '<path d="M21.174 6.812a1 1 0 0 0-3.986-3.987L3.842 16.174a2 2 0 0 0-.5.83l-1.321 4.352a.5.5 0 0 0 .623.622l4.353-1.32a2 2 0 0 0 .83-.497z"/><path d="m15 5 4 4"/>',
    'instagram'      => '<rect width="20" height="20" x="2" y="2" rx="5" ry="5"/><path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z"/><line x1="17.5" x2="17.51" y1="6.5" y2="6.5"/>',
    'facebook'       => '<path fill="currentColor" stroke="none" d="M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h2.5l1-4H14V7a1 1 0 0 1 1-1h3z"/>',
    'tarjeta'        => '<rect width="20" height="14" x="2" y="5" rx="2"/><line x1="2" x2="22" y1="10" y2="10"/>',
    'auriculares'    => '<path d="M3 14h3a2 2 0 0 1 2 2v3a2 2 0 0 1-2 2H4a1 1 0 0 1-1-1v-6a9 9 0 1 1 18 0v6a1 1 0 0 1-1 1h-2a2 2 0 0 1-2-2v-3a2 2 0 0 1 2-2h3"/>',
    'repetir'        => '<path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/>',
    'rayo'           => '<path d="M4 14a1 1 0 0 1-.78-1.63l9.9-10.2a.5.5 0 0 1 .86.46l-1.92 6.02A1 1 0 0 0 13 10h7a1 1 0 0 1 .78 1.63l-9.9 10.2a.5.5 0 0 1-.86-.46l1.92-6.02A1 1 0 0 0 11 14z"/>',
    'movil'          => '<rect width="14" height="20" x="5" y="2" rx="2" ry="2"/><path d="M12 18h.01"/>',
    'cohete'         => '<path d="M4.5 16.5c-1.5 1.26-2 5-2 5s3.74-.5 5-2c.71-.84.7-2.13-.09-2.91a2.18 2.18 0 0 0-2.91-.09z"/><path d="m12 15-3-3a22 22 0 0 1 2-3.95A12.88 12.88 0 0 1 22 2c0 2.72-.78 7.5-6 11a22.35 22.35 0 0 1-4 2z"/><path d="M9 12H4s.55-3.03 2-4c1.62-1.08 5 0 5 0"/><path d="M12 15v5s3.03-.55 4-2c1.08-1.62 0-5 0-5"/>',
    'bicicleta'      => '<circle cx="18.5" cy="17.5" r="3.5"/><circle cx="5.5" cy="17.5" r="3.5"/><circle cx="15" cy="5" r="1"/><path d="M12 17.5V14l-3-3 4-3 2 3h2"/>',
    'check'          => '<path d="M20 6 9 17l-5-5"/>',
    'correo'         => '<rect width="20" height="16" x="2" y="4" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/>',
    'ojo'            => '<path d="M2.062 12.348a1 1 0 0 1 0-.696 10.75 10.75 0 0 1 19.876 0 1 1 0 0 1 0 .696 10.75 10.75 0 0 1-19.876 0"/><circle cx="12" cy="12" r="3"/>',
    'ojo-tachado'    => '<path d="M10.733 5.076a10.744 10.744 0 0 1 11.205 6.575 1 1 0 0 1 0 .696 10.747 10.747 0 0 1-1.444 2.49"/><path d="M14.084 14.158a3 3 0 0 1-4.242-4.242"/><path d="M17.479 17.499a10.75 10.75 0 0 1-15.417-5.151 1 1 0 0 1 0-.696 10.75 10.75 0 0 1 4.446-5.143"/><path d="m2 2 20 20"/>',
    'pregunta'       => '<circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><path d="M12 17h.01"/>',
    'documento'      => '<path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/><path d="M14 2v4a2 2 0 0 0 2 2h4"/><path d="M10 9H8"/><path d="M16 13H8"/><path d="M16 17H8"/>',
    'cuadricula'     => '<rect width="7" height="7" x="3" y="3" rx="1"/><rect width="7" height="7" x="14" y="3" rx="1"/><rect width="7" height="7" x="14" y="14" rx="1"/><rect width="7" height="7" x="3" y="14" rx="1"/>',
    'botella'        => '<path d="M9.5 2h5"/><path d="M10 2v4.6c0 .7-.3 1.3-.8 1.8C7.4 9.9 6 11.3 6 13.5V20a2 2 0 0 0 2 2h8a2 2 0 0 0 2-2v-6.5c0-2.2-1.4-3.6-3.2-5.1-.5-.5-.8-1.1-.8-1.8V2"/><path d="M6 14.5h12"/>',
    'bolsa-snack'    => '<path d="M4.5 3h15v3.5h-15z"/><path d="M5.5 6.5 4.8 21h14.4l-.7-14.5"/><circle cx="12" cy="13.5" r="3"/>',
    'cajetilla'      => '<path d="M8 7V3"/><path d="M12 7V2"/><path d="M16 7V3"/><rect width="15" height="15" x="4.5" y="7" rx="2"/><path d="M4.5 12h15"/>',
    'caramelo'       => '<circle cx="12" cy="12" r="5"/><path d="M7.4 9.3 2.5 6.5v11l4.9-2.8"/><path d="m16.6 9.3 4.9-2.8v11l-4.9-2.8"/>',
    'leche'          => '<path d="m4.5 9 3-6h9l3 6"/><path d="M4.5 9h15v11a2 2 0 0 1-2 2h-11a2 2 0 0 1-2-2z"/><path d="M9 15.5h6"/>',
    'spray'          => '<rect width="11" height="12" x="3.5" y="10" rx="2"/><path d="M6.5 10V6.5h8"/><path d="M6.5 6.5V3h5"/><path d="M18 4.5h.01"/><path d="M21 6.5h.01"/><path d="M18.5 8.5h.01"/>',
    'higiene'        => '<rect width="12" height="13" x="6" y="9" rx="2"/><path d="M10 9V6h4v3"/><path d="M12 6V3h5"/><path d="M6 14h12"/>',
    'puntos'         => '<circle cx="12" cy="12" r="10"/><path d="M8 12h.01"/><path d="M12 12h.01"/><path d="M16 12h.01"/>',
    'canasta'        => '<path d="m15 11-1 9"/><path d="m19 11-4-7"/><path d="M2 11h20"/><path d="m3.5 11 1.6 7.4a2 2 0 0 0 2 1.6h9.8a2 2 0 0 0 2-1.6l1.7-7.4"/><path d="M4.5 15.5h15"/><path d="m5 11 4-7"/><path d="m9 11 1 9"/>',
    'bolsa'          => '<path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4Z"/><path d="M3 6h18"/><path d="M16 10a4 4 0 0 1-8 0"/>',
  ];

  $contenido = $trazos[$nombre] ?? '';
  $claseAttr = $clase !== '' ? ' ' . htmlspecialchars($clase) : '';

  return '<svg xmlns="http://www.w3.org/2000/svg" width="' . $tam . '" height="' . $tam . '" viewBox="0 0 24 24" '
       . 'fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" '
       . 'class="icono-svg' . $claseAttr . '" aria-hidden="true">' . $contenido . '</svg>';
}

/**
 * Ícono SVG de una categoría (círculos de Inicio, sidebar de Productos y
 * editor de Compra Semanal). Siempre es un ícono lineal que se elige por el
 * slug de la categoría: primero por palabra clave (bebida, snack, cigar...) y,
 * si no coincide ninguna, el ícono genérico de "paquete". No usa imágenes
 * ni el emoji guardado en la base de datos.
 */
function iconoCategoria(array $categoria, int $tam = 20): string {
  $slug = strtolower((string) ($categoria['slug'] ?? ''));

  // palabra clave dentro del slug => nombre del ícono
  $porPalabra = [
    'bebida'   => 'botella',
    'snack'    => 'bolsa-snack',
    'cigar'    => 'cajetilla',
    'recarga'  => 'movil',
    'golosin'  => 'caramelo',
    'caramel'  => 'caramelo',
    'lacteo'   => 'leche',
    'limpieza' => 'spray',
    'higiene'  => 'higiene',
    'perfume'  => 'higiene',
    'almacen'  => 'canasta',
    'otro'     => 'puntos',
  ];
  foreach ($porPalabra as $palabra => $nombreIcono) {
    if ($slug !== '' && str_contains($slug, $palabra)) {
      return icono($nombreIcono, '', $tam);
    }
  }
  return icono('paquete', '', $tam);
}

/**
 * Logos de marca para "continuar con Google/Facebook". Van con su color
 * oficial (no heredan currentColor como el resto de los íconos), porque
 * son isotipos reconocibles y no íconos lineales del sistema.
 */
function logoGoogle(int $tam = 18): string {
  return '<svg xmlns="http://www.w3.org/2000/svg" width="' . $tam . '" height="' . $tam . '" viewBox="0 0 24 24" class="icono-svg" aria-hidden="true">'
    . '<path fill="#4285F4" d="M23.52 12.27c0-.85-.08-1.67-.22-2.45H12v4.64h6.47a5.54 5.54 0 0 1-2.4 3.63v3h3.88c2.27-2.09 3.57-5.17 3.57-8.82Z"/>'
    . '<path fill="#34A853" d="M12 24c3.24 0 5.95-1.07 7.94-2.9l-3.88-3.02c-1.08.72-2.45 1.15-4.06 1.15-3.13 0-5.78-2.11-6.73-4.96H1.26v3.12A12 12 0 0 0 12 24Z"/>'
    . '<path fill="#FBBC05" d="M5.27 14.27a7.2 7.2 0 0 1 0-4.54V6.61H1.26a12 12 0 0 0 0 10.78z"/>'
    . '<path fill="#EA4335" d="M12 4.75c1.77 0 3.35.61 4.6 1.8l3.44-3.44C17.94 1.19 15.24 0 12 0 7.31 0 3.26 2.69 1.26 6.61l4.01 3.12C6.22 6.86 8.87 4.75 12 4.75Z"/>'
    . '</svg>';
}
function logoFacebook(int $tam = 18): string {
  return '<svg xmlns="http://www.w3.org/2000/svg" width="' . $tam . '" height="' . $tam . '" viewBox="0 0 24 24" class="icono-svg" aria-hidden="true">'
    . '<path fill="#1877F2" d="M24 12.07C24 5.68 18.63.5 12 .5S0 5.68 0 12.07c0 5.77 4.39 10.56 10.13 11.43v-8.09H7.08v-3.34h3.05V9.41c0-2.99 1.79-4.64 4.53-4.64 1.31 0 2.68.23 2.68.23v2.93h-1.51c-1.49 0-1.95.92-1.95 1.86v2.24h3.32l-.53 3.34h-2.79v8.09C19.61 22.63 24 17.84 24 12.07Z"/>'
    . '</svg>';
}

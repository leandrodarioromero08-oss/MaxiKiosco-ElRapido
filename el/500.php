<?php
/**
 * Página 500 (la usa Apache vía ErrorDocument, ver .htaccess).
 * Es autónoma: no consulta la base de datos, así que funciona aunque MySQL esté caído.
 */
require_once __DIR__ . '/includes/config.php';

responderError(
  500,
  'Algo salió mal de nuestro lado',
  'Tuvimos un problema al procesar tu solicitud. Probá de nuevo en un momento.'
);

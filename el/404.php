<?php
/**
 * Página 404 (la usa Apache vía ErrorDocument, ver .htaccess).
 * Es autónoma: no consulta la base de datos, así que funciona aunque MySQL esté caído.
 */
require_once __DIR__ . '/includes/config.php';

responderError(
  404,
  'No encontramos esta página',
  'El enlace puede estar mal escrito o la página ya no existe. Volvé al inicio o mirá nuestros productos.'
);

<?php
/**
 * Conexión a la base de datos para el panel de administración.
 * Reutiliza la misma conexión PDO (obtenerConexion()) que ya usa
 * el sitio público, para no duplicar usuario/contraseña en dos lados.
 * Si en algún momento cambiás host/usuario/contraseña, hacelo en
 * un solo lugar: includes/db.php (en la raíz del proyecto).
 */

require_once __DIR__ . '/../../includes/db.php';

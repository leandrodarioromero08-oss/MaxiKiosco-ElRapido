<?php
/**
 * Genera el SQL para crear o cambiar la contraseña de un administrador.
 * Solo se puede ejecutar por línea de comandos (nunca desde el navegador):
 *
 *   php database/generar_hash.php correo@dominio.com "MiClaveLarga123" "Nombre"
 *
 * Copiá el SQL que imprime y pegalo en phpMyAdmin.
 */
if (PHP_SAPI !== 'cli') {
  http_response_code(404);
  exit;
}
if ($argc < 3) {
  fwrite(STDERR, "Uso: php database/generar_hash.php correo contraseña [nombre]\n");
  exit(1);
}
[$_, $email, $pass] = $argv;
$nombre = $argv[3] ?? 'Administrador';
if (strlen($pass) < 12) {
  fwrite(STDERR, "La contraseña del admin debe tener al menos 12 caracteres.\n");
  exit(1);
}
$hash = password_hash($pass, PASSWORD_DEFAULT);
$q = fn(string $s) => "'" . str_replace(["\\", "'"], ["\\\\", "''"], $s) . "'";

echo "-- Crear (si no existe):\n";
echo "INSERT INTO administradores (nombre, email, password_hash) VALUES (" . $q($nombre) . ", " . $q($email) . ", " . $q($hash) . ");\n\n";
echo "-- ...o cambiar la contraseña de uno existente:\n";
echo "UPDATE administradores SET password_hash = " . $q($hash) . " WHERE email = " . $q($email) . ";\n";

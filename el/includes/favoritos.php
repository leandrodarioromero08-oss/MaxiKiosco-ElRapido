<?php
/**
 * FAVORITOS
 * Productos que cada cliente marca con el corazón ♡. Se guardan en la tabla
 * "favoritos" (usuario_id + producto_id), así que viajan con la cuenta: se ven
 * igual desde cualquier dispositivo. Los usan productos.php, favorito_toggle.php
 * y la sección Favoritos de mi-cuenta.php.
 *
 * Si la tabla todavía no existe (falta correr database/migracion_004_favoritos.sql)
 * nada se rompe: las funciones de lectura devuelven vacío y dejan el aviso en el log.
 */

/** IDs de los productos favoritos del usuario, como [id => true] (para consultar con isset) */
function obtenerIdsFavoritos(PDO $pdo, int $usuarioId): array {
  if ($usuarioId <= 0) {
    return [];
  }
  try {
    $stmt = $pdo->prepare('SELECT producto_id FROM favoritos WHERE usuario_id = :u');
    $stmt->execute([':u' => $usuarioId]);
    return array_fill_keys(array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN)), true);
  } catch (Throwable $e) {
    error_log('[el-rapido] favoritos: ' . $e->getMessage());
    return [];
  }
}

/**
 * Marca (o desmarca) un producto como favorito. Es idempotente: marcar dos veces
 * deja una sola fila. Devuelve false si se quiso marcar un producto que no existe
 * o ya no está activo (desmarcar siempre se permite).
 */
function establecerFavorito(PDO $pdo, int $usuarioId, int $productoId, bool $activo): bool {
  if ($usuarioId <= 0 || $productoId <= 0) {
    return false;
  }
  if (!$activo) {
    $stmt = $pdo->prepare('DELETE FROM favoritos WHERE usuario_id = :u AND producto_id = :p');
    $stmt->execute([':u' => $usuarioId, ':p' => $productoId]);
    return true;
  }
  $stmt = $pdo->prepare('SELECT 1 FROM productos WHERE id = :p AND activo = 1');
  $stmt->execute([':p' => $productoId]);
  if (!$stmt->fetchColumn()) {
    return false;
  }
  $stmt = $pdo->prepare('INSERT IGNORE INTO favoritos (usuario_id, producto_id) VALUES (:u, :p)');
  $stmt->execute([':u' => $usuarioId, ':p' => $productoId]);
  return true;
}

/** Favoritos del usuario con los datos del producto (solo los activos), los más nuevos primero */
function obtenerProductosFavoritos(PDO $pdo, int $usuarioId): array {
  try {
    $stmt = $pdo->prepare('
      SELECT p.id, p.nombre, p.precio, p.descuento_pct, p.emoji, p.imagen, p.stock
      FROM favoritos f
      JOIN productos p ON p.id = f.producto_id
      WHERE f.usuario_id = :u AND p.activo = 1
      ORDER BY f.creado_en DESC, p.nombre ASC
    ');
    $stmt->execute([':u' => $usuarioId]);
    return $stmt->fetchAll();
  } catch (Throwable $e) {
    error_log('[el-rapido] favoritos: ' . $e->getMessage());
    return [];
  }
}

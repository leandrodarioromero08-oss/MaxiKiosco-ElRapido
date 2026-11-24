<?php
/**
 * Autocompletado de productos para el paso 2 del wizard de promociones.
 * Se llama por fetch() desde promociones.php: ?q=coca
 * Devuelve JSON: [{id, nombre, precio, emoji, imagen}, ...]
 */
require_once '../Config/funciones.php';
require_once '../Config/auth.php';
requerirAdmin(true); // $esApi (posicional: compatible con PHP 7.x)
require_once '../../includes/promociones_funciones.php';

header('Content-Type: application/json; charset=utf-8');

$pdo = obtenerConexion();
$q = trim($_GET['q'] ?? '');

if ($q === '') {
  echo json_encode([]);
  exit;
}

$productos = buscarProductosParaPromo($pdo, $q, 8);
echo json_encode($productos);

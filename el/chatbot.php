<?php
/* ============================================================
   CHATBOT.PHP — endpoint del asistente (responde JSON)
   Lo llama assets/js/chatbot.js con un POST { mensaje }.
   Solo LEE datos (productos, promos y el último pedido del
   usuario que tiene la sesión iniciada): no modifica nada.
   ============================================================ */

require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/funciones.php';
require_once 'includes/chatbot_logica.php';

// Los avisos de PHP romperían el JSON, así que acá nunca se muestran.
ini_set('display_errors', 0);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  echo json_encode(['texto' => 'Método no permitido.']);
  exit;
}

$mensaje = mb_substr(trim((string) ($_POST['mensaje'] ?? '')), 0, 200);

// Freno simple contra abuso: máximo 20 mensajes por minuto por sesión.
$ahora  = time();
$marcas = array_values(array_filter($_SESSION['chatbot_marcas'] ?? [], function ($m) use ($ahora) {
  return $m > $ahora - 60;
}));
$demasiados = count($marcas) >= 20;
if (!$demasiados) {
  $marcas[] = $ahora;
}
$_SESSION['chatbot_marcas'] = $marcas;

// Leemos el usuario y soltamos la sesión enseguida para no bloquear otras páginas.
$usuario = usuarioLogueado() ? $_SESSION['usuario'] : null; // usuarioLogueado() también revalida la sesión contra la base
session_write_close();

if ($demasiados) {
  echo json_encode(chatbotResp('Estás escribiendo muy rápido. Esperá unos segundos y probá de nuevo.'), JSON_UNESCAPED_UNICODE);
  exit;
}

try {
  $respuesta = chatbotResponder(obtenerConexion(), $mensaje, $usuario);
} catch (Throwable $e) {
  $respuesta = chatbotResp(
    'Tuve un problema para responderte. Probá de nuevo en un momento o escribinos por WhatsApp.',
    [chatbotEnlaceWsp()]
  );
}

echo json_encode($respuesta, JSON_UNESCAPED_UNICODE);

<?php
require_once '../Config/funciones.php';
require_once '../Config/auth.php';
requerirAdmin();
csrfExigir(); // protección CSRF: rechaza cualquier POST sin token válido
$pdo = obtenerConexion();

/*
 * Cambio de contraseña del administrador logueado.
 * Antes la única forma era editar el hash directo en la base de datos.
 */
$error = '';
$ok = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $actual   = (string) ($_POST['password_actual'] ?? '');
  $nueva    = (string) ($_POST['password_nueva'] ?? '');
  $repetida = (string) ($_POST['password_repetida'] ?? '');
  $adminId  = (int) adminActual()['id'];
  $emailAdmin = (string) adminActual()['email'];

  $stmt = $pdo->prepare('SELECT password_hash FROM administradores WHERE id = :id');
  $stmt->execute([':id' => $adminId]);
  $hashActual = $stmt->fetchColumn();

  if (loginBloqueado($pdo, 'admin', $emailAdmin)) {
    $error = 'Demasiados intentos fallidos. Esperá ' . LOGIN_VENTANA_MIN . ' minutos y probá de nuevo.';
  } elseif (!$hashActual || !password_verify($actual, $hashActual)) {
    registrarLoginFallido($pdo, 'admin', $emailAdmin);
    $error = 'La contraseña actual no es correcta.';
  } elseif (strlen($nueva) < 12) {
    $error = 'La contraseña nueva debe tener al menos 12 caracteres.';
  } elseif (strlen($nueva) > 72) {
    $error = 'La contraseña nueva es demasiado larga (máximo 72 caracteres).';
  } elseif (!hash_equals($nueva, $repetida)) {
    $error = 'La contraseña nueva y su repetición no coinciden.';
  } elseif (hash_equals($actual, $nueva)) {
    $error = 'La contraseña nueva tiene que ser distinta de la actual.';
  } else {
    $pdo->prepare('UPDATE administradores SET password_hash = :h WHERE id = :id')
        ->execute([':h' => password_hash($nueva, PASSWORD_DEFAULT), ':id' => $adminId]);
    limpiarLoginFallidos($pdo, 'admin', $emailAdmin);
    session_regenerate_id(true); // nuevo ID de sesión tras cambiar la credencial
    $ok = true;
  }
}

$CSSpage       = 'configuracion.css';
$tituloPagina  = 'Cambiar contraseña';
$seccionActiva = 'configuracion';
include('../Template/sidebar.php');
include('../Template/topbar.php');
?>

    <main class="page-content">
      <h1 class="page-title">Cambiar contraseña</h1>
      <p class="page-subtitle">Usá una contraseña de al menos 12 caracteres que no uses en otros sitios</p>

      <?php if ($ok): ?>
        <div class="alerta alerta-exito"><?= icono('check-circulo', '', 18) ?> <span>Tu contraseña se actualizó correctamente.</span></div>
      <?php elseif ($error): ?>
        <div class="alerta alerta-error"><?= icono('alerta', '', 18) ?> <span><?= htmlspecialchars($error) ?></span></div>
      <?php endif; ?>

      <form method="POST" action="cambiar_password.php" autocomplete="off" style="max-width:420px;display:grid;gap:14px;margin-top:18px;">
        <?= csrfCampo() ?>
        <label>Contraseña actual
          <input type="password" name="password_actual" required style="width:100%;padding:10px;">
        </label>
        <label>Contraseña nueva
          <input type="password" name="password_nueva" required minlength="12" maxlength="72" style="width:100%;padding:10px;">
        </label>
        <label>Repetí la contraseña nueva
          <input type="password" name="password_repetida" required minlength="12" maxlength="72" style="width:100%;padding:10px;">
        </label>
        <button type="submit" class="btn-rojo" style="padding:11px 18px; border:none;">Guardar contraseña</button>
      </form>
    </main>

  </div>

</div>

</body>
</html>

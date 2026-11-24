<?php
$pageCss = 'login.css';
$pageTitle = 'Nueva contraseña';
$pageDescription = 'Elegí una contraseña nueva para tu cuenta de El Rápido.';
$pageRobots = 'noindex,follow'; // pantalla de acceso: sin valor para buscadores
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/funciones.php';
require_once 'includes/recuperacion.php';
csrfExigir(); // protección CSRF: rechaza cualquier POST sin token válido

// El enlace trae el token en la URL; al enviar el formulario viaja en un campo oculto.
header('Cache-Control: no-store');
$token = $_SERVER['REQUEST_METHOD'] === 'POST' ? postTexto('token') : (is_string($_GET['token'] ?? null) ? trim($_GET['token']) : '');

$pdo = obtenerConexion();
$recuperacion = buscarRecuperacionValida($pdo, $token);
$error = '';

if ($recuperacion && $_SERVER['REQUEST_METHOD'] === 'POST') {
  $nueva    = postRaw('password');
  $repetida = postRaw('password_repetida');

  if (($errPw = validarPasswordNueva($nueva, $recuperacion['email'])) !== null) {
    $error = $errPw;
  } elseif (!hash_equals($nueva, $repetida)) {
    $error = 'La contraseña nueva y su repetición no coinciden.';
  } else {
    try {
      $pdo->beginTransaction();
      // Releemos el token bloqueándolo: dos envíos simultáneos no pueden usarlo dos veces.
      $fila = buscarRecuperacionValida($pdo, $token, true);
      if (!$fila) {
        $pdo->rollBack();
        $recuperacion = null;
      } else {
        $pdo->prepare('UPDATE usuarios SET password_hash = :h, password_cambiada_en = NOW() WHERE id = :id')
            ->execute([':h' => password_hash($nueva, PASSWORD_DEFAULT), ':id' => $fila['usuario_id']]);
        $pdo->prepare('UPDATE password_resets SET usado_en = NOW() WHERE usuario_id = :u AND usado_en IS NULL')
            ->execute([':u' => $fila['usuario_id']]);
        $pdo->commit();

        limpiarLoginFallidos($pdo, 'sitio', $fila['email']); // por si la cuenta había quedado bloqueada
        flashSet('ok', 'Tu contraseña se restableció. Ya podés iniciar sesión con la nueva.');
        header('Location: login.php');
        exit;
      }
    } catch (Throwable $e) {
      if ($pdo->inTransaction()) {
        $pdo->rollBack();
      }
      error_log('[el-rapido] restablecer contraseña: ' . $e->getMessage());
      $error = 'No pudimos restablecer tu contraseña en este momento. Probá de nuevo en unos minutos.';
    }
  }
}

include 'includes/header.php';
require_once 'includes/iconos.php';
?>

<section class="auth-section">
  <div class="auth-inner">

    <div class="auth-form-col">
      <h1 class="auth-titulo">NUEVA CONTRASEÑA</h1>

      <?php if (!$recuperacion): ?>
        <div class="auth-error" role="alert"><?= icono('alerta', '', 16) ?> Este enlace no es válido o ya venció.</div>
        <p class="auth-ayuda">Los enlaces valen <?= RECUPERACION_VALIDEZ_MIN ?> minutos y se pueden usar una sola vez. Pedí uno nuevo para continuar.</p>
        <p class="auth-alt"><a href="recuperar.php">Pedir un enlace nuevo</a></p>
      <?php else: ?>
        <p class="auth-subtitulo">Elegí una contraseña de al menos <?= PASSWORD_MIN_CLIENTE ?> caracteres</p>

        <?php if ($error): ?>
          <div class="auth-error" role="alert"><?= icono('alerta', '', 16) ?> <?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form class="auth-form" method="POST" action="restablecer.php" autocomplete="off">
<?= csrfCampo() ?>
          <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">

          <div class="auth-campo">
            <label for="password">Contraseña nueva</label>
            <div class="auth-input-wrap">
              <span class="auth-input-icono"><?= icono('candado', '', 16) ?></span>
              <input type="password" id="password" name="password" placeholder="Mínimo <?= PASSWORD_MIN_CLIENTE ?> caracteres" minlength="<?= PASSWORD_MIN_CLIENTE ?>" maxlength="72" autocomplete="new-password" required>
              <button type="button" class="auth-toggle-pass" aria-label="Mostrar contraseña" aria-pressed="false" onclick="togglePassword(this)"><?= icono('ojo', 'icono-ojo', 16) ?></button>
            </div>
          </div>

          <div class="auth-campo">
            <label for="password_repetida">Repetí la contraseña nueva</label>
            <div class="auth-input-wrap">
              <span class="auth-input-icono"><?= icono('candado', '', 16) ?></span>
              <input type="password" id="password_repetida" name="password_repetida" placeholder="Repetí la contraseña" minlength="<?= PASSWORD_MIN_CLIENTE ?>" maxlength="72" autocomplete="new-password" required>
            </div>
          </div>

          <button type="submit" class="btn-auth-principal">GUARDAR CONTRASEÑA</button>
        </form>
      <?php endif; ?>
    </div>

    <div class="auth-imagen-col">
      <?php renderFotoHeroCiclo(); ?>
    </div>

  </div>
</section>

<script src="<?= htmlspecialchars(assetUrl('assets/js/auth.js')) ?>" defer></script>

<?php include 'includes/footer.php'; ?>

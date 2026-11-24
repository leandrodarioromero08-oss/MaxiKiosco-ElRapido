<?php
$pageCss = 'login.css';
$pageTitle = 'Recuperar contraseña';
$pageDescription = 'Pedí un enlace para elegir una contraseña nueva de tu cuenta de El Rápido.';
$pageRobots = 'noindex,follow'; // pantalla de acceso: sin valor para buscadores
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/funciones.php';
require_once 'includes/recuperacion.php';
csrfExigir(); // protección CSRF: rechaza cualquier POST sin token válido

redirigirSiYaLogueado('mi-cuenta.php?seccion=seguridad');

$error     = '';
$enviado   = false;
$enlaceDev = null; // solo se muestra en desarrollo (localhost)

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $email = postTexto('email');

  if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 150) {
    $error = 'Ingresá un correo electrónico válido.';
  } else {
    $pdo = obtenerConexion();

    if (ipLimiteExcedido($pdo, 'recuperar', 5, 60)) {
      $error = 'Hiciste demasiados pedidos seguidos. Probá de nuevo en una hora.';
    } else {
      ipRegistrarAccion($pdo, 'recuperar');
      $enlaceDev = solicitarRecuperacion($pdo, $email);
      $enviado = true; // misma respuesta exista o no la cuenta
    }
  }
}

include 'includes/header.php';
require_once 'includes/iconos.php';
?>

<section class="auth-section">
  <div class="auth-inner">

    <div class="auth-form-col">
      <h1 class="auth-titulo">RECUPERAR CONTRASEÑA</h1>
      <p class="auth-subtitulo">Te enviamos un enlace para elegir una contraseña nueva</p>

      <?php if ($error): ?>
        <div class="auth-error" role="alert"><?= icono('alerta', '', 16) ?> <?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <?php if ($enviado): ?>
        <div class="auth-ok" role="status">Si ese correo tiene una cuenta, te enviamos un enlace para restablecer la contraseña. Revisá también la carpeta de spam. El enlace vale <?= RECUPERACION_VALIDEZ_MIN ?> minutos.</div>
        <?php if (ENV === 'development' && $enlaceDev): ?>
          <p class="auth-ayuda"><strong>Solo desarrollo:</strong> <a href="<?= htmlspecialchars($enlaceDev) ?>">abrir el enlace de restablecimiento</a> (en el servidor real solo llega por correo).</p>
        <?php endif; ?>
        <p class="auth-alt"><a href="login.php">Volver a iniciar sesión</a></p>
      <?php else: ?>
        <form class="auth-form" method="POST" action="recuperar.php">
<?= csrfCampo() ?>
          <div class="auth-campo">
            <label for="email">Correo electrónico</label>
            <div class="auth-input-wrap">
              <span class="auth-input-icono"><?= icono('correo', '', 16) ?></span>
              <input type="email" id="email" name="email" placeholder="Ingresá el correo de tu cuenta" autocomplete="username" maxlength="150" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
            </div>
          </div>

          <button type="submit" class="btn-auth-principal">ENVIAR ENLACE</button>

          <p class="auth-alt">¿Te acordaste? <a href="login.php">Iniciar sesión</a></p>
        </form>
      <?php endif; ?>
    </div>

    <div class="auth-imagen-col">
      <?php renderFotoHeroCiclo(); ?>
    </div>

  </div>
</section>

<?php include 'includes/footer.php'; ?>

<?php
$pageCss = 'login.css';
$pageTitle = 'Iniciar sesión';
$pageDescription = 'Ingresá a tu cuenta de El Rápido para hacer pedidos, ver tus promociones y repetir tus compras.';
$pageRobots = 'noindex,follow'; // pantalla de acceso: sin valor para buscadores
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/funciones.php';
csrfExigir(); // protección CSRF: rechaza cualquier POST sin token válido

// Si viene de una página que requiere login (ej: el carrito), volvemos ahí al terminar
$volver = obtenerVolverSeguro('index.php');
redirigirSiYaLogueado($volver);

// El procesamiento del POST (y su eventual header('Location: ...')) tiene
// que ir ANTES de incluir includes/header.php: ese archivo ya imprime HTML,
// y una vez que se mandó output no se pueden mandar más headers HTTP.
$error = '';
$mensajesFlash = flashConsumir(); // por ejemplo: "Tu contraseña se restableció"

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $email    = postTexto('email');
  $password = postRaw('password'); // sin trim(): ver postRaw() en includes/seguridad.php

  if ($email === '' || $password === '') {
    $error = 'Completá tu correo electrónico y tu contraseña.';
  } else {
    $pdo = obtenerConexion();

    if (loginBloqueado($pdo, 'sitio', $email)) {
      $error = 'Demasiados intentos fallidos. Esperá ' . LOGIN_VENTANA_MIN . ' minutos y probá de nuevo.';
    } else {
      $stmt = $pdo->prepare('SELECT id, nombre, email, password_hash FROM usuarios WHERE email = :email LIMIT 1');
      $stmt->execute(['email' => $email]);
      $usuario = $stmt->fetch();

      // Si el correo no existe se hace igual un cálculo de hash descartable, para que
      // la respuesta tarde lo mismo y no se pueda adivinar qué correos existen.
      $passwordOk = verificarPassword($password, $usuario['password_hash'] ?? null);

      if ($usuario && $passwordOk) {
        limpiarLoginFallidos($pdo, 'sitio', $email);
        // Si el costo del algoritmo subió desde que se guardó el hash, lo actualizamos.
        if (password_needs_rehash($usuario['password_hash'], PASSWORD_DEFAULT)) {
          try {
            $pdo->prepare('UPDATE usuarios SET password_hash = :h WHERE id = :id')
                ->execute([':h' => password_hash($password, PASSWORD_DEFAULT), ':id' => $usuario['id']]);
          } catch (Throwable $e) {
            error_log('[el-rapido] rehash de contraseña: ' . $e->getMessage());
          }
        }
        iniciarSesionUsuario($usuario);
        header('Location: ' . $volver);
        exit;
      }

      registrarLoginFallido($pdo, 'sitio', $email);
      $error = 'Correo o contraseña incorrectos.';
    }
  }
}

include 'includes/header.php';
require_once 'includes/iconos.php';
?>

<section class="auth-section">
  <div class="auth-inner">

    <div class="auth-form-col">
      <h1 class="auth-titulo">INICIAR SESIÓN</h1>
      <p class="auth-subtitulo">Ingresá a tu cuenta para empezar a comprar</p>

      <?php foreach ($mensajesFlash as $m): ?>
        <div class="<?= $m['tipo'] === 'ok' ? 'auth-ok' : 'auth-error' ?>" role="<?= $m['tipo'] === 'ok' ? 'status' : 'alert' ?>"><?= htmlspecialchars($m['mensaje']) ?></div>
      <?php endforeach; ?>

      <?php if ($error): ?>
        <div class="auth-error" role="alert"><?= icono('alerta', '', 16) ?> <?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <form class="auth-form" method="POST" action="login.php">
<?= csrfCampo() ?>
        <input type="hidden" name="volver" value="<?= htmlspecialchars($volver) ?>">
        <div class="auth-campo">
          <label for="email">Correo electrónico</label>
          <div class="auth-input-wrap">
            <span class="auth-input-icono"><?= icono('correo', '', 16) ?></span>
            <input type="email" id="email" name="email" placeholder="Ingresá tu correo electrónico" autocomplete="username" maxlength="150" required>
          </div>
        </div>

        <div class="auth-campo">
          <label for="password">Contraseña</label>
          <div class="auth-input-wrap">
            <span class="auth-input-icono"><?= icono('candado', '', 16) ?></span>
            <input type="password" id="password" name="password" placeholder="Ingresá tu contraseña" autocomplete="current-password" required>
            <button type="button" class="auth-toggle-pass" aria-label="Mostrar contraseña" aria-pressed="false" onclick="togglePassword(this)"><?= icono('ojo', 'icono-ojo', 16) ?></button>
          </div>
        </div>

        <a href="recuperar.php" class="auth-olvide">¿Olvidaste tu contraseña?</a>

        <button type="submit" class="btn-auth-principal">INICIAR SESIÓN</button>

        <div class="auth-divisor"><span>o continuá con</span></div>

        <div class="auth-social">
          <button type="button" class="btn-social" disabled title="Próximamente">
            <span class="btn-social-icono"><?= logoGoogle(16) ?></span> Google <small class="btn-social-pronto">Próximamente</small>
          </button>
          <button type="button" class="btn-social" disabled title="Próximamente">
            <span class="btn-social-icono"><?= logoFacebook(16) ?></span> Facebook <small class="btn-social-pronto">Próximamente</small>
          </button>
        </div>

        <p class="auth-alt">¿No tenés una cuenta? <a href="registro.php">Crear cuenta</a></p>
      </form>
    </div>

    <div class="auth-imagen-col">
      <?php renderFotoHeroCiclo(); ?>
    </div>

  </div>

</section>

<script src="<?= htmlspecialchars(assetUrl('assets/js/auth.js')) ?>" defer></script>

<?php include 'includes/footer.php'; ?>

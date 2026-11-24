<?php
/**
 * LOGIN DEL PANEL DE ADMINISTRACIÓN
 * Es a admin/ lo que includes/header.php + login.php son al sitio
 * público, pero separado porque usa su propia tabla
 * (administradores) y su propia clave de sesión ($_SESSION['admin']).
 */
require_once __DIR__ . '/Config/auth.php';
require_once __DIR__ . '/Config/iconos.php';
csrfExigir(); // protección CSRF: rechaza cualquier POST sin token válido

// Si ya está logueado, no tiene sentido mostrarle el formulario de nuevo
if (adminLogueado()) {
  header('Location: Seccion/inicio.php');
  exit;
}

// "volver" viene de requerirAdmin() cuando alguien intentó entrar a una
// página del panel sin sesión. Solo aceptamos rutas DENTRO de Seccion/,
// para no correr el riesgo de un open redirect.
$volverParam = $_POST['volver'] ?? $_GET['volver'] ?? '';
// esRutaLocalSegura() solo deja pasar "archivo.php?query" (sin "/", "\\", "..", http://, etc.)
$volver = esRutaLocalSegura($volverParam) ? $volverParam : 'inicio.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $email    = postTexto('email');
  $password = postRaw('password'); // sin trim(): igual que en cambiar_password.php

  if ($email === '' || $password === '') {
    $error = 'Completá el correo y la contraseña.';
  } else {
    $pdo = obtenerConexion();

    if (loginBloqueado($pdo, 'admin', $email)) {
      $error = 'Demasiados intentos fallidos. Esperá ' . LOGIN_VENTANA_MIN . ' minutos y probá de nuevo.';
    } else {
      $stmt = $pdo->prepare('SELECT id, nombre, email, password_hash FROM administradores WHERE email = :email LIMIT 1');
      $stmt->execute(['email' => $email]);
      $admin = $stmt->fetch();

      // Verificación a tiempo constante aunque el correo no exista (ver login.php)
      $passwordOk = verificarPassword($password, $admin['password_hash'] ?? null);

      if ($admin && $passwordOk) {
        limpiarLoginFallidos($pdo, 'admin', $email);
        if (password_needs_rehash($admin['password_hash'], PASSWORD_DEFAULT)) {
          try {
            $pdo->prepare('UPDATE administradores SET password_hash = :h WHERE id = :id')
                ->execute([':h' => password_hash($password, PASSWORD_DEFAULT), ':id' => $admin['id']]);
          } catch (Throwable $e) {
            error_log('[el-rapido] rehash admin: ' . $e->getMessage());
          }
        }
        iniciarSesionAdmin($admin);
        header('Location: Seccion/' . $volver);
        exit;
      }

      registrarLoginFallido($pdo, 'admin', $email);
      $error = 'Correo o contraseña incorrectos.';
    }
  }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="robots" content="noindex, nofollow">
  <title>Iniciar sesión — El Rápido Admin</title>
  <link rel="icon" type="image/png" href="../assets/img/icono-el-rapido.png" />
  <link rel="apple-touch-icon" href="../assets/img/apple-touch-icon.png" />
  <link rel="manifest" href="manifest.webmanifest" />
  <meta name="theme-color" content="#1A1A1A" />
  <link rel="stylesheet" href="assets/CSS/global.css">
  <style>
    .login-admin-wrap {
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      background-color: var(--gris-fondo);
      padding: 20px;
    }
    .login-admin-card {
      background: var(--blanco);
      border-radius: var(--radio);
      box-shadow: var(--sombra);
      padding: 40px 36px;
      width: 100%;
      max-width: 380px;
    }
    .login-admin-logo {
      text-align: center;
      margin-bottom: 6px;
    }
    .login-admin-subtitulo {
      text-align: center;
      color: var(--gris-texto);
      font-size: 14px;
      margin-bottom: 28px;
    }
    .login-admin-campo {
      margin-bottom: 18px;
    }
    .login-admin-campo label {
      display: block;
      font-size: 13px;
      font-weight: 600;
      margin-bottom: 6px;
      color: var(--negro-texto);
    }
    .login-admin-campo input {
      width: 100%;
      padding: 11px 14px;
      border: 1px solid var(--gris-borde);
      border-radius: var(--radio);
      font-size: 14px;
      box-sizing: border-box;
    }
    .login-admin-campo input {
      transition: border-color 0.2s ease;
    }
    .login-admin-campo input:focus {
      outline: none;
      border-color: var(--rojo);
    }
    .login-admin-btn {
      width: 100%;
      padding: 12px;
      border: none;
      border-radius: var(--radio);
      background-color: var(--rojo);
      color: var(--blanco);
      font-weight: 700;
      font-size: 15px;
      cursor: pointer;
      margin-top: 6px;
      transition: background-color 0.2s ease, transform 0.2s ease;
    }
    .login-admin-btn:hover {
      background-color: var(--rojo-hover);
    }
    .login-admin-btn:active {
      transform: scale(0.97);
    }
    .login-admin-error {
      background: var(--rojo-claro);
      color: var(--rojo-hover);
      border-radius: var(--radio);
      padding: 10px 14px;
      font-size: 13px;
      margin-bottom: 18px;
      display: flex;
      align-items: center;
      gap: 8px;
    }
    .login-admin-error .icono-svg {
      flex-shrink: 0;
    }
    .login-admin-campo input:focus-visible,
    .login-admin-btn:focus-visible {
      outline: 2px solid var(--rojo);
      outline-offset: 2px;
    }
    @media (prefers-reduced-motion: reduce) {
      .login-admin-campo input, .login-admin-btn { transition: none; }
    }
  </style>
</head>
<body>

  <div class="login-admin-wrap">
    <div class="login-admin-card">
      <div class="login-admin-logo"><img src="../assets/img/logo-el-rapido-admin.png" alt="El Rápido — Administración" style="max-width:220px; height:auto;"></div>
      <p class="login-admin-subtitulo">Panel de administración</p>

      <?php if ($error): ?>
        <div class="login-admin-error"><?= icono('alerta', '', 16) ?> <span><?= htmlspecialchars($error) ?></span></div>
      <?php endif; ?>

      <form method="POST" action="login.php">
<?= csrfCampo() ?>
        <input type="hidden" name="volver" value="<?= htmlspecialchars($volver) ?>">

        <div class="login-admin-campo">
          <label for="email">Correo electrónico</label>
          <input type="email" id="email" name="email" placeholder="admin@elrapido.com" required autofocus>
        </div>

        <div class="login-admin-campo">
          <label for="password">Contraseña</label>
          <input type="password" id="password" name="password" placeholder="Ingresá tu contraseña" required>
        </div>

        <button type="submit" class="login-admin-btn">Iniciar sesión</button>
      </form>
    </div>
  </div>

<script src="../assets/js/pwa.js" defer></script>
</body>
</html>

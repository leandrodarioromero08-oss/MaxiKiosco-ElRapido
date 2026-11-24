<?php
$pageCss = 'login.css';
$pageTitle = 'Crear cuenta';
$pageDescription = 'Creá tu cuenta en El Rápido para hacer pedidos online con delivery o retiro en el local.';
$pageRobots = 'noindex,follow'; // pantalla de acceso: sin valor para buscadores
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/funciones.php';
csrfExigir(); // protección CSRF: rechaza cualquier POST sin token válido

redirigirSiYaLogueado('index.php');

// El procesamiento del POST (y su eventual header('Location: ...')) tiene
// que ir ANTES de incluir includes/header.php: ese archivo ya imprime HTML,
// y una vez que se mandó output no se pueden mandar más headers HTTP.
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $nombre   = postTexto('nombre');
  $email    = postTexto('email');
  $password = postRaw('password'); // sin trim(): ver postRaw() en includes/seguridad.php
  $telefono = postTexto('telefono');

  // La dirección es opcional, pero si se completa algún campo, tiene
  // que venir completa y válida (mismos criterios que en el checkout).
  $direccionCalle      = postTexto('direccion_calle');
  $direccionNumero     = postTexto('direccion_numero');
  $direccionBarrio     = postTexto('direccion_barrio');
  $direccionReferencia = postTexto('direccion_referencia');
  $tocoDireccion = $direccionCalle !== '' || $direccionNumero !== '' || $direccionBarrio !== '';

  if ($nombre === '' || $email === '' || $password === '' || $telefono === '') {
    $error = 'Completá todos los campos para crear tu cuenta.';
  } elseif (mb_strlen($nombre) > 100 || mb_strlen($direccionReferencia) > 255) {
    $error = 'El nombre (máx. 100 caracteres) o la referencia (máx. 255) son demasiado largos.';
  } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 150) {
    $error = 'Ingresá un correo electrónico válido.';
  } elseif (($errorPassword = validarPasswordNueva($password, $email)) !== null) {
    $error = $errorPassword;
  } elseif (!validarTelefono($telefono)) {
    $error = 'Ingresá un teléfono de contacto válido (solo números, espacios o guiones, entre 8 y 20 caracteres).';
  } elseif ($tocoDireccion && !validarTextoEntrega($direccionCalle, 2, 150)) {
    $error = 'Ingresá la calle de tu dirección de entrega (o dejá los tres campos vacíos si no querés guardar una).';
  } elseif ($tocoDireccion && !validarNumeroCalle($direccionNumero)) {
    $error = 'Ingresá el número de tu dirección (o "s/n" si no tiene).';
  } elseif ($tocoDireccion && !validarTextoEntrega($direccionBarrio, 2, 100)) {
    $error = 'Ingresá el barrio de tu dirección de entrega.';
  } else {
    $pdo = obtenerConexion();

    $stmt = $pdo->prepare('SELECT id FROM usuarios WHERE email = :email LIMIT 1');
    $stmt->execute(['email' => $email]);

    if (ipLimiteExcedido($pdo, 'registro', 5, 60)) {
      $error = 'Se crearon demasiadas cuentas desde tu conexión. Probá de nuevo en una hora.';
    } elseif ($stmt->fetch()) {
      $error = 'Ya existe una cuenta registrada con ese correo electrónico.';
    } else {
      $hash = password_hash($password, PASSWORD_DEFAULT);
      try {
        $stmt = $pdo->prepare('
          INSERT INTO usuarios (
            nombre, email, password_hash, telefono,
            direccion_calle, direccion_numero, direccion_barrio, direccion_referencia
          )
          VALUES (
            :nombre, :email, :hash, :telefono,
            :calle, :numero, :barrio, :referencia
          )
        ');
        $stmt->execute([
          'nombre'     => $nombre,
          'email'      => $email,
          'hash'       => $hash,
          'telefono'   => $telefono,
          'calle'      => $tocoDireccion ? $direccionCalle : null,
          'numero'     => $tocoDireccion ? $direccionNumero : null,
          'barrio'     => $tocoDireccion ? $direccionBarrio : null,
          'referencia' => ($tocoDireccion && $direccionReferencia !== '') ? $direccionReferencia : null,
        ]);
        $nuevoUsuarioId = $pdo->lastInsertId();
      } catch (PDOException $e) {
        if ($e->getCode() === '23000') {
          // Clave duplicada: otro request (doble clic) creó la cuenta entre el SELECT y el INSERT
          $error = 'Ya existe una cuenta registrada con ese correo electrónico.';
        } else {
          error_log('[el-rapido] registro: ' . $e->getMessage());
          $error = 'No pudimos crear tu cuenta en este momento. Probá de nuevo en unos minutos.';
        }
        $nuevoUsuarioId = null;
      }

      if ($nuevoUsuarioId !== null) {
        ipRegistrarAccion($pdo, 'registro');
        iniciarSesionUsuario([
          'id'     => $nuevoUsuarioId,
          'nombre' => $nombre,
          'email'  => $email,
        ]);
        header('Location: index.php');
        exit;
      }
    }
  }
}

include 'includes/header.php';
require_once 'includes/iconos.php';
?>

<section class="auth-section">
  <div class="auth-inner">

    <div class="auth-form-col">
      <h1 class="auth-titulo">CREAR CUENTA</h1>
      <p class="auth-subtitulo">Registrate para empezar a comprar en El Rápido</p>

      <?php if ($error): ?>
        <div class="auth-error" role="alert"><?= icono('alerta', '', 16) ?> <?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <form class="auth-form" method="POST" action="registro.php">
<?= csrfCampo() ?>
        <div class="auth-campo">
          <label for="nombre">Nombre completo</label>
          <div class="auth-input-wrap">
            <span class="auth-input-icono"><?= icono('usuario', '', 16) ?></span>
            <input type="text" id="nombre" name="nombre" placeholder="Ingresá tu nombre completo" autocomplete="name" maxlength="100" required value="<?= htmlspecialchars($_POST['nombre'] ?? '') ?>">
          </div>
        </div>

        <div class="auth-campo">
          <label for="email">Correo electrónico</label>
          <div class="auth-input-wrap">
            <span class="auth-input-icono"><?= icono('correo', '', 16) ?></span>
            <input type="email" id="email" name="email" placeholder="Ingresá tu correo electrónico" autocomplete="username" maxlength="150" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
          </div>
        </div>

        <div class="auth-campo">
          <label for="telefono">Teléfono</label>
          <div class="auth-input-wrap">
            <span class="auth-input-icono"><?= icono('telefono', '', 16) ?></span>
            <input type="tel" id="telefono" name="telefono" placeholder="Ej: 11 1234 5678" autocomplete="tel" required
                   inputmode="tel" pattern="[0-9+\s-]{8,20}" title="Solo números, espacios, guiones o &quot;+&quot; (8 a 20 caracteres)"
                   value="<?= htmlspecialchars($_POST['telefono'] ?? '') ?>">
          </div>
        </div>

        <div class="auth-campo">
          <label for="password">Contraseña</label>
          <div class="auth-input-wrap">
            <span class="auth-input-icono"><?= icono('candado', '', 16) ?></span>
            <input type="password" id="password" name="password" placeholder="Creá una contraseña (mínimo 10 caracteres)" minlength="10" maxlength="72" autocomplete="new-password" required>
            <button type="button" class="auth-toggle-pass" aria-label="Mostrar contraseña" aria-pressed="false" onclick="togglePassword(this)"><?= icono('ojo', 'icono-ojo', 16) ?></button>
          </div>
        </div>

        <p class="auth-seccion-titulo">Dirección de entrega (opcional)</p>
        <p class="auth-seccion-ayuda">Guardala para no tener que volver a escribirla en cada pedido. Podés dejarla en blanco y cargarla después, o cambiarla en cada compra si hace falta.</p>

        <div class="auth-campo-grupo">
          <div class="auth-campo">
            <label for="direccion_calle">Calle</label>
            <div class="auth-input-wrap">
              <span class="auth-input-icono"><?= icono('ubicacion', '', 16) ?></span>
              <input type="text" id="direccion_calle" name="direccion_calle" placeholder="Ej: San Martín" maxlength="150" value="<?= htmlspecialchars($_POST['direccion_calle'] ?? '') ?>">
            </div>
          </div>
          <div class="auth-campo">
            <label for="direccion_numero">Número</label>
            <div class="auth-input-wrap">
              <input type="text" id="direccion_numero" name="direccion_numero" placeholder="Ej: 1234" maxlength="20" value="<?= htmlspecialchars($_POST['direccion_numero'] ?? '') ?>">
            </div>
          </div>
        </div>

        <div class="auth-campo">
          <label for="direccion_barrio">Barrio</label>
          <div class="auth-input-wrap">
            <input type="text" id="direccion_barrio" name="direccion_barrio" placeholder="Ej: Centro" maxlength="100" value="<?= htmlspecialchars($_POST['direccion_barrio'] ?? '') ?>">
          </div>
        </div>

        <div class="auth-campo">
          <label for="direccion_referencia">Referencia (opcional)</label>
          <div class="auth-input-wrap">
            <input type="text" id="direccion_referencia" name="direccion_referencia" placeholder="Ej: Portón negro, casa con rejas" maxlength="255" value="<?= htmlspecialchars($_POST['direccion_referencia'] ?? '') ?>">
          </div>
        </div>

        <button type="submit" class="btn-auth-principal">CREAR CUENTA</button>

        <div class="auth-divisor"><span>o continuá con</span></div>

        <div class="auth-social">
          <button type="button" class="btn-social" disabled title="Próximamente">
            <span class="btn-social-icono"><?= logoGoogle(16) ?></span> Google <small class="btn-social-pronto">Próximamente</small>
          </button>
          <button type="button" class="btn-social" disabled title="Próximamente">
            <span class="btn-social-icono"><?= logoFacebook(16) ?></span> Facebook <small class="btn-social-pronto">Próximamente</small>
          </button>
        </div>

        <p class="auth-alt">¿Ya tenés una cuenta? <a href="login.php">Iniciar sesión</a></p>
      </form>
    </div>

    <div class="auth-imagen-col">
      <?php renderFotoHeroCiclo(); ?>
    </div>

  </div>
</section>

<script src="<?= htmlspecialchars(assetUrl('assets/js/auth.js')) ?>" defer></script>

<?php include 'includes/footer.php'; ?>

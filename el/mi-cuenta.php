<?php
$pageCss = 'carrito.css';      // tabla, resumen y botones compartidos con el carrito
$pageCssExtra = ['cuenta.css']; // va después para que sus reglas tengan prioridad
// Mismo criterio que en login.php/registro.php/carrito.php: toda la lógica
// que puede redirigir con header('Location: ...') va ANTES de incluir
// includes/header.php (que ya imprime HTML). header.php se incluye recién
// al final, justo antes de que arranque el HTML de esta página.
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/funciones.php';
require_once 'includes/compra_semanal.php';
require_once 'includes/favoritos.php';
csrfExigir(); // protección CSRF: rechaza cualquier POST sin token válido

if (!usuarioLogueado()) {
  header('Location: login.php?volver=mi-cuenta.php');
  exit;
}

$pdo = obtenerConexion();
$usuarioId = (int) $_SESSION['usuario']['id'];

$seccionesValidas = ['resumen', 'pedidos', 'compra-semanal', 'direcciones', 'pagos', 'favoritos', 'seguridad'];
$seccion = $_GET['seccion'] ?? 'compra-semanal';
if (!in_array($seccion, $seccionesValidas, true)) {
  $seccion = 'compra-semanal';
}

/* ═══════════════════════════════════════════════════════════
   ACCIONES (POST) sobre la Compra Semanal
   ═══════════════════════════════════════════════════════════ */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'agregar_todo_carrito') {
  $compraId = (int) ($_POST['compra_id'] ?? 0);
  $resultado = agregarCompraSemanalAlCarrito($pdo, $usuarioId, $compraId);
  if (!empty($resultado['agregados'])) {
    flashSet('ok', count($resultado['agregados']) . ' producto(s) agregados al carrito con precio y stock actualizados.');
  }
  if (!empty($resultado['no_disponibles'])) {
    flashSet('error', 'No se pudieron agregar: ' . implode(', ', $resultado['no_disponibles']) . '.');
  }
  header('Location: mi-cuenta.php?seccion=compra-semanal');
  exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'eliminar_lista') {
  $compraId = (int) ($_POST['compra_id'] ?? 0);
  eliminarCompraSemanal($pdo, $usuarioId, $compraId);
  flashSet('ok', 'Esa Compra Semanal fue eliminada.');
  header('Location: mi-cuenta.php?seccion=compra-semanal');
  exit;
}

/* ═══════════════════════════════════════════════════════════
   ACCIÓN (POST): quitar un producto de Favoritos
   ═══════════════════════════════════════════════════════════ */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'quitar_favorito') {
  establecerFavorito($pdo, $usuarioId, (int) ($_POST['producto_id'] ?? 0), false);
  flashSet('ok', 'Quitamos ese producto de tus favoritos.');
  header('Location: mi-cuenta.php?seccion=favoritos');
  exit;
}

/* ═══════════════════════════════════════════════════════════
   ACCIONES (POST) sobre Teléfono / Dirección guardados
   ═══════════════════════════════════════════════════════════ */
$errorDireccion = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'guardar_direccion') {
  $telefonoForm   = postTexto('telefono');
  $calleForm      = postTexto('direccion_calle');
  $numeroForm     = postTexto('direccion_numero');
  $barrioForm     = postTexto('direccion_barrio');
  $referenciaForm = postTexto('direccion_referencia');
  $tocoDireccionForm = $calleForm !== '' || $numeroForm !== '' || $barrioForm !== '';

  if (!validarTelefono($telefonoForm)) {
    $errorDireccion = 'Ingresá un teléfono de contacto válido (solo números, espacios o guiones, entre 8 y 20 caracteres).';
  } elseif ($tocoDireccionForm && !validarTextoEntrega($calleForm, 2, 150)) {
    $errorDireccion = 'Ingresá la calle de tu dirección (o dejá los tres campos vacíos si no querés guardar una).';
  } elseif ($tocoDireccionForm && !validarNumeroCalle($numeroForm)) {
    $errorDireccion = 'Ingresá el número de tu dirección (o "s/n" si no tiene).';
  } elseif ($tocoDireccionForm && !validarTextoEntrega($barrioForm, 2, 100)) {
    $errorDireccion = 'Ingresá el barrio de tu dirección.';
  } elseif ($tocoDireccionForm && mb_strlen($referenciaForm) > 255) {
    $errorDireccion = 'La referencia es demasiado larga (máximo 255 caracteres).';
  } else {
    $stmtGuardar = $pdo->prepare('
      UPDATE usuarios
      SET telefono = :telefono, direccion_calle = :calle, direccion_numero = :numero,
          direccion_barrio = :barrio, direccion_referencia = :referencia
      WHERE id = :id
    ');
    $stmtGuardar->execute([
      ':telefono'   => $telefonoForm,
      ':calle'      => $tocoDireccionForm ? $calleForm : null,
      ':numero'     => $tocoDireccionForm ? $numeroForm : null,
      ':barrio'     => $tocoDireccionForm ? $barrioForm : null,
      ':referencia' => ($tocoDireccionForm && $referenciaForm !== '') ? $referenciaForm : null,
      ':id'         => $usuarioId,
    ]);
    flashSet('ok', 'Tus datos de contacto se guardaron correctamente.');
    header('Location: mi-cuenta.php?seccion=direcciones');
    exit;
  }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'borrar_direccion') {
  $stmtBorrar = $pdo->prepare('
    UPDATE usuarios
    SET direccion_calle = NULL, direccion_numero = NULL, direccion_barrio = NULL, direccion_referencia = NULL
    WHERE id = :id
  ');
  $stmtBorrar->execute([':id' => $usuarioId]);
  flashSet('ok', 'Borramos tu dirección guardada. El teléfono lo mantuvimos.');
  header('Location: mi-cuenta.php?seccion=direcciones');
  exit;
}

/* ═══════════════════════════════════════════════════════════
   ACCIÓN (POST): cambiar la contraseña desde Mi cuenta
   ═══════════════════════════════════════════════════════════ */
$errorPassword = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'cambiar_password') {
  $seccion = 'seguridad';
  $actualPw   = postRaw('password_actual');
  $nuevaPw    = postRaw('password_nueva');
  $repetidaPw = postRaw('password_repetida');
  $emailCuenta = (string) $_SESSION['usuario']['email'];

  $stmtPw = $pdo->prepare('SELECT password_hash FROM usuarios WHERE id = :id');
  $stmtPw->execute([':id' => $usuarioId]);
  $hashActualPw = $stmtPw->fetchColumn();

  if (loginBloqueado($pdo, 'sitio', $emailCuenta)) {
    $errorPassword = 'Demasiados intentos fallidos. Esperá ' . LOGIN_VENTANA_MIN . ' minutos y probá de nuevo.';
  } elseif (!$hashActualPw || !password_verify($actualPw, $hashActualPw)) {
    registrarLoginFallido($pdo, 'sitio', $emailCuenta);
    $errorPassword = 'La contraseña actual no es correcta.';
  } elseif (($errPw = validarPasswordNueva($nuevaPw, $emailCuenta)) !== null) {
    $errorPassword = $errPw;
  } elseif (!hash_equals($nuevaPw, $repetidaPw)) {
    $errorPassword = 'La contraseña nueva y su repetición no coinciden.';
  } elseif (hash_equals($actualPw, $nuevaPw)) {
    $errorPassword = 'La contraseña nueva tiene que ser distinta de la actual.';
  } else {
    try {
      $pdo->prepare('UPDATE usuarios SET password_hash = :h, password_cambiada_en = NOW() WHERE id = :id')
          ->execute([':h' => password_hash($nuevaPw, PASSWORD_DEFAULT), ':id' => $usuarioId]);
      limpiarLoginFallidos($pdo, 'sitio', $emailCuenta);
      // Nuevo ID de sesión y "reloj" nuevo: esta sesión sigue válida, las demás quedan cerradas.
      session_regenerate_id(true);
      $_SESSION['usuario']['iniciada_en']   = time();
      $_SESSION['usuario']['verificado_en'] = time();
      flashSet('ok', 'Tu contraseña se actualizó. Cerramos tus otras sesiones abiertas.');
      header('Location: mi-cuenta.php?seccion=seguridad');
      exit;
    } catch (Throwable $e) {
      error_log('[el-rapido] cambiar contraseña: ' . $e->getMessage());
      $errorPassword = 'No pudimos actualizar tu contraseña en este momento. Probá de nuevo en unos minutos.';
    }
  }
}

/* ═══════════════════════════════════════════════════════════
   DATOS PARA LA VISTA
   ═══════════════════════════════════════════════════════════ */
$comprasSemanales = obtenerComprasSemanales($pdo, $usuarioId);
$productosFavoritos = $seccion === 'favoritos' ? obtenerProductosFavoritos($pdo, $usuarioId) : [];
$puedeAgregarLista = count($comprasSemanales) < MAX_COMPRAS_SEMANALES;

$paginaHistorial = max(1, (int) ($_GET['pagina'] ?? 1));
$historial = obtenerHistorialPedidos($pdo, $usuarioId, $paginaHistorial, 5);

$mensajesFlash = flashConsumir();

$perfilContacto = null;
if ($seccion === 'direcciones') {
  $stmtPerfil = $pdo->prepare('
    SELECT telefono, direccion_calle, direccion_numero, direccion_barrio, direccion_referencia
    FROM usuarios WHERE id = :id
  ');
  $stmtPerfil->execute([':id' => $usuarioId]);
  $perfilContacto = $stmtPerfil->fetch() ?: [];
}

// Recién acá, con toda la lógica que podía redirigir ya resuelta, incluimos
// el header: a partir de este punto empieza el HTML de la página.
$titulosSeccion = [
  'resumen' => 'Resumen de cuenta', 'pedidos' => 'Mis pedidos', 'compra-semanal' => 'Compra semanal',
  'direcciones' => 'Direcciones', 'pagos' => 'Métodos de pago', 'favoritos' => 'Favoritos', 'seguridad' => 'Cambiar contraseña',
];
$pageTitle = 'Mi cuenta - ' . ($titulosSeccion[$seccion] ?? 'Resumen de cuenta');
$pageDescription = 'Tu cuenta en El Rápido: pedidos, compra semanal, direcciones y contraseña.';
$pageRobots = 'noindex,follow'; // área privada
include 'includes/header.php';
require_once 'includes/iconos.php';
?>

<div class="cuenta-wrap">

  <div class="cuenta-layout">

    <!-- ═══════════ MENÚ LATERAL ═══════════ -->
    <aside class="cuenta-sidebar">
      <div class="cuenta-sidebar-card">
        <p class="cuenta-sidebar-titulo">MI CUENTA</p>
        <nav class="cuenta-menu-lateral" aria-label="Secciones de Mi cuenta">
          <a href="mi-cuenta.php?seccion=resumen" class="cuenta-menu-lat-item <?= $seccion === 'resumen' ? 'activo' : '' ?>"<?= $seccion === 'resumen' ? ' aria-current="page"' : '' ?>>
            <span><?= icono('usuario', '', 16) ?></span> Resumen de cuenta
          </a>
          <a href="mi-cuenta.php?seccion=pedidos" class="cuenta-menu-lat-item <?= $seccion === 'pedidos' ? 'activo' : '' ?>"<?= $seccion === 'pedidos' ? ' aria-current="page"' : '' ?>>
            <span><?= icono('paquete', '', 16) ?></span> Mis pedidos
          </a>
          <a href="mi-cuenta.php?seccion=compra-semanal" class="cuenta-menu-lat-item <?= $seccion === 'compra-semanal' ? 'activo' : '' ?>"<?= $seccion === 'compra-semanal' ? ' aria-current="page"' : '' ?>>
            <span><?= icono('calendario', '', 16) ?></span> Compra semanal
          </a>
          <a href="mi-cuenta.php?seccion=direcciones" class="cuenta-menu-lat-item <?= $seccion === 'direcciones' ? 'activo' : '' ?>"<?= $seccion === 'direcciones' ? ' aria-current="page"' : '' ?>>
            <span><?= icono('ubicacion', '', 16) ?></span> Direcciones
          </a>
          <a href="mi-cuenta.php?seccion=pagos" class="cuenta-menu-lat-item <?= $seccion === 'pagos' ? 'activo' : '' ?>"<?= $seccion === 'pagos' ? ' aria-current="page"' : '' ?>>
            <span><?= icono('tarjeta', '', 16) ?></span> Métodos de pago
          </a>
          <a href="mi-cuenta.php?seccion=favoritos" class="cuenta-menu-lat-item <?= $seccion === 'favoritos' ? 'activo' : '' ?>"<?= $seccion === 'favoritos' ? ' aria-current="page"' : '' ?>>
            <span><?= icono('corazon', '', 16) ?></span> Favoritos
          </a>
          <a href="mi-cuenta.php?seccion=seguridad" class="cuenta-menu-lat-item <?= $seccion === 'seguridad' ? 'activo' : '' ?>"<?= $seccion === 'seguridad' ? ' aria-current="page"' : '' ?>>
            <span><?= icono('candado', '', 16) ?></span> Contraseña
          </a>
          <a href="logout.php" class="cuenta-menu-lat-item cuenta-menu-lat-salir">
            <span><?= icono('salir', '', 16) ?></span> Cerrar sesión
          </a>
        </nav>
      </div>

      <div class="cuenta-ayuda-card">
        <div class="cuenta-ayuda-icono"><?= icono('auriculares', '', 28) ?></div>
        <p class="cuenta-ayuda-titulo">¿Necesitás ayuda?</p>
        <p class="cuenta-ayuda-desc">Estamos para ayudarte</p>
        <a href="<?= htmlspecialchars(urlWhatsapp()) ?>" target="_blank" rel="noopener" class="cuenta-ayuda-btn">Escribinos por WhatsApp</a>
      </div>
    </aside>

    <!-- ═══════════ CONTENIDO ═══════════ -->
    <div class="cuenta-contenido">

      <?php foreach ($mensajesFlash as $m): ?>
        <div class="cuenta-alerta cuenta-alerta-<?= $m['tipo'] === 'ok' ? 'ok' : 'error' ?>" role="<?= $m['tipo'] === 'ok' ? 'status' : 'alert' ?>">
          <?= $m['tipo'] === 'ok' ? icono('check-circulo', '', 16) : icono('alerta', '', 16) ?> <?= htmlspecialchars($m['mensaje']) ?>
        </div>
      <?php endforeach; ?>

      <?php if ($seccion === 'compra-semanal'): ?>

        <!-- ═══════════ COMPRA SEMANAL ═══════════ -->
        <div class="cuenta-seccion-header cuenta-seccion-header-flex">
          <div>
            <h1 class="cuenta-seccion-titulo">Compra semanal</h1>
            <p class="cuenta-seccion-sub">Guardá tus productos habituales y repetí tu compra con un solo clic. Podés tener hasta <?= MAX_COMPRAS_SEMANALES ?> listas.</p>
          </div>
          <?php if ($puedeAgregarLista && !empty($comprasSemanales)): ?>
            <a href="compra_semanal_editar.php?nueva=1" class="btn-nueva-lista">+ Nueva lista</a>
          <?php endif; ?>
        </div>

        <?php if (empty($comprasSemanales)): ?>

          <div class="compra-semanal-vacia">
            <div class="compra-semanal-vacia-icono"><?= icono('calendario', '', 44) ?></div>
            <h2>Todavía no creaste tu Compra Semanal</h2>
            <p>Cuando finalices un pedido vas a poder guardarlo como tu Compra Semanal para volver a pedirlo cuando quieras. También podés armarla desde cero. Podés guardar hasta <?= MAX_COMPRAS_SEMANALES ?> listas distintas (por ejemplo, una para la casa y otra para el trabajo).</p>
            <a href="compra_semanal_editar.php" class="btn-crear-primera-compra">+ Crear mi primera compra</a>
          </div>

        <?php else: ?>

          <?php foreach ($comprasSemanales as $compraSemanal): ?>
          <div class="compra-semanal-card">
            <div class="compra-semanal-card-header">
              <div>
                <h2 class="compra-semanal-nombre">
                  <?= htmlspecialchars($compraSemanal['nombre']) ?>
                  <a href="compra_semanal_editar.php?id=<?= (int) $compraSemanal['id'] ?>" class="lapiz-editar" title="Editar nombre y productos" aria-label="Editar nombre y productos de <?= htmlspecialchars($compraSemanal['nombre']) ?>"><?= icono('lapiz', '', 14) ?></a>
                </h2>
                <p class="compra-semanal-meta">Última actualización: <?= tiempoRelativo($compraSemanal['actualizado_en']) ?></p>
              </div>
            </div>

            <div class="compra-semanal-datos">
              <div class="compra-semanal-dato">
                <span class="compra-semanal-dato-icono"><?= icono('calendario', '', 20) ?></span>
                <div>
                  <p class="compra-semanal-dato-label">Día de compra</p>
                  <p class="compra-semanal-dato-valor"><?= htmlspecialchars(diaCompraLabel($compraSemanal['dia_compra'])) ?></p>
                </div>
              </div>
              <div class="compra-semanal-dato">
                <span class="compra-semanal-dato-icono"><?= icono('paquete', '', 20) ?></span>
                <div>
                  <p class="compra-semanal-dato-label">Productos guardados</p>
                  <p class="compra-semanal-dato-valor"><?= (int) $compraSemanal['cantidad_items'] ?> producto<?= $compraSemanal['cantidad_items'] === 1 ? '' : 's' ?></p>
                </div>
              </div>
            </div>

            <p class="compra-semanal-productos-titulo">Productos (<?= (int) $compraSemanal['cantidad_items'] ?>)</p>
            <div class="compra-semanal-productos-grid">
              <?php foreach ($compraSemanal['items'] as $item): ?>
                <div class="csp-item <?= $item['disponible'] ? '' : 'csp-item-no-disponible' ?>">
                  <span class="csp-item-cant">x<?= (int) $item['cantidad'] ?></span>
                  <div class="csp-item-img">
                    <?php if ($item['disponible'] && !empty($item['imagen'])): ?>
                      <img src="assets/img/productos/<?= htmlspecialchars($item['imagen']) ?>" alt="<?= htmlspecialchars($item['nombre']) ?>" loading="lazy" decoding="async">
                    <?php else: ?>
                      <?= htmlspecialchars($item['emoji']) ?>
                    <?php endif; ?>
                  </div>
                  <p class="csp-item-nombre"><?= htmlspecialchars($item['nombre']) ?></p>
                  <?php if (!$item['disponible']): ?>
                    <p class="csp-item-aviso">No disponible</p>
                  <?php elseif ($item['stock'] <= 0): ?>
                    <p class="csp-item-aviso">Sin stock</p>
                  <?php endif; ?>
                </div>
              <?php endforeach; ?>
            </div>

            <div class="compra-semanal-acciones">
              <form method="POST" action="mi-cuenta.php?seccion=compra-semanal" class="cs-accion-form">
<?= csrfCampo() ?>
                <input type="hidden" name="accion" value="agregar_todo_carrito">
                <input type="hidden" name="compra_id" value="<?= (int) $compraSemanal['id'] ?>">
                <button type="submit" class="btn-agregar-todo" aria-label="Agregar todo al carrito: <?= htmlspecialchars($compraSemanal['nombre']) ?>"><?= icono('carrito', '', 16) ?> Agregar todo al carrito</button>
              </form>
              <a href="compra_semanal_editar.php?id=<?= (int) $compraSemanal['id'] ?>" class="btn-editar-productos" aria-label="Editar productos de <?= htmlspecialchars($compraSemanal['nombre']) ?>"><?= icono('lapiz', '', 16) ?> Editar productos</a>
              <form method="POST" action="mi-cuenta.php?seccion=compra-semanal" class="cs-accion-form"
                    onsubmit="return confirm('¿Eliminar definitivamente esta Compra Semanal? Esta acción no se puede deshacer.');">
<?= csrfCampo() ?>
                <input type="hidden" name="accion" value="eliminar_lista">
                <input type="hidden" name="compra_id" value="<?= (int) $compraSemanal['id'] ?>">
                <button type="submit" class="btn-eliminar-lista" aria-label="Eliminar la lista <?= htmlspecialchars($compraSemanal['nombre']) ?>"><?= icono('basura', '', 16) ?> Eliminar lista</button>
              </form>
            </div>

            <div class="compra-semanal-nota">
              <?= icono('info', '', 14) ?> Los precios se actualizan al momento de agregar al carrito.
            </div>
          </div>
          <?php endforeach; ?>

          <?php if ($puedeAgregarLista): ?>
            <a href="compra_semanal_editar.php?nueva=1" class="compra-semanal-agregar-otra">
              <span class="compra-semanal-agregar-otra-icono">+</span>
              Crear otra Compra Semanal (<?= count($comprasSemanales) ?>/<?= MAX_COMPRAS_SEMANALES ?>)
            </a>
          <?php else: ?>
            <div class="compra-semanal-limite">
              Llegaste al máximo de <?= MAX_COMPRAS_SEMANALES ?> Compras Semanales. Para crear una nueva, primero eliminá o editá alguna de las que ya tenés.
            </div>
          <?php endif; ?>

        <?php endif; ?>

        <!-- ═══════════ HISTORIAL DE PEDIDOS ═══════════ -->
        <div class="historial-header">
          <h2 class="historial-titulo">Historial de pedidos</h2>
          <p class="historial-sub">Revisá tus pedidos anteriores.</p>
        </div>

        <?php if (empty($historial['pedidos'])): ?>
          <div class="historial-vacio">
            <div class="historial-vacio-icono"><?= icono('paquete', '', 32) ?></div>
            <p>Todavía no hiciste ningún pedido.</p>
            <a href="productos.php" class="carrito-seguir-comprando">Ver productos <?= icono('flecha-der', '', 16) ?></a>
          </div>
        <?php else: ?>
          <div class="historial-lista">
            <?php foreach ($historial['pedidos'] as $p):
              $info = estadoPedidoInfo($p['estado']);
              $fecha = new DateTime($p['creado_en']);
            ?>
              <div class="historial-item">
                <div class="historial-item-icono"><?= icono('paquete', '', 18) ?></div>
                <div class="historial-item-info">
                  <p class="historial-item-numero">Pedido #<?= (int) $p['id'] ?></p>
                  <p class="historial-item-fecha"><?= $fecha->format('d/m/Y') ?> • <?= $fecha->format('H:i') ?></p>
                </div>
                <div class="historial-item-total">
                  <p class="historial-total-monto"><?= formatoPrecio((float) $p['total']) ?></p>
                  <p class="historial-total-label">Total</p>
                </div>
                <div class="historial-item-estado">
                  <span class="estado-badge <?= $info['clase'] ?>">● <?= htmlspecialchars($info['label']) ?></span>
                </div>
                <div class="historial-item-acciones">
                  <a href="pedido_detalle.php?id=<?= (int) $p['id'] ?>" class="btn-ver-detalle" aria-label="Ver detalle del pedido #<?= (int) $p['id'] ?>"><?= icono('ojo', '', 14) ?> Ver detalle</a>
                  <?php if ($p['estado'] !== 'cancelado'): ?>
                    <form method="POST" action="pedido_repetir.php" class="cs-accion-form">
<?= csrfCampo() ?>
                      <input type="hidden" name="pedido_id" value="<?= (int) $p['id'] ?>">
                      <input type="hidden" name="volver" value="mi-cuenta.php?seccion=compra-semanal">
                      <button type="submit" class="btn-volver-comprar" aria-label="Volver a comprar el pedido #<?= (int) $p['id'] ?>"><?= icono('repetir', '', 14) ?> Volver a comprar</button>
                    </form>
                  <?php endif; ?>
                </div>
              </div>
            <?php endforeach; ?>
          </div>

          <?php if ($historial['total_paginas'] > 1): ?>
            <nav class="historial-paginacion" aria-label="Paginación del historial de pedidos">
              <?php for ($i = 1; $i <= $historial['total_paginas']; $i++): ?>
                <a href="mi-cuenta.php?seccion=compra-semanal&pagina=<?= $i ?>"
                   class="pagina-btn <?= $i === $historial['pagina'] ? 'activa' : '' ?>" aria-label="Página <?= $i ?>"<?= $i === $historial['pagina'] ? ' aria-current="page"' : '' ?>><?= $i ?></a>
              <?php endfor; ?>
            </nav>
          <?php endif; ?>
        <?php endif; ?>

      <?php elseif ($seccion === 'pedidos'): ?>

        <div class="cuenta-seccion-header">
          <h1 class="cuenta-seccion-titulo">Mis pedidos</h1>
          <p class="cuenta-seccion-sub">Todos tus pedidos realizados en El Rápido.</p>
        </div>
        <?php if (empty($historial['pedidos'])): ?>
          <div class="historial-vacio">
            <div class="historial-vacio-icono"><?= icono('paquete', '', 32) ?></div>
            <p>Todavía no hiciste ningún pedido.</p>
            <a href="productos.php" class="carrito-seguir-comprando">Ver productos <?= icono('flecha-der', '', 16) ?></a>
          </div>
        <?php else: ?>
          <div class="historial-lista">
            <?php foreach ($historial['pedidos'] as $p):
              $info = estadoPedidoInfo($p['estado']);
              $fecha = new DateTime($p['creado_en']);
            ?>
              <div class="historial-item">
                <div class="historial-item-icono"><?= icono('paquete', '', 18) ?></div>
                <div class="historial-item-info">
                  <p class="historial-item-numero">Pedido #<?= (int) $p['id'] ?></p>
                  <p class="historial-item-fecha"><?= $fecha->format('d/m/Y') ?> • <?= $fecha->format('H:i') ?></p>
                </div>
                <div class="historial-item-total">
                  <p class="historial-total-monto"><?= formatoPrecio((float) $p['total']) ?></p>
                  <p class="historial-total-label">Total</p>
                </div>
                <div class="historial-item-estado">
                  <span class="estado-badge <?= $info['clase'] ?>">● <?= htmlspecialchars($info['label']) ?></span>
                </div>
                <div class="historial-item-acciones">
                  <a href="pedido_detalle.php?id=<?= (int) $p['id'] ?>" class="btn-ver-detalle" aria-label="Ver detalle del pedido #<?= (int) $p['id'] ?>"><?= icono('ojo', '', 14) ?> Ver detalle</a>
                  <?php if ($p['estado'] !== 'cancelado'): ?>
                    <form method="POST" action="pedido_repetir.php" class="cs-accion-form">
<?= csrfCampo() ?>
                      <input type="hidden" name="pedido_id" value="<?= (int) $p['id'] ?>">
                      <input type="hidden" name="volver" value="mi-cuenta.php?seccion=pedidos">
                      <button type="submit" class="btn-volver-comprar" aria-label="Volver a comprar el pedido #<?= (int) $p['id'] ?>"><?= icono('repetir', '', 14) ?> Volver a comprar</button>
                    </form>
                  <?php endif; ?>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>

      <?php elseif ($seccion === 'resumen'): ?>
        <div class="cuenta-seccion-header">
          <h1 class="cuenta-seccion-titulo">Resumen de cuenta</h1>
          <p class="cuenta-seccion-sub">Tus datos personales.</p>
        </div>
        <div class="placeholder-card">
          <p><strong>Nombre:</strong> <?= htmlspecialchars($_SESSION['usuario']['nombre']) ?></p>
          <p><strong>Email:</strong> <?= htmlspecialchars($_SESSION['usuario']['email']) ?></p>
        </div>

      <?php elseif ($seccion === 'direcciones'): ?>
        <div class="cuenta-seccion-header">
          <h1 class="cuenta-seccion-titulo">Direcciones</h1>
          <p class="cuenta-seccion-sub">Guardá tu teléfono y tu dirección de entrega para no volver a escribirlos en cada pedido. Vas a poder elegir otro en cualquier compra si hace falta.</p>
        </div>

        <?php if ($errorDireccion): ?>
          <div class="cuenta-alerta cuenta-alerta-error" role="alert"><?= icono('alerta', '', 16) ?> <?= htmlspecialchars($errorDireccion) ?></div>
        <?php endif; ?>

        <form method="POST" action="mi-cuenta.php?seccion=direcciones">
<?= csrfCampo() ?>
          <input type="hidden" name="accion" value="guardar_direccion">

          <div class="editor-datos-card editor-datos-card-1col">
            <div class="resumen-campo">
              <label for="perfil-telefono">Teléfono de contacto</label>
              <input type="tel" id="perfil-telefono" name="telefono" autocomplete="tel" required
                     inputmode="tel" pattern="[0-9+\s-]{8,20}" title="Solo números, espacios, guiones o &quot;+&quot; (8 a 20 caracteres)"
                     value="<?= htmlspecialchars($_POST['telefono'] ?? $perfilContacto['telefono'] ?? '') ?>">
            </div>
          </div>

          <p class="cuenta-seccion-titulo-chico">Dirección de entrega (opcional)</p>

          <div class="editor-datos-card">
            <div class="resumen-campo">
              <label for="perfil-calle">Calle</label>
              <input type="text" id="perfil-calle" name="direccion_calle" maxlength="150"
                     value="<?= htmlspecialchars($_POST['direccion_calle'] ?? $perfilContacto['direccion_calle'] ?? '') ?>">
            </div>
            <div class="resumen-campo">
              <label for="perfil-numero">Número</label>
              <input type="text" id="perfil-numero" name="direccion_numero" maxlength="20"
                     value="<?= htmlspecialchars($_POST['direccion_numero'] ?? $perfilContacto['direccion_numero'] ?? '') ?>">
            </div>
            <div class="resumen-campo">
              <label for="perfil-barrio">Barrio</label>
              <input type="text" id="perfil-barrio" name="direccion_barrio" maxlength="100"
                     value="<?= htmlspecialchars($_POST['direccion_barrio'] ?? $perfilContacto['direccion_barrio'] ?? '') ?>">
            </div>
            <div class="resumen-campo">
              <label for="perfil-referencia">Referencia (opcional)</label>
              <input type="text" id="perfil-referencia" name="direccion_referencia" maxlength="255"
                     value="<?= htmlspecialchars($_POST['direccion_referencia'] ?? $perfilContacto['direccion_referencia'] ?? '') ?>">
            </div>
          </div>

          <div class="direccion-form-acciones">
            <button type="submit" class="btn-guardar-lista"><?= icono('check', '', 16) ?> Guardar cambios</button>
            <?php if (!empty($perfilContacto['direccion_calle'])): ?>
              <button type="submit" form="form-borrar-direccion" class="btn-eliminar-lista"><?= icono('basura', '', 16) ?> Borrar dirección guardada</button>
            <?php endif; ?>
          </div>
        </form>

        <?php if (!empty($perfilContacto['direccion_calle'])): ?>
          <form method="POST" action="mi-cuenta.php?seccion=direcciones" id="form-borrar-direccion"
                onsubmit="return confirm('¿Borrar la dirección guardada? El teléfono no se toca.');">
<?= csrfCampo() ?>
            <input type="hidden" name="accion" value="borrar_direccion">
          </form>
        <?php endif; ?>

      <?php elseif ($seccion === 'seguridad'): ?>
        <div class="cuenta-seccion-header">
          <h1 class="cuenta-seccion-titulo">Cambiar contraseña</h1>
          <p class="cuenta-seccion-sub">Usá al menos <?= PASSWORD_MIN_CLIENTE ?> caracteres. Al cambiarla, cerramos tus sesiones abiertas en otros dispositivos.</p>
        </div>

        <?php if ($errorPassword): ?>
          <div class="cuenta-alerta cuenta-alerta-error" role="alert"><?= icono('alerta', '', 16) ?> <?= htmlspecialchars($errorPassword) ?></div>
        <?php endif; ?>

        <form method="POST" action="mi-cuenta.php?seccion=seguridad" autocomplete="off">
<?= csrfCampo() ?>
          <input type="hidden" name="accion" value="cambiar_password">
          <div class="editor-datos-card editor-datos-card-1col">
            <div class="resumen-campo">
              <label for="pw-actual">Contraseña actual</label>
              <input type="password" id="pw-actual" name="password_actual" autocomplete="current-password" required>
            </div>
            <div class="resumen-campo">
              <label for="pw-nueva">Contraseña nueva</label>
              <input type="password" id="pw-nueva" name="password_nueva" autocomplete="new-password" minlength="<?= PASSWORD_MIN_CLIENTE ?>" maxlength="72" required>
            </div>
            <div class="resumen-campo">
              <label for="pw-repetida">Repetí la contraseña nueva</label>
              <input type="password" id="pw-repetida" name="password_repetida" autocomplete="new-password" minlength="<?= PASSWORD_MIN_CLIENTE ?>" maxlength="72" required>
            </div>
          </div>
          <div class="direccion-form-acciones">
            <button type="submit" class="btn-guardar-lista"><?= icono('check', '', 16) ?> Guardar contraseña</button>
          </div>
        </form>

      <?php elseif ($seccion === 'pagos'): ?>
        <div class="cuenta-seccion-header">
          <h1 class="cuenta-seccion-titulo">Métodos de pago</h1>
          <p class="cuenta-seccion-sub">Por ahora el pago se hace al retirar o recibir tu pedido.</p>
        </div>
        <div class="placeholder-card"><p>Guardar métodos de pago todavía no está disponible. Próximamente.</p></div>

      <?php elseif ($seccion === 'favoritos'): ?>
        <!-- ═══════════ FAVORITOS ═══════════ -->
        <div class="cuenta-seccion-header cuenta-seccion-header-flex">
          <div>
            <h1 class="cuenta-seccion-titulo">Favoritos</h1>
            <p class="cuenta-seccion-sub">Los productos que marcás con el corazón quedan guardados acá y aparecen primero cuando mirás el catálogo.</p>
          </div>
        </div>

        <?php if (empty($productosFavoritos)): ?>
          <div class="compra-semanal-vacia">
            <div class="compra-semanal-vacia-icono"><?= icono('corazon', '', 44) ?></div>
            <h2>Todavía no tenés favoritos</h2>
            <p>Tocá el corazón de cualquier producto del catálogo para guardarlo acá. Vas a verlo siempre primero cuando entres a Productos.</p>
            <a href="productos.php" class="btn-crear-primera-compra">Ver productos</a>
          </div>
        <?php else: ?>
          <div class="favoritos-grid">
            <?php foreach ($productosFavoritos as $fav): ?>
              <?php
                $favPrecio   = precioConDescuento((float) $fav['precio'], (int) $fav['descuento_pct']);
                $favSinStock = (int) $fav['stock'] <= 0;
              ?>
              <div class="favorito-card <?= $favSinStock ? 'favorito-card-sin-stock' : '' ?>">
                <div class="favorito-img">
                  <?php if (!empty($fav['imagen'])): ?>
                    <img src="assets/img/productos/<?= htmlspecialchars($fav['imagen']) ?>" alt="<?= htmlspecialchars($fav['nombre']) ?>" loading="lazy" decoding="async">
                  <?php else: ?>
                    <?= htmlspecialchars($fav['emoji']) ?>
                  <?php endif; ?>
                </div>
                <p class="favorito-nombre"><?= htmlspecialchars($fav['nombre']) ?></p>
                <p class="favorito-precio">
                  <?= formatoPrecio($favPrecio) ?>
                  <?php if ((int) $fav['descuento_pct'] > 0): ?>
                    <span class="favorito-precio-original"><?= formatoPrecio((float) $fav['precio']) ?></span>
                  <?php endif; ?>
                </p>
                <div class="favorito-acciones">
                  <?php if ($favSinStock): ?>
                    <button type="button" class="btn-agregar-todo" disabled>Sin stock</button>
                  <?php else: ?>
                    <form method="POST" action="carrito_agregar.php" class="cs-accion-form">
<?= csrfCampo() ?>
                      <input type="hidden" name="producto_id" value="<?= (int) $fav['id'] ?>">
                      <input type="hidden" name="volver" value="mi-cuenta.php?seccion=favoritos">
                      <button type="submit" class="btn-agregar-todo" aria-label="Agregar <?= htmlspecialchars($fav['nombre']) ?> al carrito"><?= icono('carrito', '', 16) ?> Agregar</button>
                    </form>
                  <?php endif; ?>
                  <form method="POST" action="mi-cuenta.php?seccion=favoritos" class="cs-accion-form">
<?= csrfCampo() ?>
                    <input type="hidden" name="accion" value="quitar_favorito">
                    <input type="hidden" name="producto_id" value="<?= (int) $fav['id'] ?>">
                    <button type="submit" class="btn-eliminar-lista" aria-label="Quitar <?= htmlspecialchars($fav['nombre']) ?> de favoritos"><?= icono('basura', '', 16) ?> Quitar</button>
                  </form>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      <?php endif; ?>

    </div><!-- /cuenta-contenido -->
  </div>
</div>

<?php include 'includes/footer.php'; ?>

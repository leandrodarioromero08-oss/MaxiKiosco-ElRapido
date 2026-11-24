<?php
/**
 * FUNCIONES DEL SITIO PÚBLICO
 * Acá viven las funciones que se repiten entre varias páginas
 * (login.php, registro.php, productos.php, carrito.php, etc).
 * Cada página incluye este archivo y llama solo a las funciones
 * que necesita. Las funciones específicas del carrito (agregar,
 * eliminar, calcular totales) están en includes/carrito.php,
 * separadas porque son un tema aparte.
 */

/* ═══════════════════════════════════════════════════════════
   SESIÓN / AUTENTICACIÓN
   Funciones que usan login.php, registro.php y logout.php
   para guardar y consultar el usuario logueado.
   ═══════════════════════════════════════════════════════════ */

/**
 * Guarda en la sesión los datos mínimos del usuario logueado.
 * Se llama justo después de un login o un registro exitoso.
 * Recibe el array que devuelve la consulta a la tabla "usuarios"
 * y se queda solo con id/nombre/email (nunca con el password_hash).
 */
function iniciarSesionUsuario(array $usuario): void {
  // Nuevo id de sesión al loguearse: evita la "fijación de sesión".
  session_regenerate_id(true);
  unset($_SESSION['csrf_token']);
  $_SESSION['usuario'] = [
    'id'     => $usuario['id'],
    'nombre' => $usuario['nombre'],
    'email'  => $usuario['email'],
    'iniciada_en'  => time(), // para invalidar sesiones viejas si se cambia la contraseña
    'verificado_en' => time(),
  ];
}

/** Devuelve true si hay alguien logueado en esta sesión */
function usuarioLogueado(): bool {
  if (!isset($_SESSION['usuario'])) {
    return false;
  }

  // Cada 60 segundos confirmamos contra la base que la cuenta siga existiendo y
  // que la contraseña no se haya cambiado después de iniciar esta sesión (así,
  // cambiar o recuperar la contraseña cierra las demás sesiones abiertas).
  static $revisado = false;
  if ($revisado || !function_exists('obtenerConexion')) {
    return true;
  }
  $revisado = true;

  $ahora = time();
  if ((int) ($_SESSION['usuario']['verificado_en'] ?? 0) > $ahora - 60) {
    return true;
  }
  $_SESSION['usuario']['verificado_en'] = $ahora; // también si falla, para no repetir el error en cada request

  try {
    $stmt = obtenerConexion()->prepare('SELECT password_cambiada_en FROM usuarios WHERE id = :id');
    $stmt->execute([':id' => (int) ($_SESSION['usuario']['id'] ?? 0)]);
    $fila = $stmt->fetch();

    $cambio = $fila && !empty($fila['password_cambiada_en']) ? (int) strtotime($fila['password_cambiada_en']) : 0;
    if (!$fila || $cambio > (int) ($_SESSION['usuario']['iniciada_en'] ?? 0)) {
      unset($_SESSION['usuario'], $_SESSION['csrf_token']);
      return false;
    }
  } catch (Throwable $e) {
    // Si la columna todavía no existe (falta importar la migración) no se cierra la sesión.
    error_log('[el-rapido] revalidación de sesión: ' . $e->getMessage());
  }
  return true;
}

/**
 * Arma el link de WhatsApp (wa.me) con el número real del negocio
 * (WP_NUMBER en config.php) y un mensaje precargado. Si no se pasa
 * mensaje, usa el mensaje por defecto (WP_MSG_DEFAULT).
 */
function urlWhatsapp(?string $mensaje = null): string {
  $texto = $mensaje ?? WP_MSG_DEFAULT;
  return 'https://wa.me/' . WP_NUMBER . '?text=' . rawurlencode($texto);
}

/**
 * Si el usuario ya está logueado, lo manda directo a $destino y
 * corta la ejecución. Se usa al principio de login.php y
 * registro.php para que alguien que ya inició sesión no vea
 * el formulario de nuevo.
 */
function redirigirSiYaLogueado(string $destino): void {
  if (usuarioLogueado()) {
    header('Location: ' . $destino);
    exit;
  }
}

/**
 * Lee el parámetro "volver" (de dónde vino el usuario, por ejemplo el
 * carrito) y lo devuelve limpio. Solo se aceptan rutas locales simples
 * del sitio ("archivo.php?x=y"); todo lo demás (http://, HTTP://, //, /\,
 * javascript:, saltos de línea...) se ignora y se usa $porDefecto.
 * La validación vive en includes/seguridad.php (esRutaLocalSegura).
 */
function obtenerVolverSeguro(string $porDefecto): string {
  $volver = $_GET['volver'] ?? $_POST['volver'] ?? $porDefecto;
  return esRutaLocalSegura($volver) ? $volver : $porDefecto;
}

/* ═══════════════════════════════════════════════════════════
   MENSAJES FLASH (avisos que sobreviven a un redirect)
   Se usan, por ejemplo, después de "Agregar todo al carrito" o
   "Eliminar lista" en la sección Compra Semanal de Mi cuenta.
   ═══════════════════════════════════════════════════════════ */

function flashSet(string $tipo, string $mensaje): void {
  $_SESSION['flash'][] = ['tipo' => $tipo, 'mensaje' => $mensaje];
}

/** Devuelve todos los mensajes pendientes y los borra de la sesión */
function flashConsumir(): array {
  $mensajes = $_SESSION['flash'] ?? [];
  unset($_SESSION['flash']);
  return $mensajes;
}

/**
 * Consume los mensajes flash pendientes y los devuelve ya dibujados (HTML escapado).
 * Úsala en las páginas a las que redirigen pedido_repetir.php, guardar_compra_semanal.php,
 * etc., para que el aviso se vea justo donde la persona termina y no quede en la sesión
 * hasta aparecer, fuera de contexto, en otra pantalla.
 */
function flashHtml(): string {
  $html = '';
  foreach (flashConsumir() as $flash) {
    $esOk = ($flash['tipo'] ?? '') === 'ok';
    $colores = $esOk
      ? 'background:#e8f5e9;color:#1b5e20;border:1px solid #a5d6a7;'
      : 'background:#fff4e5;color:#8a4b00;border:1px solid #f5c98a;';
    $html .= '<div role="status" style="padding:12px 18px;border-radius:8px;font-size:14px;font-weight:600;margin-bottom:16px;'
           . $colores . '">' . htmlspecialchars((string) ($flash['mensaje'] ?? '')) . '</div>';
  }
  return $html;
}

/* ═══════════════════════════════════════════════════════════
   FORMATO
   ═══════════════════════════════════════════════════════════ */

/** Formatea un número como precio en pesos: 1234.5 → "$1.235" */
function formatoPrecio(float $valor): string {
  // Sin decimales para precios enteros; con 2 decimales si hay centavos
  // (antes "1234,50" se mostraba como "$1.235" y los subtotales no cerraban).
  $decimales = abs($valor - round($valor)) < 0.005 ? 0 : 2;
  return '$' . number_format($valor, $decimales, ',', '.');
}

/** Texto para mostrar el método de pago de un pedido (sitio público). */
function etiquetaMetodoPagoPublico(?string $metodo): string {
  return [
    'efectivo'    => 'Efectivo',
    'mercadopago' => 'Mercado Pago',
    'tarjeta'     => 'Tarjeta',
  ][$metodo ?? ''] ?? 'Efectivo';
}

/**
 * Calcula el precio final de un producto aplicándole su descuento.
 * Por ejemplo: precio 1000 con descuento_pct 20 → devuelve 800.
 * Esta es la ÚNICA función que calcula precios con descuento en
 * todo el sitio: la usan productos.php (para mostrar el precio en
 * el catálogo) e includes/carrito.php (para cobrar el precio real
 * en el carrito y en el pedido). Si el descuento es 0, devuelve el
 * precio sin cambios.
 */
function precioConDescuento(float $precio, int $descuentoPct): float {
  if ($descuentoPct <= 0) {
    return $precio;
  }
  $descuentoPct = min(100, $descuentoPct); // por las dudas, nunca más de 100%
  return round($precio - ($precio * $descuentoPct / 100), 2);
}

/* ═══════════════════════════════════════════════════════════
   DATOS DE ENTREGA (checkout del carrito)
   Validaciones chicas para los campos estructurados que se piden
   al finalizar la compra (nombre, apellido, teléfono, calle,
   número, barrio). No son validaciones "de laboratorio": alcanza
   con rechazar los casos obviamente inválidos (vacío, puro texto
   pegado, etc.) sin volverse tan estrictas que rechacen datos
   reales de gente real.
   ═══════════════════════════════════════════════════════════ */

/**
 * Un teléfono válido: solo dígitos, espacios, guiones o "+", y entre
 * 8 y 20 caracteres en total. Cubre formatos como "11 1234-5678",
 * "+54 9 3777 123456", etc. sin exigir un formato único.
 */
function validarTelefono(string $telefono): bool {
  return (bool) preg_match('/^[0-9+\s-]{8,20}$/', trim($telefono));
}

/**
 * Validación genérica para nombre, apellido, calle y barrio: no
 * vacío, largo razonable, y con al menos una letra (para rechazar
 * cosas como "123" o "..." puestas solo para pasar el "required").
 */
function validarTextoEntrega(string $texto, int $min = 2, int $max = 100): bool {
  $texto = trim($texto);
  $largo = mb_strlen($texto);
  if ($largo < $min || $largo > $max) {
    return false;
  }
  return (bool) preg_match('/\p{L}/u', $texto); // al menos una letra
}

/**
 * El número de la calle es más laxo (puede tener "1234", "1234 B",
 * "s/n", etc.), así que solo pedimos que no esté vacío y no sea
 * absurdamente largo.
 */
function validarNumeroCalle(string $numero): bool {
  $numero = trim($numero);
  $largo = mb_strlen($numero);
  return $largo >= 1 && $largo <= 20;
}

/**
 * Arma la URL de Google Maps para la dirección de entrega de un
 * pedido, usando el mismo esquema de "Maps URLs" que ya se usa en
 * config.php para NEGOCIO_MAPS_URL: no hace falta API key, solo
 * abre Google Maps con una búsqueda de texto. Se le agrega la
 * localidad del negocio para que la búsqueda sea más precisa.
 */
function generarUrlMapsDireccion(string $calle, string $numero, string $barrio): string {
  $direccion = trim($calle . ' ' . $numero . ', ' . $barrio . ', Monte Caseros, Corrientes, Argentina');
  return 'https://www.google.com/maps/search/?api=1&query=' . urlencode($direccion);
}

/**
 * Arma el texto de dirección "de una línea" (para compatibilidad
 * con pedidos viejos, el CSV del admin, etc. que todavía leen el
 * campo pedidos.direccion como un solo string).
 */
function armarDireccionCompleta(string $calle, string $numero, string $barrio, string $referencia = ''): string {
  $direccion = trim($calle) . ' ' . trim($numero) . ', ' . trim($barrio);
  if (trim($referencia) !== '') {
    $direccion .= ' (Ref: ' . trim($referencia) . ')';
  }
  return $direccion;
}

/* ═══════════════════════════════════════════════════════════
   URLs CON FILTROS
   ═══════════════════════════════════════════════════════════ */

/**
 * Arma una URL de listado conservando los filtros actuales y
 * permitiendo sobrescribir alguno puntual (por ejemplo, al cambiar
 * de página o de categoría). La usa productos.php para armar los
 * links del sidebar y de la paginación sin repetir los mismos
 * parámetros una y otra vez.
 */
function urlConFiltros(string $archivo, array $filtrosActuales, array $sobrescribir = []): string {
  $actuales = array_filter($filtrosActuales, fn($v) => $v !== '' && $v !== null);
  $finales  = array_merge($actuales, $sobrescribir);
  return $archivo . '?' . http_build_query($finales);
}

/* ═══════════════════════════════════════════════════════════
   CATÁLOGO DE PRODUCTOS (productos.php)
   ═══════════════════════════════════════════════════════════ */

/**
 * Los criterios de orden que acepta productos.php por la URL
 * (?orden=precio_asc) junto con el SQL "ORDER BY" que le corresponde
 * a cada uno. Si alguien manda un valor que no está en esta lista,
 * productos.php cae de nuevo en "destacados" (ver validarOrden()).
 */
function ordenesValidosProductos(): array {
  return [
    'destacados'  => 'p.destacado DESC, p.id ASC',
    'precio_asc'  => 'p.precio ASC',
    'precio_desc' => 'p.precio DESC',
    'az'          => 'p.nombre ASC',
  ];
}

/** Si $orden no es uno de los criterios válidos, devuelve "destacados" por defecto */
function validarOrden(string $orden): string {
  return array_key_exists($orden, ordenesValidosProductos()) ? $orden : 'destacados';
}

/**
 * Trae todas las categorías junto con la cantidad de productos
 * activos que tiene cada una, para pintar el sidebar de productos.php
 * con los números reales (no "inventados").
 */
function obtenerCategoriasPublico(PDO $pdo): array {
  return $pdo->query("
    SELECT c.id, c.nombre, c.slug, c.icono,
           COUNT(p.id) AS cantidad
    FROM categorias c
    LEFT JOIN productos p ON p.categoria_id = c.id AND p.activo = 1
    GROUP BY c.id, c.nombre, c.slug, c.icono
    ORDER BY c.orden ASC
  ")->fetchAll();
}

/**
 * Parte lo que escribió la persona en el buscador en palabras sueltas (máximo 5, de hasta 40 letras cada una).
 * "coca  cola 2.25" → ['coca', 'cola', '2.25']. Cada palabra tiene que aparecer en el producto, en cualquier orden.
 */
function terminosBusquedaProductos(string $busqueda): array {
  $palabras = preg_split('/\s+/u', trim($busqueda), -1, PREG_SPLIT_NO_EMPTY) ?: [];
  $terminos = [];
  foreach ($palabras as $palabra) {
    $terminos[] = mb_substr($palabra, 0, 40);
    if (count($terminos) >= 5) break;
  }
  return $terminos;
}

/**
 * Arma el WHERE + parámetros para el catálogo público según la
 * categoría elegida (o "todas") y, si hay, el texto del buscador.
 * Solo se muestran productos activos.
 *
 * Búsqueda: cada palabra debe estar en el nombre del producto o en el de su categoría ("gaseosa" encuentra las
 * bebidas aunque el nombre no lo diga). La base usa utf8mb4_unicode_ci, así que no distingue mayúsculas ni tildes
 * ("rapido" encuentra "Rápido"). Los comodines % y _ que escriba la persona se tratan como texto común
 * (se escapan con "|") y todo va con parámetros preparados.
 */
function construirFiltroProductosPublico(string $categoriaSlug, string $busqueda = ''): array {
  $where  = 'WHERE p.activo = 1';
  $params = [];

  if ($categoriaSlug !== 'todas') {
    $where .= ' AND c.slug = :slug';
    $params[':slug'] = $categoriaSlug;
  }

  foreach (terminosBusquedaProductos($busqueda) as $i => $termino) {
    $like = '%' . str_replace(['|', '%', '_'], ['||', '|%', '|_'], $termino) . '%';
    $where .= " AND (p.nombre LIKE :bn$i ESCAPE '|' OR c.nombre LIKE :bc$i ESCAPE '|')";
    $params[":bn$i"] = $like;
    $params[":bc$i"] = $like;
  }

  return [$where, $params];
}

/** Cuenta cuántos productos cumplen el filtro (para la paginación) */
function contarProductosPublico(PDO $pdo, string $where, array $params): int {
  $stmt = $pdo->prepare("SELECT COUNT(*) FROM productos p JOIN categorias c ON c.id = p.categoria_id $where");
  $stmt->execute($params);
  return (int) $stmt->fetchColumn();
}

/**
 * Trae la página de productos que le toca ver al cliente, ya
 * ordenados según el criterio elegido (destacados/precio/A-Z).
 * Si hay un usuario logueado ($usuarioId), sus productos favoritos
 * van SIEMPRE primero (en todas las páginas), y dentro de cada grupo
 * (favoritos / resto) rige el orden elegido.
 */
function obtenerProductosPublico(PDO $pdo, string $where, array $params, string $ordenSql, int $limite, int $offset, ?int $usuarioId = null): array {
  $conFavoritos = $usuarioId !== null && $usuarioId > 0;
  $joinFav  = $conFavoritos ? 'LEFT JOIN favoritos fav ON fav.producto_id = p.id AND fav.usuario_id = :uid_fav' : '';
  $ordenFav = $conFavoritos ? '(fav.producto_id IS NULL) ASC, ' : '';

  $sql = "
    SELECT p.id, p.nombre, p.precio, p.descuento_pct, p.emoji, p.imagen, p.stock,
           c.nombre AS categoria_nombre, c.slug AS categoria_slug
    FROM productos p
    JOIN categorias c ON c.id = p.categoria_id
    $joinFav
    $where
    ORDER BY $ordenFav$ordenSql
    LIMIT :limite OFFSET :offset
  ";
  try {
    $stmt = $pdo->prepare($sql);
    foreach ($params as $clave => $valor) {
      $stmt->bindValue($clave, $valor);
    }
    if ($conFavoritos) {
      $stmt->bindValue(':uid_fav', $usuarioId, PDO::PARAM_INT);
    }
    $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
  } catch (PDOException $e) {
    // Si falta la tabla "favoritos" (migración 004 sin importar) el catálogo sigue funcionando, sin prioridad.
    if (!$conFavoritos) {
      throw $e;
    }
    error_log('[el-rapido] catálogo sin favoritos: ' . $e->getMessage());
    return obtenerProductosPublico($pdo, $where, $params, $ordenSql, $limite, $offset, null);
  }
}

/**
 * Foto del negocio con fundido automático noche ↔ día (hero de Login, Registro,
 * Recuperar, Restablecer y Nosotros). El recuadro tiene proporción fija 4:5
 * (no se estira con el alto de la página) y el ciclo lo maneja assets/js/global.js.
 * Los estilos están en global.css (.foto-hero--ciclo).
 */
function renderFotoHeroCiclo(bool $conFondo = true): void {
  ?>
  <div class="foto-hero foto-hero--ciclo">
    <?php if ($conFondo): ?><div class="foto-hero-fondo"></div><?php endif; ?>
    <div class="foto-hero-marco foto-hero-marco--activa">
      <picture><source srcset="assets/img/hero-foto-640.webp 640w, assets/img/hero-foto.webp 1122w" sizes="(max-width: 900px) 100vw, 520px" type="image/webp"><img src="assets/img/hero-foto.jpg" srcset="assets/img/hero-foto-640.jpg 640w, assets/img/hero-foto.jpg 1122w" sizes="(max-width: 900px) 100vw, 520px" alt="Frente del Maxikiosco El Rápido de noche" class="foto-hero-img" width="1122" height="1402" fetchpriority="high" decoding="async"></picture>
      <span class="foto-hero-etiqueta"><?= icono('luna', '', 13) ?> De noche</span>
      <div class="foto-hero-duotono"></div>
    </div>
    <div class="foto-hero-marco">
      <picture><source srcset="assets/img/hero-foto-2-640.webp 640w, assets/img/hero-foto-2.webp 1122w" sizes="(max-width: 900px) 100vw, 520px" type="image/webp"><img src="assets/img/hero-foto-2.jpg" srcset="assets/img/hero-foto-2-640.jpg 640w, assets/img/hero-foto-2.jpg 1122w" sizes="(max-width: 900px) 100vw, 520px" alt="Frente del Maxikiosco El Rápido de día" class="foto-hero-img" width="1122" height="1402" loading="lazy" decoding="async"></picture>
      <span class="foto-hero-etiqueta"><?= icono('sol', '', 13) ?> De día</span>
      <div class="foto-hero-duotono"></div>
    </div>
  </div>
  <?php
}


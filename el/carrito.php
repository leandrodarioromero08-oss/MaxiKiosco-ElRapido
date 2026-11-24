<?php
// ════════════════════════════════════════════════════════════════
//  carrito.php — PÁGINA DEL CARRITO DE COMPRAS
// ════════════════════════════════════════════════════════════════
//  Este archivo hace TRES cosas, en este orden:
//
//    1) Procesa las acciones sobre el carrito (sumar/restar cantidad
//       y eliminar productos o promociones).
//    2) Procesa "Finalizar compra": validaciones + transacción que
//       descuenta stock y crea el pedido en la base de datos.
//    3) Dibuja el HTML. Según el caso muestra:
//         - la pantalla de "pedido confirmado" (con ?confirmado=ID),
//         - el carrito vacío, o
//         - la tabla de productos + el resumen con el formulario.   
//
//  REGLA CLAVE: toda la lógica PHP va ANTES del HTML, porque la lógica
//  puede redirigir con header('Location: ...') y eso solo funciona si
//  todavía no se imprimió nada en pantalla.
//
//  IDEA DE SEGURIDAD: el navegador nunca decide precios ni stock. En la
//  sesión solo se guardan ids y cantidades; todo lo demás se recalcula
//  desde la base de datos en cada carga.
// Variables que lee includes/header.php para saber qué hojas de estilo
// cargar en esta página (carrito.css y promo-publico.css).
$pageCss = 'carrito.css';
$pageCssExtra = ['promo-publico.css'];
// Los requires de funciones/DB van ANTES de cualquier lógica que pueda
// redirigir con header('Location: ...') (cambiar cantidad, eliminar,
// finalizar compra, etc.). includes/header.php recién se incluye más
// abajo, después de toda esa lógica, porque ese archivo ya imprime HTML
// (la barra de navegación) — y una vez que hay output no se pueden
// mandar más headers HTTP.
// ── Dependencias ──────────────────────────────────────────────
// config.php               → constantes (RECARGO_DELIVERY, etc.). Al final
//                            carga seguridad.php automáticamente.
// seguridad.php            → CSRF (csrfCampo, csrfExigir), postTexto(),
//                            limitarCantidad(), esRutaLocalSegura().
// db.php                   → obtenerConexion() (conexión PDO a la base).
// includes/carrito.php     → el "motor" del carrito (agregar, eliminar,
//                            carritoDetalle()).
// funciones.php            → helpers: formatoPrecio, precioConDescuento,
//                            mensajes flash, validaciones de teléfono y
//                            dirección, usuarioLogueado, links a Google Maps.
// compra_semanal.php       → listas de "Compra Semanal" del usuario.
// promociones_funciones.php→ promos (2x1, 3x2...), stock y descuento atómico.
// require_once carga cada archivo UNA sola vez, aunque otro ya lo incluya.
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/carrito.php';
require_once 'includes/funciones.php';
require_once 'includes/compra_semanal.php';
require_once 'includes/promociones_funciones.php';
// Protección CSRF: si la petición es POST y no trae un token válido,
// corta con error 403. Con GET no hace nada. Va acá arriba para que
// NINGUNA acción de abajo se procese sin validar el token.
csrfExigir(); // protección CSRF: rechaza cualquier POST sin token válido

// RECARGO_DELIVERY se define en includes/config.php (un solo lugar).

// $pdo = conexión a la base (se usa en casi todo el archivo).
// $logueado = true/false: ¿hay un usuario con sesión iniciada?
// Se guarda en una variable para no consultar la sesión varias veces.
$pdo = obtenerConexion();
$logueado = usuarioLogueado();

/* ═══════════════════════════════════════════════════════════
   1) ACCIONES SOBRE EL CARRITO (cambiar cantidad / eliminar)
   ═══════════════════════════════════════════════════════════ */
// Las 4 acciones de este bloque siguen el MISMO patrón
// (Post-Redirect-Get):  recibir POST → validar → modificar la sesión →
// redirigir a carrito.php y hacer exit. Así, si el usuario refresca la
// página, el navegador NO reenvía la acción (no se suma dos veces).
//
// ACCIÓN 'cambiar': sumar (+1) o restar (-1) una unidad de un producto.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'cambiar') {
  // (int) fuerza a número entero: si llega texto raro, queda en 0.
  $productoId = (int) ($_POST['producto_id'] ?? 0);
  // $delta es cuánto cambiar (+1 o -1). max/min lo limitan al rango
  // -99..99 por si alguien manipula el formulario y manda delta=999999.
  $delta = max(-CANTIDAD_MAXIMA_POR_LINEA, min(CANTIDAD_MAXIMA_POR_LINEA, (int) ($_POST['delta'] ?? 0)));
  // Cantidad que ya hay en el carrito (o 0 si el producto no está).
  $actual = $_SESSION['carrito'][$productoId] ?? 0;
  // Esta acción solo MODIFICA líneas existentes. Si el producto no está
  // en el carrito, no hace nada y vuelve.
  if (!isset($_SESSION['carrito'][$productoId])) {
    // No se agregan productos nuevos desde acá (solo se modifican líneas existentes)
    header('Location: carrito.php');
    exit;
  }
  // Se consulta el stock REAL a la base (consulta preparada: el id viaja
  // separado del SQL, lo que evita inyección SQL). Solo productos activos.
  $stmtStock = $pdo->prepare('SELECT stock FROM productos WHERE id = :id AND activo = 1');
  $stmtStock->execute([':id' => $productoId]);
  $stockActual = $stmtStock->fetchColumn();
  // fetchColumn() devuelve false si no encontró fila: el producto ya no
  // existe o fue desactivado, así que se saca del carrito.
  if ($stockActual === false) {
    carritoEliminar($productoId); // ya no existe o está desactivado
  } else {
    // Si se pide MÁS, se recorta al stock; si se baja, siempre se permite
    // Ojo con el último parámetro: al SUBIR se pasa el stock como tope, y al
    // BAJAR se pasa null (sin tope) para que siempre se pueda reducir.
    $nueva = $actual + $delta;
    carritoActualizarCantidad($productoId, $nueva, $delta > 0 ? (int) $stockActual : null);
  }
  header('Location: carrito.php');
  exit;
}

// ACCIÓN 'eliminar': quita un producto del carrito.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'eliminar') {
  $productoId = (int) ($_POST['producto_id'] ?? 0);
  carritoEliminar($productoId);
  header('Location: carrito.php');
  exit;
}

// ACCIÓN 'cambiar_promo': igual que 'cambiar' pero para promociones.
// Las promos se mueven en "pasos" (2 en un 2x1, 3 en un 3x2, 1 en el resto).
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'cambiar_promo') {
  $promocionId = (int) ($_POST['promocion_id'] ?? 0);
  $delta = max(-CANTIDAD_MAXIMA_POR_LINEA, min(CANTIDAD_MAXIMA_POR_LINEA, (int) ($_POST['delta'] ?? 0)));
  if (!isset($_SESSION['carrito_promos'][$promocionId])) {
    header('Location: carrito.php');
    exit;
  }
  // Cantidad actual de esa promo en el carrito.
  $promoActualCant = $_SESSION['carrito_promos'][$promocionId];

  // $promoTmp (promo temporal): guarda la promoción COMPLETA tal como está
  // hoy en la base (tipo, productos, fechas...) o null si ya no se puede
  // usar (vencida, pausada o sin productos). En la sesión solo hay id y
  // cantidad, así que hay que ir a buscar el tipo para saber el paso.
  // El paso viene del propio tipo de promo (2 en 2x1, 3 en 3x2, 1 en el resto)
  $promoTmp = obtenerPromocionActivaPorId($pdo, $promocionId);
  if (!$promoTmp) {
    carritoEliminarPromo($promocionId); // vencida, pausada o sin productos
  } else {
    // pasoCantidadPromo(tipo): 2 para '2x1', 3 para '3x2', 1 para el resto.
    $paso = pasoCantidadPromo($promoTmp['tipo']);
    // El delta se multiplica por el paso: un clic en + en un 2x1 suma 2.
    $nueva = $promoActualCant + ($delta * $paso);
    if ($delta > 0) {
      // Al subir, tope = stock real y múltiplo correcto de la promo
      $nueva = intdiv(min($nueva, cantidadMaximaPromo($promoTmp, $promoTmp['productos'])), $paso) * $paso;
    }
    carritoActualizarCantidadPromo($promocionId, $nueva);
  }
  header('Location: carrito.php');
  exit;
}

// ACCIÓN 'eliminar_promo': quita una promoción del carrito.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'eliminar_promo') {
  $promocionId = (int) ($_POST['promocion_id'] ?? 0);
  carritoEliminarPromo($promocionId);
  header('Location: carrito.php');
  exit;
}

/* ═══════════════════════════════════════════════════════════
   2) FINALIZAR COMPRA
   ═══════════════════════════════════════════════════════════ */
// $errorCheckout: mensaje de error que se mostrará arriba del carrito
// (null = no hay error). $avisosCheckout: avisos que no son errores.
$errorCheckout = null;
$avisosCheckout = [];

// Datos guardados del usuario (teléfono y dirección de entrega, si los
// cargó al registrarse) para autocompletar el checkout y no hacerlo
// escribir de nuevo lo mismo en cada pedido. Solo se usan para
// completar el formulario recién al mostrarlo (ver más abajo); si el
// usuario corrige un error de validación, el formulario se vuelve a
// mostrar en modo "editar" con lo que haya escrito, no con esto.
$telefonoGuardado = null;
$direccionGuardada = null;
// Si hay usuario logueado, leemos su teléfono y dirección guardados
// para autocompletar el formulario de checkout.
if ($logueado) {
  $stmtPerfil = $pdo->prepare('
    SELECT telefono, direccion_calle, direccion_numero, direccion_barrio, direccion_referencia
    FROM usuarios WHERE id = :id
  ');
  // El id sale de la SESIÓN, no de un campo del formulario: así nadie
  // puede pedir el perfil de otro usuario.
  $stmtPerfil->execute([':id' => $_SESSION['usuario']['id']]);
  $perfil = $stmtPerfil->fetch();
  if ($perfil) {
    // ?: (operador Elvis) usa el valor de la izquierda y, si está vacío,
    // usa null. Así un teléfono vacío queda como null y no como ''.
    $telefonoGuardado = $perfil['telefono'] ?: null;
    // La dirección solo se considera "guardada" si tiene calle.
    if (!empty($perfil['direccion_calle'])) {
      $direccionGuardada = [
        'calle'      => $perfil['direccion_calle'],
        'numero'     => $perfil['direccion_numero'] ?? '',
        'barrio'     => $perfil['direccion_barrio'] ?? '',
        'referencia' => $perfil['direccion_referencia'] ?? '',
      ];
    }
  }
}
// Solo mostramos el aviso de "dato guardado" en la carga inicial del
// formulario, no cuando se está reintentando después de un error.
// TRES variables que deciden cómo se dibuja el formulario:
//
//  $esReintentoConError → ¿el usuario acaba de enviar "finalizar"?
//    (si la página se está dibujando de nuevo tras un POST, es porque
//    algo falló; si la compra salía bien ya se habría redirigido).
//  $mostrarTelefonoGuardado / $mostrarDireccionGuardada → true solo si
//    NO es un reintento Y existe un dato guardado.
//
// Por qué: en la primera carga se autocompleta con el dato guardado
// (bloqueado, con botón "Usar otro"). Pero si el usuario escribió un
// teléfono nuevo y falló otra validación, al volver a mostrar el
// formulario NO hay que pisar lo que escribió con el dato guardado.
$esReintentoConError = $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'finalizar';
$mostrarTelefonoGuardado = !$esReintentoConError && $telefonoGuardado;
$mostrarDireccionGuardada = !$esReintentoConError && $direccionGuardada;

// ─── Entrada al checkout ─────────────────────────────────────
// Todo lo que sigue se ejecuta solo si el usuario apretó
// "FINALIZAR COMPRA" (POST con accion = finalizar).
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'finalizar') {

  // Sin sesión no se puede comprar: se manda al login y, al terminar,
  // vuelve al carrito (?volver=carrito.php).
  if (!$logueado) {
    header('Location: login.php?volver=carrito.php');
    exit;
  }

  // El nombre de quien recibe el pedido ya no se pide en un campo del
  // formulario: usamos directamente el nombre de la cuenta con la que
  // inició sesión (así evitamos pedirle el mismo dato dos veces).
  // trim() saca espacios al principio y al final.
  $entregaNombre   = trim($_SESSION['usuario']['nombre'] ?? '');
  $entregaApellido = null;
  // postTexto('campo'): lee $_POST['campo'], le saca los espacios y
  // devuelve '' si no vino o si alguien mandó un array (campo[]=x),
  // lo que evitaría errores fatales en trim()/strlen().
  $telefono        = postTexto('telefono');
  // LISTA BLANCA: solo se aceptan los valores permitidos. Si alguien
  // manipula el formulario y manda tipo_entrega=otracosa, se usa 'retiro'.
  // El true de in_array activa la comparación estricta (valor y tipo).
  $tipoEntrega = $_POST['tipo_entrega'] ?? 'retiro';
  if (!in_array($tipoEntrega, ['retiro', 'delivery'], true)) {
    $tipoEntrega = 'retiro';
  }
  // Campos de la dirección (se leen siempre, pero solo se validan y se
  // guardan si el tipo de entrega es delivery).
  $entregaCalle      = postTexto('entrega_calle');
  $entregaNumero     = postTexto('entrega_numero');
  $entregaBarrio     = postTexto('entrega_barrio');
  $entregaReferencia = postTexto('entrega_referencia');
  // Otra lista blanca, con 'efectivo' como valor por defecto.
  $metodoPago = $_POST['metodo_pago'] ?? 'efectivo';
  if (!in_array($metodoPago, ['efectivo', 'mercadopago', 'tarjeta'], true)) {
    $metodoPago = 'efectivo';
  }

  // El recargo lo decide el SERVIDOR (constante RECARGO_DELIVERY):
  // el navegador nunca manda cuánto cobrar de envío.
  $recargoEnvio = $tipoEntrega === 'delivery' ? RECARGO_DELIVERY : 0;

  // Token de un solo uso: se consume al confirmar el pedido. Evita pedidos duplicados
  // por doble clic, reenvío del formulario o dos pestañas, y evita mostrar
  // "carrito vacío" cuando en realidad el pedido ya se había creado.
  $tokenRecibido = $_POST['token_checkout'] ?? '';
  // $tokenValido es true solo si se cumplen las 5 condiciones (&&):
  //   1. lo recibido es un texto (no un array),
  //   2. no está vacío,
  //   3. el servidor tiene un token guardado en la sesión,
  //   4. ese token guardado también es texto,
  //   5. ambos son idénticos según hash_equals().
  // hash_equals compara en tiempo constante (evita ataques que adivinan
  // el token midiendo cuánto tarda la comparación). Las condiciones 1 a 4
  // van ANTES porque hash_equals da error fatal si recibe algo que no sea
  // texto; el && corta en la primera que falla.
  $tokenValido = is_string($tokenRecibido) && $tokenRecibido !== ''
    && isset($_SESSION['checkout_token']) && is_string($_SESSION['checkout_token'])
    && hash_equals($_SESSION['checkout_token'], $tokenRecibido);

  // La dirección "de una línea" se sigue armando y guardando por
  // compatibilidad con el admin viejo, el CSV, etc. La fuente de
  // verdad ahora son los campos estructurados (calle/número/barrio).
  $direccion = $tipoEntrega === 'delivery'
    ? armarDireccionCompleta($entregaCalle, $entregaNumero, $entregaBarrio, $entregaReferencia)
    : '';

  // ─── Validaciones ───────────────────────────────────────────
  // Cadena if/elseif: el PRIMER error que aparece se guarda y los demás
  // ya no se evalúan. Orden: token → teléfono → calle → número → barrio
  // → largo de la referencia. (La dirección solo se valida en delivery.)
  if (!$tokenValido) {
    $errorCheckout = 'Este pedido ya fue procesado o la página venció. Revisá tu historial en Mi cuenta antes de volver a comprar.';
  } elseif (!validarTelefono($telefono)) {
    $errorCheckout = 'Ingresá un teléfono de contacto válido (solo números, espacios o guiones, entre 8 y 20 caracteres).';
  } elseif ($tipoEntrega === 'delivery' && !validarTextoEntrega($entregaCalle, 2, 150)) {
    $errorCheckout = 'Ingresá la calle donde querés recibir tu pedido.';
  } elseif ($tipoEntrega === 'delivery' && !validarNumeroCalle($entregaNumero)) {
    $errorCheckout = 'Ingresá el número (o "s/n" si la calle no tiene numeración).';
  } elseif ($tipoEntrega === 'delivery' && !validarTextoEntrega($entregaBarrio, 2, 100)) {
    $errorCheckout = 'Ingresá el barrio donde querés recibir tu pedido.';
  } elseif ($tipoEntrega === 'delivery' && mb_strlen($entregaReferencia) > 255) {
    // Mismo límite que la columna entrega_referencia (VARCHAR 255): sin esto MySQL
    // rechaza el INSERT y el cliente no puede terminar la compra.
    $errorCheckout = 'La referencia de entrega es demasiado larga (máximo 255 caracteres).';
  }

  // Recalcula el carrito desde la base de datos (precios, descuentos,
  // promos vigentes, stock). Esta es la verdad del servidor.
  $detalle = carritoDetalle($pdo);
  // Los avisos (promo vencida, cantidad ajustada, producto quitado) se guardan para
  // mostrarlos al volver a dibujar el carrito: la limpieza ya modificó la sesión.
  $avisosCheckout = $detalle['avisos'];

  // El total que el cliente vio al enviar el formulario debe coincidir con el recalculado
  // en el servidor; si no, NO se crea el pedido: se le muestra el carrito actualizado.
  // $subtotalEsperado = el subtotal que el cliente VIO en pantalla (viene
  // en un campo oculto). Si no llegó o no es un número válido, queda null.
  // round(..., 2) lo deja con 2 decimales.
  $subtotalEsperado = isset($_POST['subtotal_esperado']) && is_numeric($_POST['subtotal_esperado'])
    ? round((float) $_POST['subtotal_esperado'], 2) : null;
  // $carritoCambio = true si el carrito cambió mientras el cliente decidía.
  // Tiene la forma  A && (B || C || D):
  //   A: hay productos en el carrito.
  //   B: carritoDetalle generó avisos (algo se quitó o se ajustó).
  //   C: no se pudo leer el subtotal que el cliente vio.
  //   D: la diferencia entre lo que vio y lo real supera 0.009.
  // Se usa 0.009 y no una comparación exacta porque los decimales de
  // computadora son imprecisos (0.1 + 0.2 no da exactamente 0.3).
  $carritoCambio = !empty($detalle['items']) && (
    !empty($detalle['avisos']) || $subtotalEsperado === null || abs($subtotalEsperado - $detalle['subtotal']) > 0.009
  );

  // Tres ramas (solo se ejecuta UNA). El !$errorCheckout de cada una
  // significa "solo si todavía no hubo ningún error": el primero gana.
  //   1) carrito vacío  2) el carrito cambió  3) todo OK → controlar stock.
  if (!$errorCheckout && empty($detalle['items'])) {
    $errorCheckout = 'Tu carrito está vacío.';
  } elseif (!$errorCheckout && $carritoCambio) {
    $errorCheckout = 'Tu carrito cambió desde la última vez que lo viste (precio, promoción o stock). Revisá el total actualizado y confirmá de nuevo.';
  } elseif (!$errorCheckout) {
    // Control previo (para dar un mensaje claro): se compara la demanda TOTAL
    // por producto —línea normal + promociones que lo incluyen— contra el stock.
    // Antes cada línea se validaba por separado, y el mismo producto en dos
    // líneas podía superar el stock real.
    // demandaPorProducto suma cuántas unidades de CADA producto real
    // necesita el carrito, contando líneas normales + promociones.
    // Ej.: Coca x2 (línea normal) + Coca x4 (en un 2x1) = 6 en total.
    // Con stock 5, controlar cada línea por separado pasaría (2 y 4 ≤ 5),
    // pero en conjunto no alcanza. También ordena por id (ksort) para
    // evitar bloqueos cruzados entre compras simultáneas (deadlocks).
    $demanda = demandaPorProducto($detalle['items']);
    // Lista de nombres de productos cuyo stock no alcanza.
    $sinStock = [];
    foreach ($demanda as $d) {
      if ($d['cantidad'] > $d['stock']) {
        $sinStock[] = $d['nombre'];
      }
    }

    // Si falta stock se muestra un mensaje con todos los productos
    // afectados. Si no, empieza la transacción.
    if (!empty($sinStock)) {
      $errorCheckout = 'No hay stock suficiente para: ' . implode(', ', $sinStock) . '. Ajustá la cantidad para continuar.';
    } else {
      // try/catch: cualquier error dentro del try salta a los catch de abajo.
      try {
        // TRANSACCIÓN: stock + pedido + items se guardan TODOS juntos o
        // NINGUNO. beginTransaction() = "empiezo, nada es definitivo todavía";
        // commit() = guardar todo; rollBack() = deshacer todo.
        $pdo->beginTransaction();

        // 1) STOCK PRIMERO, de forma atómica ("stock >= cantidad" dentro del
        //    propio UPDATE) y en orden por id. Si otra compra se llevó las
        //    unidades entre el control de arriba y este punto, lanza
        //    StockInsuficienteException y no se guarda nada.
        // descontarStockProducto ejecuta un UPDATE con la condición
        // "AND stock >= cantidad" adentro. Eso lo hace atómico: si dos clientes
        // compran la última unidad a la vez, a uno el UPDATE no le modifica
        // ninguna fila y se lanza StockInsuficienteException.
        foreach ($demanda as $productoId => $d) {
          descontarStockProducto($pdo, (int) $productoId, (int) $d['cantidad'], $d['nombre']);
        }

        // Total final = total del carrito (calculado por el servidor) + envío.
        $totalConEnvio = $detalle['total'] + $recargoEnvio;

        // INSERT del pedido con parámetros con nombre (:telefono, :total...).
        // El SQL y los valores viajan separados → sin inyección SQL.
        // El estado inicial de todo pedido es "pendiente".
        $stmtPedido = $pdo->prepare('
          INSERT INTO pedidos (
            usuario_id, telefono_contacto, entrega_nombre, entrega_apellido,
            tipo_entrega, direccion, entrega_calle, entrega_numero, entrega_barrio, entrega_referencia,
            recargo_envio, metodo_pago, estado, total
          )
          VALUES (
            :usuario_id, :telefono, :entrega_nombre, :entrega_apellido,
            :tipo_entrega, :direccion, :entrega_calle, :entrega_numero, :entrega_barrio, :entrega_referencia,
            :recargo, :metodo_pago, "pendiente", :total
          )
        ');
        // Cada :clave se vincula con su valor. Si el pedido es retiro, los
        // campos de dirección se guardan como NULL (así no queda basura si el
        // cliente había escrito una dirección y después cambió a retiro).
        // mb_substr corta la dirección a 500 caracteres (tamaño de la columna).
        $stmtPedido->execute([
          ':usuario_id'         => $_SESSION['usuario']['id'],
          ':telefono'           => $telefono,
          ':entrega_nombre'     => $entregaNombre,
          ':entrega_apellido'   => $entregaApellido,
          ':tipo_entrega'       => $tipoEntrega,
          ':direccion'          => $tipoEntrega === 'delivery' ? mb_substr($direccion, 0, 500) : null,
          ':entrega_calle'      => $tipoEntrega === 'delivery' ? $entregaCalle : null,
          ':entrega_numero'     => $tipoEntrega === 'delivery' ? $entregaNumero : null,
          ':entrega_barrio'     => $tipoEntrega === 'delivery' ? $entregaBarrio : null,
          ':entrega_referencia' => ($tipoEntrega === 'delivery' && $entregaReferencia !== '') ? $entregaReferencia : null,
          ':recargo'            => $recargoEnvio,
          ':metodo_pago'        => $metodoPago,
          ':total'              => $totalConEnvio,
        ]);
        // ID que la base le asignó al pedido recién insertado (AUTO_INCREMENT).
        // Se necesita para vincular los items con este pedido.
        $pedidoId = (int) $pdo->lastInsertId();

        // La consulta de items se PREPARA una sola vez y se ejecuta una vez
        // por cada línea del carrito (más eficiente).
        $stmtItem = $pdo->prepare('
          INSERT INTO pedido_items (pedido_id, producto_id, promocion_id, nombre_producto, emoji, precio_unitario, cantidad, subtotal)
          VALUES (:pedido_id, :producto_id, :promocion_id, :nombre, :emoji, :precio, :cantidad, :subtotal)
        ');

        // Recorre las líneas del carrito:
        //  - Promo → insertarLineaPromoEnPedido la descompone en filas por
        //    producto real (el combo reparte el precio; 2x1/3x2 = una fila).
        //  - Producto normal → una fila con el precio YA con descuento.
        // Se guardan nombre y precio tal como estaban al comprar: si mañana
        // cambian, el pedido histórico no se altera.
        foreach ($detalle['items'] as $item) {
          if (!empty($item['es_promo'])) {
            insertarLineaPromoEnPedido($pdo, $stmtItem, $pedidoId, $item);
            continue;
          }
          $stmtItem->execute([
            ':pedido_id'    => $pedidoId,
            ':producto_id'  => $item['id'],
            ':promocion_id' => null, // compra de producto normal: no se acredita a ninguna promo
            ':nombre'       => mb_substr($item['nombre'], 0, 150),
            ':emoji'        => $item['emoji'],
            ':precio'       => $item['precio'],
            ':cantidad'     => $item['cantidad'],
            ':subtotal'     => $item['subtotal'],
          ]);
        }

        // Todo salió bien, en este orden:
        //  1. commit()  → se guarda definitivamente en la base.
        //  2. carritoVaciar() → se vacía el carrito (DESPUÉS del commit: si la
        //     base falla, el cliente conserva su carrito y puede reintentar).
        //  3. se destruye el token → un reenvío ya no crea otro pedido.
        //  4. redirige a la pantalla de confirmación.
        $pdo->commit();
        carritoVaciar();
        unset($_SESSION['checkout_token']); // token consumido: un reenvío ya no crea otro pedido
        header('Location: carrito.php?confirmado=' . $pedidoId);
        exit;
      // Error ESPERABLE: otro cliente se llevó el stock antes. Se deshace
      // todo (rollBack) y se muestra un mensaje claro con el nombre del producto.
      // El token y el carrito se conservan para poder reintentar.
      } catch (StockInsuficienteException $e) {
        if ($pdo->inTransaction()) {
          $pdo->rollBack();
        }
        $errorCheckout = $e->motivo === 'no_disponible'
          ? '"' . $e->producto . '" ya no está disponible. Quitalo del carrito para continuar.'
          : 'Otro cliente se llevó las últimas unidades de: ' . $e->producto . '. Ajustá la cantidad para continuar.';
      // Cualquier OTRO error (base caída, columna inexistente...). Se deshace
      // todo; el detalle técnico va al log del servidor (error_log) y al
      // cliente solo se le muestra un mensaje genérico, sin filtrar detalles
      // internos.
      } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
          $pdo->rollBack();
        }
        error_log('[el-rapido] checkout: ' . $e->getMessage());
        $errorCheckout = 'Ocurrió un error al procesar tu pedido. Probá de nuevo.';
      }
    }
  }
}

/* ═══════════════════════════════════════════════════════════
   3) ¿VENIMOS DE UNA COMPRA CONFIRMADA?
   ═══════════════════════════════════════════════════════════ */
// Variables para la pantalla de "pedido confirmado" y el modal de
// Compra Semanal. Arrancan vacías y se llenan solo si venimos de comprar.
$pedidoConfirmado = null;
$itemsConfirmado = [];
$preguntarCompraSemanal = false;
$comprasExistentes = [];
// SEGURIDAD: el resumen trae nombre, teléfono y dirección, así que solo se
// muestra si hay sesión iniciada Y el pedido pertenece a ese usuario.
// Antes cualquiera podía recorrer ?confirmado=1,2,3... y ver pedidos ajenos.
// ?confirmado=ID en la URL + usuario logueado → buscamos ese pedido.
if (isset($_GET['confirmado']) && $logueado) {
  $idConfirmado = (int) $_GET['confirmado'];
  // El pedido se muestra SOLO a su dueño: sin "AND usuario_id", cualquiera podía ver
  // nombre, teléfono y dirección de cualquier pedido cambiando el número de la URL.
  $stmt = $pdo->prepare('
    SELECT id, total, tipo_entrega, direccion, recargo_envio, creado_en, metodo_pago,
           telefono_contacto, entrega_nombre, entrega_apellido,
           entrega_calle, entrega_numero, entrega_barrio, entrega_referencia
    FROM pedidos WHERE id = :id AND usuario_id = :uid
  ');
  $stmt->execute([':id' => $idConfirmado, ':uid' => (int) $_SESSION['usuario']['id']]);
  $pedidoConfirmado = $stmt->fetch();
  $itemsConfirmado = [];
  if ($pedidoConfirmado) {
    // Items del pedido, para mostrarlos en la pantalla de confirmación.
    $stmtIt = $pdo->prepare('SELECT nombre_producto, cantidad, subtotal FROM pedido_items WHERE pedido_id = :id ORDER BY id ASC');
    $stmtIt->execute([':id' => $idConfirmado]);
    $itemsConfirmado = $stmtIt->fetchAll();
  }

  // Solo preguntamos una vez por pedido, y solo si el pedido es de este usuario
  if ($pedidoConfirmado && $logueado && empty($_SESSION['cs_preguntado_' . $idConfirmado])) {
    $preguntarCompraSemanal = true;
    $comprasExistentes = obtenerComprasSemanales($pdo, (int) $_SESSION['usuario']['id']);
  }
}
// ¿El usuario todavía puede crear una lista nueva o ya llegó al máximo?
$puedeCrearNuevaCS = count($comprasExistentes) < MAX_COMPRAS_SEMANALES;

// Se recalcula el carrito OTRA vez: la primera vez fue para validar el
// checkout; esta refleja el estado final para dibujar la pantalla.
$detalle = carritoDetalle($pdo);
if (!empty($avisosCheckout)) {
  // Avisos generados al intentar finalizar (la limpieza ya se hizo antes de redibujar)
  $detalle['avisos'] = array_values(array_unique(array_merge($avisosCheckout, $detalle['avisos'])));
}
// Token de un solo uso para el próximo envío de "Finalizar compra"
// bin2hex(random_bytes(16)) = 32 caracteres hexadecimales aleatorios
// y criptográficamente seguros. Solo se crea si aún no existe: se reutiliza
// mientras no se consuma. Viaja al navegador en un campo oculto del
// formulario y se compara de vuelta al finalizar.
if (empty($_SESSION['checkout_token']) || !is_string($_SESSION['checkout_token'])) {
  $_SESSION['checkout_token'] = bin2hex(random_bytes(16));
}

// Ya pasamos toda la lógica que podía redirigir (cambiar/eliminar del
// carrito, finalizar compra). Recién acá incluimos el header: a partir
// de este punto empieza el HTML de la página.
$resumenCarrito = $detalle; // el header reutiliza este cálculo (evita repetir las consultas del carrito)
// Datos para el <head> (título, descripción y robots) que lee el header.
// 'noindex' = esta pantalla es privada y no debe aparecer en buscadores.
$pageTitle = $pedidoConfirmado ? 'Pedido confirmado' : 'Carrito de compras';
$pageDescription = 'Revisá los productos de tu carrito y finalizá tu pedido en El Rápido.';
$pageRobots = 'noindex,follow'; // pantalla privada del cliente
// Recién ahora se imprime HTML (barra de navegación).
include 'includes/header.php';
require_once 'includes/iconos.php';
?>

<?php /*
   Contenedor principal de toda la página del carrito.
   ESTRUCTURA GENERAL del HTML (3 situaciones posibles):
     if pedido confirmado  → [modal Compra Semanal] + [pedido confirmado]
     else
         título + alertas
         if carrito vacío   → mensaje "Tu carrito está vacío"
         else               → [tabla de productos] + [resumen y formulario]
         beneficios (las 4 tarjetas de abajo)
*/ ?>
<div class="carrito-wrap">

  <?php /*
     Mensajes flash pendientes (por ejemplo, el resultado de guardar
     una Compra Semanal).
  */ ?>
  <?= flashHtml() /* avisos pendientes (ej: resultado de "Guardar como Compra Semanal") */ ?>

  <?php /*
     SITUACIÓN A: venimos de una compra confirmada (?confirmado=ID).
  */ ?>
  <?php if ($pedidoConfirmado): ?>

    <?php /*
       El modal solo se dibuja si corresponde preguntar (una vez por pedido).
    */ ?>
    <?php if ($preguntarCompraSemanal): ?>
    <!-- ═══════════ MODAL: ¿GUARDAR COMO COMPRA SEMANAL? ═══════════ -->
    <?php /*
       Fondo oscuro del modal. role="dialog" y aria-modal="true" son
       atributos de accesibilidad: avisan a los lectores de pantalla que es
       una ventana modal.
    */ ?>
    <div class="modal-overlay" id="modal-compra-semanal">
      <div class="modal-compra-semanal" role="dialog" aria-modal="true" aria-labelledby="modal-cs-titulo" tabindex="-1">
        <div class="modal-cs-icono"><?= icono('calendario', '', 28) ?></div>
        <h2 class="modal-cs-titulo" id="modal-cs-titulo">¿Deseás guardar este pedido como una Compra Semanal para volver a pedirlo cuando quieras?</h2>

        <?php /*
           Formulario 1: GUARDAR. Va a guardar_compra_semanal.php con
           decision=guardar. El pedido_id oculto indica qué pedido se guarda.
        */ ?>
        <form method="POST" action="guardar_compra_semanal.php" id="form-guardar-cs">
<?php /*
   Token CSRF oculto (lo valida csrfExigir en el servidor).
*/ ?>
<?= csrfCampo() ?>
          <input type="hidden" name="pedido_id" value="<?= (int) $pedidoConfirmado['id'] ?>">
          <input type="hidden" name="decision" value="guardar">

          <?php /*
             Si el usuario ya tiene listas, se ofrece elegir dónde guardar:
             crear una nueva (si no llegó al máximo) o reemplazar una existente.
          */ ?>
          <?php if (!empty($comprasExistentes)): ?>
            <div class="modal-cs-campo">
              <label for="modal-cs-destino">¿Dónde la guardamos?</label>
              <select id="modal-cs-destino" name="compra_id">
                <?php if ($puedeCrearNuevaCS): ?>
                  <option value="">+ Crear una lista nueva</option>
                <?php endif; ?>
                <?php foreach ($comprasExistentes as $lista): ?>
                  <option value="<?= (int) $lista['id'] ?>">Reemplazar "<?= htmlspecialchars($lista['nombre']) ?>"</option>
                <?php endforeach; ?>
              </select>
              <?php if (!$puedeCrearNuevaCS): ?>
                <p class="modal-cs-aviso-limite">Ya tenés <?= MAX_COMPRAS_SEMANALES ?> Compras Semanales guardadas (el máximo). Elegí cuál actualizar con este pedido.</p>
              <?php endif; ?>
            </div>
          <?php endif; ?>

          <?php /*
             Nombre de la lista. El JS lo oculta si se elige reemplazar una existente.
          */ ?>
          <div class="modal-cs-campo" id="modal-cs-nombre-wrap">
            <label for="modal-cs-nombre">Nombre de la lista</label>
            <input type="text" id="modal-cs-nombre" name="nombre" maxlength="100"
                   placeholder="Ej: Compra de los viernes, Compra para la casa">
          </div>

          <div class="modal-cs-botones">
            <button type="submit" class="btn-guardar-compra-modal">Guardar compra</button>
          </div>
        </form>

        <?php /*
           Formulario 2: OMITIR ("No, gracias"). Es un formulario aparte para que
           rechazar sea un solo clic, sin validar el campo nombre.
        */ ?>
        <form method="POST" action="guardar_compra_semanal.php" id="form-omitir-cs">
<?= csrfCampo() ?>
          <input type="hidden" name="pedido_id" value="<?= (int) $pedidoConfirmado['id'] ?>">
          <input type="hidden" name="decision" value="omitir">
          <button type="submit" class="btn-no-gracias-modal">No, gracias</button>
        </form>
      </div>
    </div>
    <?php /*
       JS del modal (foco, validación del nombre). Se carga solo si el
       modal existe. defer = se ejecuta cuando el HTML ya está listo.
    */ ?>
    <script src="<?= htmlspecialchars(assetUrl('assets/js/carrito-modal-cs.js')) ?>" defer></script>
    <?php endif; ?>

    <!-- ═══════════ PEDIDO CONFIRMADO ═══════════ -->
    <?php /*
       Resumen del pedido recién creado, leído de la base de datos.
       Todo valor dinámico pasa por htmlspecialchars() (evita XSS) y los
       números por (int) o (float).
    */ ?>
    <div class="pedido-confirmado">
      <div class="pedido-confirmado-icono"><?= icono('check-circulo', '', 40) ?></div>
      <h1>¡Gracias por tu compra!</h1>
      <p>Tu pedido fue registrado correctamente.</p>
      <p class="pedido-confirmado-numero">Pedido #<?= (int) $pedidoConfirmado['id'] ?></p>
      <?php /*
         Arma "nombre apellido" para mostrar (el apellido puede ser null).
      */ ?>
      <?php
        $nombreCompletoEntrega = trim(($pedidoConfirmado['entrega_nombre'] ?? '') . ' ' . ($pedidoConfirmado['entrega_apellido'] ?? ''));
      ?>
      <?php if ($nombreCompletoEntrega !== ''): ?>
        <p class="pedido-confirmado-linea"><?= icono('usuario', '', 16) ?> A nombre de: <strong><?= htmlspecialchars($nombreCompletoEntrega) ?></strong></p>
      <?php endif; ?>
      <?php if (!empty($pedidoConfirmado['telefono_contacto'])): ?>
        <p class="pedido-confirmado-linea"><?= icono('telefono', '', 16) ?> <?= htmlspecialchars($pedidoConfirmado['telefono_contacto']) ?></p>
      <?php endif; ?>
      <?php /*
         Dos variantes según el tipo de entrega: delivery (dirección,
         referencia, recargo y link a Google Maps) o retiro en el local.
      */ ?>
      <?php if ($pedidoConfirmado['tipo_entrega'] === 'delivery'): ?>
        <p class="pedido-confirmado-linea"><?= icono('camion', '', 16) ?> Delivery a: <strong><?= htmlspecialchars($pedidoConfirmado['direccion']) ?></strong></p>
        <?php if (!empty($pedidoConfirmado['entrega_referencia'])): ?>
          <p style="font-size:13px;">Referencia: <?= htmlspecialchars($pedidoConfirmado['entrega_referencia']) ?></p>
        <?php endif; ?>
        <p style="font-size:13px;">Incluye <?= formatoPrecio((float) $pedidoConfirmado['recargo_envio']) ?> de recargo por envío.</p>
        <?php if (!empty($pedidoConfirmado['entrega_calle'])): ?>
          <p style="margin-top:10px;">
            <a href="<?= htmlspecialchars(generarUrlMapsDireccion($pedidoConfirmado['entrega_calle'], $pedidoConfirmado['entrega_numero'] ?? '', $pedidoConfirmado['entrega_barrio'] ?? '')) ?>" target="_blank" rel="noopener" class="carrito-seguir-comprando"><?= icono('ubicacion', '', 16) ?> Ver en Google Maps</a>
          </p>
        <?php endif; ?>
      <?php else: ?>
        <p class="pedido-confirmado-linea"><?= icono('casa', '', 16) ?> Retirás tu pedido en el local.</p>
      <?php endif; ?>
      <?php /*
         Lista de lo comprado: cantidad × nombre — subtotal.
      */ ?>
      <?php if (!empty($itemsConfirmado)): ?>
        <ul class="pedido-confirmado-items" style="list-style:none;padding:0;margin:14px 0;font-size:14px;">
          <?php foreach ($itemsConfirmado as $it): ?>
            <li><?= (int) $it['cantidad'] ?> × <?= htmlspecialchars($it['nombre_producto']) ?> — <?= formatoPrecio((float) $it['subtotal']) ?></li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
      <?php /*
         Total, método de pago y aclaración de cuándo se paga (el texto
         cambia según sea delivery o retiro).
      */ ?>
      <p>Total: <strong><?= formatoPrecio((float) $pedidoConfirmado['total']) ?></strong></p>
      <p class="pedido-confirmado-linea">Método de pago elegido: <strong><?= htmlspecialchars(etiquetaMetodoPagoPublico($pedidoConfirmado['metodo_pago'] ?? null)) ?></strong></p>
      <p style="font-size:13px;">El pago se realiza al <?= $pedidoConfirmado['tipo_entrega'] === 'delivery' ? 'recibir' : 'retirar' ?> tu pedido. Si elegiste Mercado Pago o tarjeta, nos vamos a comunicar con vos para coordinarlo.</p>
      <p style="margin-top:20px;">
        <a href="productos.php" class="carrito-seguir-comprando"><?= icono('flecha-izq', '', 16) ?> Seguir comprando</a>
      </p>
    </div>

  <?php /*
     SITUACIÓN B: no venimos de una compra → pantalla normal del carrito.
  */ ?>
  <?php else: ?>

    <h1 class="carrito-titulo">CARRITO DE COMPRAS</h1>
    <p class="carrito-subtitulo">Revisá los productos que elegiste</p>

    <?php /*
       Error del checkout (rojo). role="alert" hace que los lectores de
       pantalla lo anuncien enseguida.
    */ ?>
    <?php if ($errorCheckout): ?>
      <div class="alerta-carrito" role="alert"><?= icono('alerta', '', 18) ?> <?= htmlspecialchars($errorCheckout) ?></div>
    <?php endif; ?>

    <?php /*
       Avisos informativos (promo vencida, producto quitado, cantidad
       ajustada). role="status" es el equivalente no urgente.
    */ ?>
    <?php if (!empty($detalle['avisos'])): ?>
      <div class="avisos-carrito" role="status">
        <?php foreach ($detalle['avisos'] as $aviso): ?>
          <div class="aviso-carrito-item"><?= icono('info', '', 16) ?> <?= htmlspecialchars($aviso) ?></div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <?php /*
       ¿Hay algo en el carrito? Si no → mensaje de vacío; si sí → layout.
    */ ?>
    <?php if (empty($detalle['items'])): ?>

      <!-- ═══════════ CARRITO VACÍO ═══════════ -->
      <div class="carrito-vacio">
        <div class="carrito-vacio-icono"><?= icono('carrito', '', 40) ?></div>
        <h2>Tu carrito está vacío</h2>
        <p>Agregá productos para verlos acá.</p>
        <a href="productos.php" class="carrito-seguir-comprando">Ver productos <?= icono('flecha-der', '', 16) ?></a>
      </div>

    <?php else: ?>

    <?php /*
       Layout de 2 columnas: izquierda = tabla, derecha = resumen.
    */ ?>
    <div class="carrito-layout">

      <!-- ═══════════ COLUMNA IZQUIERDA: TABLA ═══════════ -->
      <?php /*
         Columna izquierda: la tabla con todos los productos y promociones.
      */ ?>
      <div class="carrito-col-izq">
        <div class="carrito-tabla-card">
          <table class="tabla-carrito">
            <?php /*
               caption sr-only: título de la tabla solo para lectores de pantalla.
            */ ?>
            <caption class="sr-only">Productos en tu carrito</caption>
            <thead>
              <tr>
                <th scope="col">Producto</th>
                <th scope="col">Precio</th>
                <th scope="col">Cantidad</th>
                <th scope="col">Subtotal</th>
                <th scope="col"><span class="sr-only">Quitar del carrito</span></th>
              </tr>
            </thead>
            <tbody>
              <?php /*
                 Recorre cada línea del carrito. Un if distingue dos tipos de fila:
                  - PROMOCIÓN (es_promo): badge de color, nota, ahorro. Sus botones
                    envían cambiar_promo / eliminar_promo con promocion_id.
                  - PRODUCTO normal: imagen, categoría, aviso de stock. Sus botones
                    envían cambiar / eliminar con producto_id.
              */ ?>
              <?php foreach ($detalle['items'] as $item): ?>
              <?php if (!empty($item['es_promo'])): ?>
              <?php /*
                 Colores del badge según color_banner (rojo, amarillo u oscuro) y
                 texto de la etiqueta (2x1, 3x2, % OFF...).
              */ ?>
              <?php
                $bgPromo = $item['color_banner'] === 'rojo' ? '#D91F26' : ($item['color_banner'] === 'amarillo' ? '#FFD600' : '#1a1a2e');
                $txtPromo = $item['color_banner'] === 'amarillo' ? '#1a1a1a' : '#fff';
                $badgePromo = $item['tipo'] === 'descuento_pct' ? '% OFF' : etiquetasTipoPromo()[$item['tipo']][0];
              ?>
              <tr>
                <td>
                  <div class="item-producto-info">
                    <div class="item-img">
                      <?php if (!empty($item['productos'][0]['imagen'])): ?>
                        <img src="assets/img/productos/<?= htmlspecialchars($item['productos'][0]['imagen']) ?>" alt="" loading="lazy" decoding="async">
                      <?php else: ?>
                        <?= !empty($item['productos'][0]['emoji']) ? htmlspecialchars($item['productos'][0]['emoji']) : icono('bolsa', '', 28) ?>
                      <?php endif; ?>
                    </div>
                    <div>
                      <span class="item-promo-badge" style="background:<?= $bgPromo ?>;color:<?= $txtPromo ?>;"><?= htmlspecialchars(mb_strtoupper($badgePromo)) ?></span>
                      <p class="item-nombre"><?= htmlspecialchars($item['nombre']) ?></p>
                      <p class="item-promo-nota"><?= htmlspecialchars($item['nota']) ?></p>
                    </div>
                  </div>
                </td>
                <td class="item-precio" data-label="Precio"><?= formatoPrecio((float) $item['precio']) ?></td>
                <td data-label="Cantidad">
                  <div class="item-qty-control">
                    <?php /*
                       CADA botón (−, + y eliminar) es su PROPIO formulario POST, con CSRF y
                       campos ocultos. No hace falta JavaScript: todo funciona con HTML puro.
                       El name de cada input es la clave con la que PHP lo lee en $_POST.
                    */ ?>
                    <form class="item-qty-form" method="POST" action="carrito.php">
<?= csrfCampo() ?>
                      <input type="hidden" name="accion" value="cambiar_promo">
                      <input type="hidden" name="promocion_id" value="<?= (int) $item['id'] ?>">
                      <input type="hidden" name="delta" value="-1">
                      <button type="submit" aria-label="Quitar una unidad de <?= htmlspecialchars($item['nombre']) ?>">−</button>
                    </form>
                    <span><?= htmlspecialchars($item['etiqueta_unidad']) ?></span>
                    <form class="item-qty-form" method="POST" action="carrito.php">
<?= csrfCampo() ?>
                      <input type="hidden" name="accion" value="cambiar_promo">
                      <input type="hidden" name="promocion_id" value="<?= (int) $item['id'] ?>">
                      <input type="hidden" name="delta" value="1">
                      <button type="submit" aria-label="Sumar una unidad de <?= htmlspecialchars($item['nombre']) ?>">+</button>
                    </form>
                  </div>
                  <?php /*
                     Ahorro que genera la promo en esta línea.
                  */ ?>
                  <p class="item-promo-ahorro">Ahorrás <?= formatoPrecio((float) $item['ahorro']) ?></p>
                </td>
                <td class="item-subtotal" data-label="Subtotal"><?= formatoPrecio((float) $item['subtotal']) ?></td>
                <td>
                  <form class="item-eliminar-form" method="POST" action="carrito.php">
<?= csrfCampo() ?>
                    <input type="hidden" name="accion" value="eliminar_promo">
                    <input type="hidden" name="promocion_id" value="<?= (int) $item['id'] ?>">
                    <button type="submit" class="item-eliminar-btn" title="Eliminar" aria-label="Eliminar <?= htmlspecialchars($item['nombre']) ?> del carrito"><?= icono('basura', '', 16) ?></button>
                  </form>
                </td>
              </tr>
              <?php /*
                 Fila de PRODUCTO normal.
              */ ?>
              <?php else: ?>
              <tr>
                <td>
                  <div class="item-producto-info">
                    <div class="item-img">
                      <?php if (!empty($item['imagen'])): ?>
                        <img src="assets/img/productos/<?= htmlspecialchars($item['imagen']) ?>" alt="<?= htmlspecialchars($item['nombre']) ?>" loading="lazy" decoding="async">
                      <?php else: ?>
                        <?= htmlspecialchars($item['emoji']) ?>
                      <?php endif; ?>
                    </div>
                    <div>
                      <p class="item-nombre"><?= htmlspecialchars($item['nombre']) ?></p>
                      <p class="item-categoria"><?= htmlspecialchars($item['categoria_nombre']) ?></p>
                      <?php /*
                         Si la cantidad en el carrito supera el stock, se avisa cuántas quedan.
                      */ ?>
                      <?php if ($item['cantidad'] > $item['stock']): ?>
                        <p class="item-stock-aviso">Solo quedan <?= (int) $item['stock'] ?> disponibles</p>
                      <?php endif; ?>
                    </div>
                  </div>
                </td>
                <td class="item-precio" data-label="Precio"><?= formatoPrecio($item['precio']) ?></td>
                <td data-label="Cantidad">
                  <div class="item-qty-control">
                    <form class="item-qty-form" method="POST" action="carrito.php">
<?= csrfCampo() ?>
                      <input type="hidden" name="accion" value="cambiar">
                      <input type="hidden" name="producto_id" value="<?= (int) $item['id'] ?>">
                      <input type="hidden" name="delta" value="-1">
                      <button type="submit" aria-label="Quitar una unidad de <?= htmlspecialchars($item['nombre']) ?>">−</button>
                    </form>
                    <span><?= (int) $item['cantidad'] ?></span>
                    <form class="item-qty-form" method="POST" action="carrito.php">
<?= csrfCampo() ?>
                      <input type="hidden" name="accion" value="cambiar">
                      <input type="hidden" name="producto_id" value="<?= (int) $item['id'] ?>">
                      <input type="hidden" name="delta" value="1">
                      <button type="submit" aria-label="Sumar una unidad de <?= htmlspecialchars($item['nombre']) ?>">+</button>
                    </form>
                  </div>
                </td>
                <td class="item-subtotal" data-label="Subtotal"><?= formatoPrecio($item['subtotal']) ?></td>
                <td>
                  <form class="item-eliminar-form" method="POST" action="carrito.php">
<?= csrfCampo() ?>
                    <input type="hidden" name="accion" value="eliminar">
                    <input type="hidden" name="producto_id" value="<?= (int) $item['id'] ?>">
                    <button type="submit" class="item-eliminar-btn" title="Eliminar" aria-label="Eliminar <?= htmlspecialchars($item['nombre']) ?> del carrito"><?= icono('basura', '', 16) ?></button>
                  </form>
                </td>
              </tr>
              <?php endif; ?>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>

        <?php /*
           Enlace para volver al catálogo.
        */ ?>
        <a href="productos.php" class="carrito-seguir-comprando"><?= icono('flecha-izq', '', 16) ?> Seguir comprando</a>
      </div>

      <!-- ═══════════ COLUMNA DERECHA: RESUMEN ═══════════ -->
      <?php /*
         Columna derecha: tarjeta de resumen con totales y el formulario
         de finalización.
      */ ?>
      <div class="carrito-col-der">
        <div class="resumen-card">
          <h2 class="resumen-titulo">RESUMEN DEL PEDIDO</h2>

          <div class="resumen-fila">
            <?php /*
               Subtotal con plural dinámico: "1 producto" / "3 productos".
            */ ?>
            <span>Subtotal (<?= (int) $detalle['cantidad_total'] ?> producto<?= $detalle['cantidad_total'] === 1 ? '' : 's' ?>)</span>
            <span><?= formatoPrecio($detalle['subtotal']) ?></span>
          </div>
          <?php /*
             La fila de ahorro solo aparece si hubo ahorro por promociones.
          */ ?>
          <?php if ($detalle['ahorro_total'] > 0): ?>
          <div class="resumen-fila resumen-fila-ahorro">
            <span>Ahorro por promociones</span>
            <span>− <?= formatoPrecio($detalle['ahorro_total']) ?></span>
          </div>
          <?php endif; ?>
          <?php /*
             Envío y total: arrancan como "Gratis" / subtotal. El JS los actualiza
             al elegir delivery (aria-live avisa el cambio a lectores de pantalla).
             Aunque se vean cambiar en pantalla, el cálculo real lo hace el servidor.
          */ ?>
          <div class="resumen-fila envio-gratis" id="fila-envio">
            <span>Envío</span>
            <span id="envio-texto" aria-live="polite">Gratis (retiro en local)</span>
          </div>

          <div class="resumen-divisor"></div>

          <div class="resumen-total-fila">
            <span>Total</span>
            <span id="total-final" aria-live="polite"><?= formatoPrecio($detalle['subtotal']) ?></span>
          </div>

          <?php /*
             FORMULARIO DE FINALIZACIÓN. Envía por POST a este mismo archivo.
             data-subtotal y data-recargo-delivery son atributos que PHP imprime
             para que el JS (carrito-resumen.js) calcule el total en pantalla sin
             depender de PHP.
          */ ?>
          <form method="POST" action="carrito.php" id="form-finalizar" data-subtotal="<?= number_format((float) $detalle['subtotal'], 2, '.', '') ?>" data-recargo-delivery="<?= (int) RECARGO_DELIVERY ?>">
<?= csrfCampo() ?>
            <?php /*
               Tres campos OCULTOS clave:
                accion=finalizar      → dice al servidor qué hacer (lo lee el if de arriba).
                subtotal_esperado     → el total que el cliente está viendo, para detectar
                                        si el carrito cambió antes de crear el pedido.
                token_checkout        → token de un solo uso contra pedidos duplicados.
            */ ?>
            <input type="hidden" name="accion" value="finalizar">
            <input type="hidden" name="subtotal_esperado" value="<?= number_format((float) $detalle['subtotal'], 2, '.', '') ?>">
            <input type="hidden" name="token_checkout" value="<?= htmlspecialchars($_SESSION['checkout_token'], ENT_QUOTES) ?>">

            <?php /*
               TELÉFONO. Si hay uno guardado se muestra un aviso y el campo queda
               readonly con el botón "Usar otro número" (lo maneja el JS).
               El value se rellena con:  lo que escribió ?? el dato guardado ?? ''.
               required + pattern son validaciones del navegador (el servidor repite
               la validación por seguridad).
            */ ?>
            <div class="resumen-campo">
              <label for="campo-telefono">Teléfono de contacto</label>
              <?php if ($mostrarTelefonoGuardado): ?>
                <p class="dato-guardado-aviso" id="telefono-aviso">
                  <span id="telefono-aviso-texto"><?= icono('telefono', '', 14) ?> Te vamos a contactar al <strong><?= htmlspecialchars($telefonoGuardado) ?></strong> (el que tenés guardado).</span>
                  <button type="button" class="dato-guardado-btn" id="telefono-cambiar-btn" data-guardado="<?= htmlspecialchars($telefonoGuardado) ?>">Usar otro número</button>
                </p>
              <?php endif; ?>
              <input type="tel" id="campo-telefono" name="telefono" placeholder="Ej: 11 1234 5678" autocomplete="tel" required
                     inputmode="tel" pattern="[0-9+\s-]{8,20}" title="Solo números, espacios, guiones o &quot;+&quot; (8 a 20 caracteres)"
                     value="<?= htmlspecialchars($_POST['telefono'] ?? $telefonoGuardado ?? '') ?>"
                     <?= $mostrarTelefonoGuardado ? 'readonly' : '' ?>>
            </div>

            <?php /*
               TIPO DE ENTREGA. Los dos radios comparten name="tipo_entrega":
               solo se puede marcar uno y se envía el value del elegido.
            */ ?>
            <div class="resumen-campo">
              <span class="resumen-campo-etiqueta" id="lbl-entrega">¿Cómo querés recibir tu pedido?</span>
              <div class="resumen-pago-opciones" role="radiogroup" aria-labelledby="lbl-entrega">
                <label><input type="radio" name="tipo_entrega" value="retiro" id="entrega-retiro" checked> Retiro en el local</label>
                <label><input type="radio" name="tipo_entrega" value="delivery" id="entrega-delivery"> Delivery (recargo de <?= formatoPrecio((float) RECARGO_DELIVERY) ?>)</label>
              </div>
            </div>

            <?php /*
               DIRECCIÓN: arranca oculta (display:none) y el JS la muestra al elegir
               delivery. Se precarga con la misma lógica que el teléfono.
            */ ?>
            <div id="campo-direccion-wrap" style="display:none;">
              <?php if ($mostrarDireccionGuardada): ?>
                <?php $direccionGuardadaTexto = armarDireccionCompleta($direccionGuardada['calle'], $direccionGuardada['numero'], $direccionGuardada['barrio'], $direccionGuardada['referencia']); ?>
                <p class="dato-guardado-aviso" id="direccion-aviso">
                  <span id="direccion-aviso-texto"><?= icono('ubicacion', '', 14) ?> Vamos a enviarlo a <strong><?= htmlspecialchars($direccionGuardadaTexto) ?></strong> (tu dirección guardada).</span>
                  <button type="button" class="dato-guardado-btn" id="direccion-cambiar-btn"
                          data-calle="<?= htmlspecialchars($direccionGuardada['calle']) ?>"
                          data-numero="<?= htmlspecialchars($direccionGuardada['numero']) ?>"
                          data-barrio="<?= htmlspecialchars($direccionGuardada['barrio']) ?>"
                          data-referencia="<?= htmlspecialchars($direccionGuardada['referencia']) ?>">Usar otra dirección</button>
                </p>
              <?php endif; ?>
              <div class="resumen-campo-grupo resumen-campo-grupo-calle">
                <div class="resumen-campo resumen-campo-calle">
                  <label for="campo-calle">Calle</label>
                  <input type="text" id="campo-calle" name="entrega_calle" placeholder="Ej: San Martín" maxlength="150"
                         value="<?= htmlspecialchars($_POST['entrega_calle'] ?? $direccionGuardada['calle'] ?? '') ?>"
                         <?= $mostrarDireccionGuardada ? 'readonly' : '' ?>>
                </div>
                <div class="resumen-campo resumen-campo-numero">
                  <label for="campo-numero">Número</label>
                  <input type="text" id="campo-numero" name="entrega_numero" placeholder="Ej: 1234" maxlength="20"
                         value="<?= htmlspecialchars($_POST['entrega_numero'] ?? $direccionGuardada['numero'] ?? '') ?>"
                         <?= $mostrarDireccionGuardada ? 'readonly' : '' ?>>
                </div>
              </div>
              <div class="resumen-campo">
                <label for="campo-barrio">Barrio</label>
                <input type="text" id="campo-barrio" name="entrega_barrio" placeholder="Ej: Centro" maxlength="100"
                       value="<?= htmlspecialchars($_POST['entrega_barrio'] ?? $direccionGuardada['barrio'] ?? '') ?>"
                       <?= $mostrarDireccionGuardada ? 'readonly' : '' ?>>
              </div>
              <div class="resumen-campo">
                <label for="campo-referencia">Referencia (opcional)</label>
                <input type="text" id="campo-referencia" name="entrega_referencia" placeholder="Ej: Portón negro, casa con rejas" maxlength="255"
                       value="<?= htmlspecialchars($_POST['entrega_referencia'] ?? $direccionGuardada['referencia'] ?? '') ?>"
                       <?= $mostrarDireccionGuardada ? 'readonly' : '' ?>>
              </div>
            </div>

            <?php /*
               MÉTODO DE PAGO (name="metodo_pago"). El servidor lo valida con una
               lista blanca. En este archivo no hay cobro online: Mercado Pago y
               tarjeta se coordinan por contacto posterior.
            */ ?>
            <div class="resumen-campo">
              <span class="resumen-campo-etiqueta" id="lbl-pago">Método de pago</span>
              <div class="resumen-pago-opciones" role="radiogroup" aria-labelledby="lbl-pago">
                <label><input type="radio" name="metodo_pago" value="efectivo" checked> Efectivo</label>
                <label><input type="radio" name="metodo_pago" value="mercadopago"> Mercado Pago</label>
                <label><input type="radio" name="metodo_pago" value="tarjeta"> Tarjeta</label>
              </div>
              <p style="font-size:12px;color:var(--gris-texto);margin-top:6px;">El pago se realiza al retirar o recibir tu pedido. Si elegís Mercado Pago o tarjeta, nos comunicamos con vos para coordinarlo.</p>
            </div>

            <?php /*
               Botón de envío. El JS lo bloquea tras el primer clic ("PROCESANDO...").
            */ ?>
            <button type="submit" class="btn-finalizar-compra">FINALIZAR COMPRA</button>
          </form>

          <p class="resumen-seguro"><?= icono('candado', '', 14) ?> Pagás al retirar o recibir tu pedido</p>
        </div>
      </div>

      <?php /*
         JS del resumen: calcula envío y total, valida la dirección, evita el
         doble clic y maneja los botones "Usar otro número / dirección".
      */ ?>
      <script src="<?= htmlspecialchars(assetUrl('assets/js/carrito-resumen.js')) ?>" defer></script>


    </div>

    <?php endif; ?>

    <?php /*
       Tarjetas de beneficios (texto informativo).
    */ ?>
    <!-- ═══════════ BENEFICIOS ═══════════ -->
    <div class="carrito-beneficios">
      <div class="carrito-beneficio-item">
        <span class="carrito-beneficio-icono"><?= icono('camion', '', 22) ?></span>
        <div>
          <div class="carrito-beneficio-titulo">Envío rápido</div>
          <div class="carrito-beneficio-desc">Recibí tu pedido en minutos</div>
        </div>
      </div>
      <div class="carrito-beneficio-item">
        <span class="carrito-beneficio-icono"><?= icono('casa', '', 22) ?></span>
        <div>
          <div class="carrito-beneficio-titulo">Retiro en tienda</div>
          <div class="carrito-beneficio-desc">Retirá gratis en cualquier sucursal</div>
        </div>
      </div>
      <div class="carrito-beneficio-item">
        <span class="carrito-beneficio-icono"><?= icono('mensaje', '', 22) ?></span>
        <div>
          <div class="carrito-beneficio-titulo">Atención por WhatsApp</div>
          <div class="carrito-beneficio-desc">Estamos para ayudarte</div>
        </div>
      </div>
      <div class="carrito-beneficio-item">
        <span class="carrito-beneficio-icono"><?= icono('escudo-check', '', 22) ?></span>
        <div>
          <div class="carrito-beneficio-titulo">Pagos seguros</div>
          <div class="carrito-beneficio-desc">Protegemos tus datos</div>
        </div>
      </div>
    </div>

  <?php endif; ?>

</div>

<?php include 'includes/footer.php'; ?>

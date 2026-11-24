<?php
/* ============================================================
   CHATBOT_LOGICA.PHP — El Rápido

   Cerebro del asistente. Es un bot de REGLAS (no usa inteligencia
   artificial): reconoce palabras clave y responde con datos reales
   del sitio (config.php, base de datos y faq_datos.php).

   Solo depende de una función:
       chatbotResponder($pdo, $mensaje, $usuario)
   y devuelve siempre el mismo formato:
       [
         'texto'       => string,
         'tarjetas'    => [ ['titulo','detalle','precio'], ... ],
         'enlaces'     => [ ['texto','url','tipo'?], ... ],
         'sugerencias' => [ 'texto', ... ],
       ]
   Como no sabe nada de HTML ni de la web, más adelante el mismo
   código se puede usar para un bot de WhatsApp.
   ============================================================ */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/funciones.php';
require_once __DIR__ . '/promociones_funciones.php';
require_once __DIR__ . '/faq_datos.php';

/* ═══════════════════════════════════════════════════════════
   UTILIDADES
   ═══════════════════════════════════════════════════════════ */

/** Minúsculas, sin tildes y solo letras/números separados por 1 espacio */
function chatbotNormalizar(string $texto): string {
  $texto = mb_strtolower($texto, 'UTF-8');
  $texto = strtr($texto, [
    'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n',
    'à' => 'a', 'è' => 'e', 'ì' => 'i', 'ò' => 'o', 'ù' => 'u',
  ]);
  $texto = preg_replace('/[^a-z0-9]+/', ' ', $texto);
  return trim($texto);
}

/** ¿El texto (ya normalizado) contiene alguna de estas frases como palabra completa? */
function chatbotContiene(string $t, array $frases): bool {
  $h = ' ' . $t . ' ';
  foreach ($frases as $f) {
    if (strpos($h, ' ' . $f . ' ') !== false) {
      return true;
    }
  }
  return false;
}

/** ¿Queda algo del texto después de sacarle estas frases? (para saludos, gracias, chau) */
function chatbotSoloContiene(string $t, array $frases): bool {
  $resto = ' ' . $t . ' ';
  foreach ($frases as $f) {          // las frases largas van primero en cada lista
    $resto = str_replace(' ' . $f . ' ', ' ', $resto);
  }
  return trim($resto) === '' && trim($t) !== '';
}

function chatbotResp(string $texto, array $enlaces = [], array $sugerencias = [], array $tarjetas = []): array {
  return [
    'texto'       => $texto,
    'tarjetas'    => $tarjetas,
    'enlaces'     => $enlaces,
    'sugerencias' => $sugerencias,
  ];
}

function chatbotEnlaceWsp(string $texto = 'Hablar por WhatsApp', ?string $mensaje = null): array {
  return [
    'texto' => $texto,
    'url'   => urlWhatsapp($mensaje ?? 'Hola, tengo una consulta sobre El Rápido.'),
    'tipo'  => 'wsp',
  ];
}

/* ═══════════════════════════════════════════════════════════
   HORARIO (lee NEGOCIO_HORARIO de config.php, ej. "08:00 a 01:00 hs")
   Soporta horarios que pasan la medianoche.
   ═══════════════════════════════════════════════════════════ */

function chatbotEstadoHorario(): ?array {
  if (!preg_match_all('/(\d{1,2}):(\d{2})/', NEGOCIO_HORARIO, $m, PREG_SET_ORDER) || count($m) < 2) {
    return null;
  }
  $abre   = ((int) $m[0][1]) * 60 + (int) $m[0][2];
  $cierra = ((int) $m[1][1]) * 60 + (int) $m[1][2];
  $ahora  = ((int) date('G')) * 60 + (int) date('i');

  if ($abre === $cierra) {
    $abierto = true;                                   // 24 hs
  } elseif ($abre < $cierra) {
    $abierto = $ahora >= $abre && $ahora < $cierra;    // ej. 08:00 a 22:00
  } else {
    $abierto = $ahora >= $abre || $ahora < $cierra;    // ej. 08:00 a 01:00
  }

  return [
    'abierto' => $abierto,
    'abre'    => sprintf('%02d:%02d', (int) $m[0][1], (int) $m[0][2]),
    'cierra'  => sprintf('%02d:%02d', (int) $m[1][1], (int) $m[1][2]),
  ];
}

function chatbotRespuestaHorario(): array {
  $e = chatbotEstadoHorario();
  if ($e === null) {
    return chatbotResp('Atendemos todos los días de ' . NEGOCIO_HORARIO . '.');
  }
  if ($e['abierto']) {
    return chatbotResp(
      'Sí, estamos abiertos ahora. Atendemos hasta las ' . $e['cierra'] . ' hs.',
      [['texto' => 'Ver productos', 'url' => 'productos.php']],
      ['¿Hacen delivery?', 'Promos de hoy']
    );
  }
  return chatbotResp(
    'Ahora estamos cerrados. Volvemos a abrir a las ' . $e['abre'] . ' hs. Podés ir armando tu carrito, pero los pedidos se procesan dentro del horario de atención.',
    [['texto' => 'Ver productos', 'url' => 'productos.php']],
    ['¿Cuál es el horario?', 'Promos de hoy']
  );
}

/* ═══════════════════════════════════════════════════════════
   PEDIDO DEL CLIENTE (solo si inició sesión)
   ═══════════════════════════════════════════════════════════ */

function chatbotRespuestaPedido(PDO $pdo, ?array $usuario): array {
  if (!$usuario || empty($usuario['id'])) {
    return chatbotResp(
      'Para ver el estado de tu pedido tenés que iniciar sesión.',
      [['texto' => 'Iniciar sesión', 'url' => 'login.php?volver=' . rawurlencode('mi-cuenta.php?seccion=pedidos')]]
    );
  }

  $stmt = $pdo->prepare('SELECT id, estado, total, creado_en FROM pedidos WHERE usuario_id = :u ORDER BY id DESC LIMIT 1');
  $stmt->execute([':u' => (int) $usuario['id']]);
  $p = $stmt->fetch();

  if (!$p) {
    return chatbotResp(
      'Todavía no tenés pedidos en tu cuenta. Cuando hagas el primero, lo vas a poder seguir desde acá.',
      [['texto' => 'Ver productos', 'url' => 'productos.php']]
    );
  }

  $estados = [
    'pendiente'    => 'recibido, todavía no salió',
    'preparando'   => 'en preparación',
    'listo_retiro' => 'listo para retirar',
    'en_camino'    => 'en camino',
    'entregado' => 'entregado',
    'cancelado' => 'cancelado',
  ];
  $estado = $estados[$p['estado']] ?? $p['estado'];
  $fecha  = date('d/m', strtotime($p['creado_en']));

  $texto = 'Tu último pedido es el #' . (int) $p['id'] . ' del ' . $fecha
         . ', por ' . formatoPrecio((float) $p['total']) . '.' . "\n"
         . 'Estado: ' . $estado . '.';

  $enlaces = [
    ['texto' => 'Ver detalle', 'url' => 'pedido_detalle.php?id=' . (int) $p['id']],
    ['texto' => 'Todos mis pedidos', 'url' => 'mi-cuenta.php?seccion=pedidos'],
  ];
  if ($p['estado'] === 'pendiente') {
    $texto .= "\n" . 'Si querés modificarlo o cancelarlo, escribinos por WhatsApp antes de que salga.';
    $enlaces[] = chatbotEnlaceWsp('Modificar o cancelar', 'Hola, quiero consultar por mi pedido #' . (int) $p['id'] . '.');
  }

  return chatbotResp($texto, $enlaces);
}

/* ═══════════════════════════════════════════════════════════
   PROMOCIONES Y CATEGORÍAS (datos reales de la base)
   ═══════════════════════════════════════════════════════════ */

function chatbotRespuestaPromos(PDO $pdo): array {
  actualizarEstadosPromociones($pdo);
  $promos = obtenerTodasLasPromocionesActivas($pdo);

  if (empty($promos)) {
    return chatbotResp(
      'Ahora no hay promociones activas. Pasate de nuevo en unas horas o mirá el catálogo completo.',
      [['texto' => 'Ver productos', 'url' => 'productos.php']]
    );
  }

  $tarjetas = [];
  foreach (array_slice($promos, 0, 4) as $p) {
    $detalle = subtituloTextoPromo($p);
    if ($detalle === '') {
      $detalle = implode(', ', array_column($p['productos'], 'nombre'));
    }
    $tarjetas[] = [
      'titulo'  => badgeTextoPromo($p) . ' ' . $p['titulo'],
      'detalle' => $detalle,
      'precio'  => precioTextoPromo($p),
    ];
  }

  $texto = count($promos) === 1 ? 'Hoy tenemos esta promoción:' : 'Estas son las promociones activas:';
  if (count($promos) > 4) {
    $texto .= "\n" . 'Hay ' . (count($promos) - 4) . ' más en el catálogo.';
  }

  return chatbotResp(
    $texto,
    [['texto' => 'Ver todas las promos', 'url' => 'productos.php?cat=promociones']],
    [],
    $tarjetas
  );
}

function chatbotRespuestaCategorias(PDO $pdo): array {
  $cats = array_filter(obtenerCategoriasPublico($pdo), function ($c) {
    return (int) $c['cantidad'] > 0;
  });
  if (empty($cats)) {
    return chatbotResp('Todavía no hay productos cargados en el catálogo.');
  }

  $lineas = [];
  foreach (array_slice(array_values($cats), 0, 10) as $c) {
    $lineas[] = '• ' . $c['nombre'] . ' (' . (int) $c['cantidad'] . ')';
  }

  return chatbotResp(
    "Tenemos estas categorías:\n" . implode("\n", $lineas),
    [['texto' => 'Ver el catálogo', 'url' => 'productos.php']],
    ['Promos de hoy']
  );
}

/* ═══════════════════════════════════════════════════════════
   BÚSQUEDA DE PRODUCTOS
   ═══════════════════════════════════════════════════════════ */

/** Palabras que NO sirven para buscar un producto (ya normalizadas) */
function chatbotPalabrasVacias(): array {
  static $lista = null;
  if ($lista === null) {
    $lista = array_flip([
      'que', 'como', 'donde', 'cual', 'cuales', 'cuanto', 'cuanta', 'cuantos', 'sale', 'cuesta', 'vale', 'valen',
      'esta', 'estan', 'tienen', 'tenes', 'tienes', 'tiene', 'hay', 'venden', 'vende', 'vendes', 'ofrecen',
      'precio', 'precios', 'busco', 'quiero', 'necesito', 'quisiera', 'queria', 'dame', 'tengo',
      'para', 'por', 'con', 'sin', 'una', 'uno', 'unos', 'unas', 'del', 'los', 'las', 'el', 'la', 'de', 'en',
      'y', 'o', 'me', 'mi', 'mis', 'tu', 'tus', 'si', 'no', 'es', 'son', 'lo', 'le', 'les', 'se', 'ya',
      'mas', 'muy', 'pero', 'tambien', 'hoy', 'ahora', 'stock', 'disponible', 'disponibles',
      'algun', 'alguna', 'algunos', 'algunas', 'favor', 'porfa', 'hola', 'buenas', 'buen', 'dia', 'dias',
      'tarde', 'tardes', 'noche', 'noches', 'kiosco', 'maxi', 'hacen', 'hace', 'puedo', 'puede',
      'podria', 'podrian', 'ver', 'info', 'informacion', 'consulta', 'consultar', 'saber', 'decime', 'decir',
      'gracias', 'quien', 'ser',
    ]);
  }
  return $lista;
}

/** Saca plural simple: "alfajores" → "alfajor", "papas" → "papa" */
function chatbotRaiz(string $w): string {
  $n = strlen($w);
  if ($n > 5 && substr($w, -2) === 'es') {
    return substr($w, 0, -2);
  }
  if ($n > 3 && substr($w, -1) === 's') {
    return substr($w, 0, -1);
  }
  return $w;
}

function chatbotTokensProducto(string $t): array {
  $vacias = chatbotPalabrasVacias();
  $out = [];
  foreach (explode(' ', $t) as $w) {
    if (strlen($w) < 3 || isset($vacias[$w])) {
      continue;
    }
    $out[] = chatbotRaiz($w);
  }
  return array_values(array_unique($out));
}

/** Devuelve hasta 5 productos activos cuyo nombre contiene TODAS las palabras */
function chatbotBuscarProductos(PDO $pdo, array $tokens): array {
  if (empty($tokens)) {
    return [];
  }
  $where  = ['p.activo = 1'];
  $params = [];
  foreach (array_values($tokens) as $i => $tok) {
    $where[] = 'p.nombre LIKE :t' . $i;
    $params[':t' . $i] = '%' . $tok . '%';
  }
  $sql = 'SELECT p.nombre, p.precio, p.descuento_pct, p.emoji, p.stock
          FROM productos p
          WHERE ' . implode(' AND ', $where) . '
          ORDER BY (p.stock > 0) DESC, p.destacado DESC, p.ventas DESC
          LIMIT 5';
  $stmt = $pdo->prepare($sql);
  $stmt->execute($params);
  return $stmt->fetchAll();
}

function chatbotRespuestaProductos(array $filas): array {
  $tarjetas = [];
  foreach (array_slice($filas, 0, 4) as $p) {
    $pct    = (int) $p['descuento_pct'];
    $final  = precioConDescuento((float) $p['precio'], $pct);
    $detalle = ((int) $p['stock'] > 0) ? 'Disponible' : 'Sin stock por ahora';
    if ($pct > 0) {
      $detalle .= ', ' . $pct . '% OFF';
    }
    $tarjetas[] = [
      'titulo'  => trim($p['emoji'] . ' ' . $p['nombre']),
      'detalle' => $detalle,
      'precio'  => formatoPrecio($final),
    ];
  }

  $texto = count($filas) === 1 ? 'Encontré este producto:' : 'Encontré esto:';
  if (count($filas) > 4) {
    $texto .= "\n" . 'Hay más opciones en el catálogo.';
  }

  return chatbotResp(
    $texto,
    [['texto' => 'Ver el catálogo', 'url' => 'productos.php']],
    [],
    $tarjetas
  );
}

/* ═══════════════════════════════════════════════════════════
   PREGUNTAS FRECUENTES (faq_datos.php)
   ═══════════════════════════════════════════════════════════ */

function chatbotMejorFaq(string $t): ?array {
  $mejor = null;
  $mejorPuntaje = 0;
  foreach (obtenerPreguntasFrecuentes() as $faq) {
    $puntaje = 0;
    foreach ($faq['palabras'] as $frase) {
      if (chatbotContiene($t, [$frase])) {
        $puntaje += substr_count($frase, ' ') + 1;   // frases largas pesan más
      }
    }
    if ($puntaje > $mejorPuntaje) {
      $mejorPuntaje = $puntaje;
      $mejor = $faq;
    }
  }
  return $mejor;
}

/* ═══════════════════════════════════════════════════════════
   FUNCIÓN PRINCIPAL
   ═══════════════════════════════════════════════════════════ */

function chatbotResponder(PDO $pdo, string $mensaje, ?array $usuario = null): array {
  $t = chatbotNormalizar($mensaje);

  if ($t === '') {
    return chatbotResp('Escribime tu consulta o tocá una de las opciones.', [], ['Promos de hoy', '¿Están abiertos?', '¿Hacen delivery?']);
  }

  $sugerenciasBase = ['Promos de hoy', '¿Están abiertos?', '¿Hacen delivery?', 'Medios de pago', 'Mi pedido'];

  /* 1) Mensajes de cortesía (solo si el mensaje es únicamente eso) */
  $saludos = ['buenas tardes', 'buenas noches', 'buenos dias', 'buen dia', 'como estas', 'como andas', 'como va', 'que tal', 'hola', 'holaa', 'buenas', 'hey', 'ey'];
  if (chatbotSoloContiene($t, $saludos)) {
    return chatbotResp('¡Hola! ¿En qué te puedo ayudar?', [], $sugerenciasBase);
  }
  $gracias = ['muchas gracias', 'mil gracias', 'gracias', 'genial', 'perfecto', 'excelente', 'buenisimo', 'barbaro', 'joya', 'listo', 'dale', 'ok', 'oka'];
  if (chatbotSoloContiene($t, $gracias)) {
    return chatbotResp('¡De nada! Si necesitás algo más, acá estoy.');
  }
  $chau = ['hasta luego', 'nos vemos', 'chau', 'chao', 'adios', 'saludos'];
  if (chatbotSoloContiene($t, $chau)) {
    return chatbotResp('¡Chau! Que tengas un buen día.');
  }

  /* 2) Quién es el bot / hablar con una persona */
  if (chatbotContiene($t, ['quien sos', 'quien eres', 'sos un bot', 'sos un robot', 'eres un bot', 'eres un robot', 'sos una persona', 'sos humano', 'eres humano', 'con quien hablo', 'como te llamas', 'tu nombre'])) {
    return chatbotResp(
      'Soy el asistente virtual de El Rápido: un programa que responde con la información del sitio. Si necesitás algo que yo no sé, te paso con una persona por WhatsApp.',
      [chatbotEnlaceWsp()],
      $sugerenciasBase
    );
  }
  if (chatbotContiene($t, ['hablar con alguien', 'hablar con una persona', 'hablar con un humano', 'persona real', 'atencion al cliente', 'operador', 'asesor', 'humano', 'con alguien'])) {
    return chatbotResp(
      'Claro, te atiende una persona por WhatsApp.',
      [chatbotEnlaceWsp('Abrir WhatsApp')]
    );
  }

  /* 3) Estado del pedido del cliente */
  $pideCambio = chatbotContiene($t, ['cancelar', 'modificar', 'cambiar', 'anular', 'error', 'falta', 'faltan', 'faltante', 'equivocado', 'equivocaron', 'trajeron', 'reclamo']);
  if (!$pideCambio && chatbotContiene($t, ['mi pedido', 'mis pedidos', 'estado del pedido', 'estado de mi pedido', 'mi ultimo pedido', 'ultimo pedido', 'seguimiento', 'donde esta mi pedido', 'cuando llega mi pedido', 'mi compra', 'mi orden'])) {
    return chatbotRespuestaPedido($pdo, $usuario);
  }

  /* 4) ¿Estamos abiertos ahora? */
  if (chatbotContiene($t, ['abierto', 'abiertos', 'abierta', 'abiertas', 'estan atendiendo', 'atienden ahora', 'ya cerraron', 'cerrado', 'cerrados', 'hay atencion'])) {
    return chatbotRespuestaHorario();
  }

  /* 5) Promociones */
  if (chatbotContiene($t, ['promo', 'promos', 'promocion', 'promociones', 'oferta', 'ofertas', 'descuento', 'descuentos', '2x1', '3x2', 'combo', 'combos', 'rebaja', 'rebajas', 'liquidacion'])) {
    return chatbotRespuestaPromos($pdo);
  }

  /* 6) Categorías */
  if (chatbotContiene($t, ['categorias', 'categoria', 'que venden', 'que productos tienen', 'que productos hay', 'catalogo', 'que tienen para comprar'])) {
    return chatbotRespuestaCategorias($pdo);
  }

  /* 7) Producto (si la frase tiene forma de "¿tienen X?", "precio de X") */
  $tokens = chatbotTokensProducto($t);
  $formaProducto = chatbotContiene($t, ['tienen', 'tenes', 'tienes', 'hay', 'precio', 'precios', 'cuanto sale', 'cuanto cuesta', 'cuanto esta', 'cuanto vale', 'vale', 'venden', 'busco', 'quiero', 'necesito', 'queria', 'quisiera']);
  $yaBuscado = false;
  $buscoPorForma = false;
  if ($formaProducto && !empty($tokens) && count($tokens) <= 4) {
    $filas = chatbotBuscarProductos($pdo, $tokens);
    $yaBuscado = true;
    $buscoPorForma = true;
    if (!empty($filas)) {
      return chatbotRespuestaProductos($filas);
    }
  }

  /* 8) Preguntas frecuentes */
  $faq = chatbotMejorFaq($t);
  if ($faq !== null) {
    return chatbotResp($faq['respuesta'], $faq['enlaces'] ?? [], []);
  }

  /* 9) Mensaje corto sin forma de pregunta: probamos como nombre de producto ("leche", "papas fritas") */
  if (!$yaBuscado && !empty($tokens) && count($tokens) <= 3) {
    $filas = chatbotBuscarProductos($pdo, $tokens);
    $yaBuscado = true;
    if (!empty($filas)) {
      return chatbotRespuestaProductos($filas);
    }
  }

  /* 10) No entendimos */
  if ($buscoPorForma) {
    return chatbotResp(
      'No encontré ese producto en el catálogo. Puede que lo tengamos y no esté cargado: consultanos por WhatsApp y te confirmamos.',
      [['texto' => 'Ver el catálogo', 'url' => 'productos.php'], chatbotEnlaceWsp('Consultar por WhatsApp', 'Hola, ¿tienen ' . mb_substr($mensaje, 0, 60) . '?')]
    );
  }

  return chatbotResp(
    'No estoy seguro de haberte entendido. Puedo ayudarte con horarios, delivery, medios de pago, promociones, precios de productos y el estado de tu pedido. Si preferís, hablá con una persona por WhatsApp.',
    [chatbotEnlaceWsp()],
    $sugerenciasBase
  );
}

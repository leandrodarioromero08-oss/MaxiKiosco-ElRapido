<?php
/* ============================================================
   FAQ_DATOS.PHP — El Rápido
   Fuente ÚNICA de preguntas y respuestas.

   La usan dos lugares:
     - preguntas-frecuentes.php  → muestra las que tienen 'web' => true
     - includes/chatbot_logica.php → el chatbot responde con TODAS

   Para cambiar una respuesta, editala acá una sola vez y se
   actualiza en la página y en el bot.

   Campos de cada pregunta:
     web       true = aparece en la página de preguntas frecuentes
               false = la usa solo el chatbot
     pregunta  texto de la pregunta
     respuesta texto plano (sin HTML)
     palabras  frases que el bot busca para reconocer la consulta.
               Van en minúsculas y SIN tildes. Cuantas más palabras
               tiene una frase, más peso tiene al elegir la respuesta.
     enlaces   (opcional) botones que el bot muestra debajo
   ============================================================ */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/funciones.php';

function obtenerPreguntasFrecuentes(): array {
  $direccion = NEGOCIO_DIRECCION . ', Monte Caseros, Corrientes';

  return [

    /* ── Las 7 de la página de preguntas frecuentes ─────────── */
    [
      'web'       => true,
      'pregunta'  => '¿Cuál es el horario de atención?',
      'respuesta' => 'Atendemos todos los días de ' . NEGOCIO_HORARIO . '. Los pedidos por la web y por WhatsApp se procesan dentro de ese mismo horario.',
      'palabras'  => ['horario', 'horarios', 'hora de atencion', 'a que hora', 'hasta que hora', 'desde que hora', 'atencion', 'atienden', 'abren', 'cierran'],
    ],
    [
      'web'       => true,
      'pregunta'  => '¿Hacen delivery? ¿Cuánto tarda?',
      'respuesta' => 'Sí, hacemos delivery todos los días dentro de Monte Caseros. El tiempo estimado suele ser de 15 a 30 minutos, según la zona y la cantidad de pedidos en curso. Si preferís, también podés retirar tu pedido en el local sin cargo de envío.',
      'palabras'  => ['delivery', 'envio', 'envios', 'reparto', 'reparten', 'llevan', 'domicilio', 'a domicilio', 'cuanto tarda', 'demora', 'tarda', 'retiro', 'retirar'],
    ],
    [
      'web'       => true,
      'pregunta'  => '¿Qué medios de pago aceptan?',
      'respuesta' => 'Podés pagar en efectivo o con tarjeta al recibir tu pedido (delivery o retiro en el local). Estamos trabajando para sumar pago online próximamente.',
      'palabras'  => ['pago', 'pagos', 'pagar', 'medios de pago', 'metodos de pago', 'tarjeta', 'efectivo', 'transferencia', 'mercadopago', 'mercado pago', 'debito', 'credito'],
    ],
    [
      'web'       => true,
      'pregunta'  => '¿Hay un pedido mínimo para delivery?',
      'respuesta' => 'No pedimos un mínimo de compra para hacer envíos dentro del centro de Monte Caseros. Para zonas más alejadas, puede aplicarse un pequeño recargo de envío que se muestra antes de confirmar el pedido.',
      'palabras'  => ['minimo', 'pedido minimo', 'compra minima', 'recargo', 'costo de envio', 'cuanto sale el envio', 'cuanto cuesta el envio', 'zonas'],
    ],
    [
      'web'       => true,
      'pregunta'  => '¿Puedo modificar o cancelar un pedido ya hecho?',
      'respuesta' => 'Sí, mientras el pedido todavía no salió en camino. Escribinos por WhatsApp lo antes posible con tu número de pedido y te ayudamos a modificarlo o cancelarlo.',
      'palabras'  => ['cancelar', 'cancelo', 'cancelacion', 'modificar', 'modifico', 'anular', 'cambiar el pedido', 'cambiar mi pedido'],
      'enlaces'   => [['texto' => 'Escribir por WhatsApp', 'url' => urlWhatsapp('Hola, quiero modificar o cancelar mi pedido.'), 'tipo' => 'wsp']],
    ],
    [
      'web'       => true,
      'pregunta'  => '¿Necesito crear una cuenta para comprar?',
      'respuesta' => 'Te recomendamos registrarte: así podés guardar tus direcciones, ver el historial de tus pedidos y armar tu Compra Semanal para repetirla con un clic. Pero también podés escribirnos directo por WhatsApp si preferís no registrarte.',
      'palabras'  => ['cuenta', 'crear cuenta', 'registrar', 'registrarme', 'registro', 'registrarse', 'necesito cuenta'],
      'enlaces'   => [['texto' => 'Crear cuenta', 'url' => 'registro.php']],
    ],
    [
      'web'       => true,
      'pregunta'  => '¿Qué pasa si un producto no tiene stock?',
      'respuesta' => 'Si un producto se queda sin stock, dejamos de mostrarlo disponible en el catálogo. Si ya lo tenías guardado en una lista de Compra Semanal, te avisamos al armar el pedido para que elijas un reemplazo o lo saques del carrito.',
      'palabras'  => ['stock', 'sin stock', 'agotado', 'agotados', 'reemplazo', 'no hay stock', 'se acabo'],
    ],

    /* ── Solo para el chatbot (información que ya está en el sitio) ── */
    [
      'web'       => false,
      'pregunta'  => '¿Dónde queda el local?',
      'respuesta' => 'Estamos en ' . $direccion . '. Atendemos todos los días de ' . NEGOCIO_HORARIO . '.',
      'palabras'  => ['direccion', 'donde estan', 'donde queda', 'donde es', 'ubicacion', 'ubicados', 'como llegar', 'mapa', 'local'],
      'enlaces'   => [['texto' => 'Ver en el mapa', 'url' => NEGOCIO_MAPS_URL]],
    ],
    [
      'web'       => false,
      'pregunta'  => '¿Cómo los contacto?',
      'respuesta' => 'Podés escribirnos por WhatsApp (es lo más rápido), llamarnos al ' . NEGOCIO_TELEFONO . ' o mandarnos un mail a ' . SITE_EMAIL . '.',
      'palabras'  => ['contacto', 'contactar', 'contactarlos', 'telefono', 'llamar', 'llamo', 'mail', 'email', 'correo', 'numero', 'whatsapp', 'wsp', 'wpp'],
      'enlaces'   => [['texto' => 'Escribir por WhatsApp', 'url' => urlWhatsapp(), 'tipo' => 'wsp']],
    ],
    [
      'web'       => false,
      'pregunta'  => '¿Puedo cambiar o devolver un producto?',
      'respuesta' => 'Si recibiste un producto en mal estado, vencido o distinto al que pediste, escribinos dentro de las 24 horas de recibido tu pedido y lo solucionamos sin costo: te lo cambiamos por uno en buen estado o te devolvemos el importe de ese producto. No aceptamos devoluciones de productos ya abiertos o consumidos, salvo que el reclamo sea por un desperfecto del producto.',
      'palabras'  => ['cambio', 'cambios', 'devolucion', 'devoluciones', 'devolver', 'reclamo', 'reclamar', 'vencido', 'mal estado', 'garantia', 'politica de cambio', 'politicas'],
      'enlaces'   => [
        ['texto' => 'Ver políticas', 'url' => 'politicas.php'],
        ['texto' => 'Hacer un reclamo', 'url' => urlWhatsapp('Hola, tengo un reclamo sobre un pedido.'), 'tipo' => 'wsp'],
      ],
    ],
    [
      'web'       => false,
      'pregunta'  => '¿Qué hago si hubo un error en mi pedido?',
      'respuesta' => 'Si el error fue nuestro (te llevamos un producto distinto o falta algo de tu compra), lo resolvemos sin cargo: te acercamos lo que falta o te devolvemos ese importe, según prefieras. Escribinos por WhatsApp con tu número de pedido.',
      'palabras'  => ['error en mi pedido', 'error en el pedido', 'falta algo', 'me falta', 'me trajeron otra cosa', 'pedido equivocado', 'no me trajeron', 'faltante'],
      'enlaces'   => [['texto' => 'Escribir por WhatsApp', 'url' => urlWhatsapp('Hola, hubo un error en mi pedido.'), 'tipo' => 'wsp']],
    ],
    [
      'web'       => false,
      'pregunta'  => '¿Qué es la Compra Semanal?',
      'respuesta' => 'Es una lista de los productos que comprás seguido: la armás una vez en Mi cuenta y después la repetís con un clic. Necesitás tener una cuenta para usarla.',
      'palabras'  => ['compra semanal', 'lista semanal', 'repetir compra', 'repetir pedido', 'repetir mi pedido', 'lista de compras'],
      'enlaces'   => [['texto' => 'Ir a Compra Semanal', 'url' => 'mi-cuenta.php?seccion=compra-semanal']],
    ],
    [
      'web'       => false,
      'pregunta'  => '¿Qué hacen con mis datos?',
      'respuesta' => 'Los datos que nos dejás al registrarte (nombre, teléfono, direcciones) se usan únicamente para gestionar tus pedidos y comunicarnos con vos. No los compartimos con terceros.',
      'palabras'  => ['privacidad', 'mis datos', 'datos personales', 'proteccion de datos', 'terminos', 'condiciones'],
      'enlaces'   => [['texto' => 'Ver términos y condiciones', 'url' => 'terminos.php']],
    ],
    [
      'web'       => false,
      'pregunta'  => '¿Puedo trabajar con ustedes?',
      'respuesta' => 'Si querés trabajar con nosotros, mandanos tu CV por WhatsApp.',
      'palabras'  => ['trabajar', 'trabajo', 'empleo', 'cv', 'curriculum', 'busqueda laboral', 'necesitan gente'],
      'enlaces'   => [['texto' => 'Enviar mi CV', 'url' => urlWhatsapp(WP_MSG_TRABAJO), 'tipo' => 'wsp']],
    ],
    [
      'web'       => false,
      'pregunta'  => '¿Tienen redes sociales?',
      'respuesta' => 'Sí, nos encontrás en Instagram y en Facebook.',
      'palabras'  => ['instagram', 'facebook', 'redes', 'redes sociales', 'insta'],
      'enlaces'   => [
        ['texto' => 'Instagram', 'url' => INSTAGRAM_URL],
        ['texto' => 'Facebook', 'url' => FACEBOOK_URL],
      ],
    ],

  ];
}

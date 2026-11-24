<?php
$pageCss = 'estatica.css';
$pageTitle = 'Preguntas frecuentes';
$pageDescription = 'Respuestas a las preguntas más comunes sobre El Rápido: horarios, delivery, medios de pago, promociones y cómo hacer tu pedido.';
include 'includes/header.php';
require_once 'includes/iconos.php';
require_once 'includes/faq_datos.php';
require_once 'includes/estatica_hero.php';

// Las preguntas viven en includes/faq_datos.php (las usa también el chatbot).
$preguntasWeb = array_values(array_filter(obtenerPreguntasFrecuentes(), function ($f) {
  return !empty($f['web']);
}));

renderHeroEstatica([
  'pagina' => 'faq', 'icono' => 'pregunta', 'kicker' => 'AYUDA',
  'titulo' => 'Preguntas', 'acento' => 'frecuentes',
  'subtitulo' => 'Lo que más nos preguntan nuestros clientes: horarios, delivery, pagos y cómo pedir.',
]);
?>

<div class="est-wrap">

  <!-- Buscador: filtra las preguntas mientras se escribe (sin JavaScript se ven todas) -->
  <div class="faq-barra" data-faq-barra hidden>
    <label class="faq-buscar">
      <span class="sr-only">Buscar una pregunta</span>
      <?= icono('buscar', '', 18) ?>
      <input type="search" id="faq-buscar" placeholder="Buscá una pregunta, por ejemplo “delivery”" autocomplete="off">
    </label>
    <button type="button" class="faq-todas" id="faq-todas" aria-pressed="false">Abrir todas</button>
  </div>
  <p class="faq-cuenta" id="faq-cuenta" role="status" aria-live="polite"><?= count($preguntasWeb) ?> preguntas</p>

  <div class="faq-lista" id="faq-lista">
    <?php foreach ($preguntasWeb as $i => $faq): ?>
    <details class="faq-item" data-reveal<?= $i === 0 ? ' open' : '' ?>>
      <summary>
        <span class="faq-num" aria-hidden="true"><?= str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT) ?></span>
        <span class="faq-pregunta"><?= htmlspecialchars($faq['pregunta']) ?></span>
        <span class="faq-chevron" aria-hidden="true"><?= icono('chevron-abajo', '', 18) ?></span>
      </summary>
      <div class="faq-respuesta"><p><?= htmlspecialchars($faq['respuesta']) ?></p></div>
    </details>
    <?php endforeach; ?>
  </div>

  <p class="faq-vacio" id="faq-vacio" hidden>No encontramos preguntas con eso. Probá con otra palabra o escribinos directamente.</p>

  <?php renderCtaEstatica('¿No encontraste lo que buscabas?', 'Hola, tengo una consulta sobre El Rápido.'); ?>
</div>

<script src="<?= htmlspecialchars(assetUrl('assets/js/estatica.js')) ?>" defer></script>
<?php include 'includes/footer.php'; ?>

<?php
// ════════════════════════════════════════════════════════════════
//  includes/estatica_hero.php — CABECERA COMÚN de Preguntas frecuentes, Políticas y Términos
// ════════════════════════════════════════════════════════════════
//  Mismo lenguaje visual que los heroes de Inicio y Nosotros: fondo crema con rayas diagonales que se
//  desvanecen, título en Bebas Neue con acento rojo y franja amarilla, y un acceso rápido entre las tres páginas.
//  $d: pagina (clave), icono, kicker, titulo, acento (última palabra resaltada), subtitulo, meta (opcional)
function renderHeroEstatica(array $d): void {
  $paginas = [
    'faq'       => ['preguntas-frecuentes.php', 'Preguntas frecuentes', 'pregunta'],
    'politicas' => ['politicas.php', 'Políticas de la tienda', 'escudo-check'],
    'terminos'  => ['terminos.php', 'Términos y condiciones', 'documento'],
  ];
  ?>
  <section class="est-hero">
    <div class="est-hero-inner">
      <div class="est-hero-texto">
        <nav class="est-migas" aria-label="Ruta de navegación">
          <a href="index.php">Inicio</a><span aria-hidden="true">›</span><span aria-current="page"><?= htmlspecialchars($paginas[$d['pagina']][1]) ?></span>
        </nav>
        <span class="est-kicker"><?= htmlspecialchars($d['kicker']) ?></span>
        <h1 class="est-titulo"><?= htmlspecialchars($d['titulo']) ?> <span class="est-acento"><?= htmlspecialchars($d['acento']) ?></span></h1>
        <p class="est-subtitulo"><?= htmlspecialchars($d['subtitulo']) ?></p>
        <?php if (!empty($d['meta'])): ?><span class="est-meta"><?= icono('reloj', '', 14) ?> <?= htmlspecialchars($d['meta']) ?></span><?php endif; ?>
        <nav class="est-tabs" aria-label="Información del sitio">
          <?php foreach ($paginas as $clave => [$url, $nombre, $ico]): ?>
            <a href="<?= $url ?>" class="est-tab<?= $clave === $d['pagina'] ? ' active' : '' ?>"<?= $clave === $d['pagina'] ? ' aria-current="page"' : '' ?>><?= icono($ico, '', 16) ?> <?= htmlspecialchars($nombre) ?></a>
          <?php endforeach; ?>
        </nav>
      </div>
      <div class="est-hero-deco" aria-hidden="true">
        <span class="est-deco-circulo"><?= icono($d['icono'], '', 72) ?></span>
        <span class="est-deco-punto est-deco-punto--1"></span><span class="est-deco-punto est-deco-punto--2"></span>
      </div>
    </div>
  </section>
  <?php
}

// Tarjeta final de contacto por WhatsApp (igual en las tres páginas)
function renderCtaEstatica(string $pregunta, string $mensaje): void {
  ?>
  <aside class="est-cta" data-reveal>
    <span class="est-cta-ico" aria-hidden="true"><?= icono('mensaje', '', 26) ?></span>
    <div class="est-cta-txt"><strong><?= htmlspecialchars($pregunta) ?></strong><span>Respondemos todos los días en el horario de atención.</span></div>
    <a href="<?= htmlspecialchars(urlWhatsapp($mensaje)) ?>" class="est-cta-btn" target="_blank" rel="noopener"><?= icono('mensaje', '', 16) ?> Escribinos por WhatsApp<span class="sr-only"> (se abre en una pestaña nueva)</span></a>
  </aside>
  <?php
}

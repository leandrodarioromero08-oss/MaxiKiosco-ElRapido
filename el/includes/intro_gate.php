<?php
/* Pantalla de inicio de la intro cinematográfica (solo se incluye en index.php, ver header.php).
 * Se ve únicamente cuando <html> tiene la clase "intro-on" (la pone un script del <head> antes de pintar).
 * La lógica está en assets/js/intro.js y los estilos en assets/css/intro.css. */
?>
<div class="ig" id="intro-gate" role="dialog" aria-modal="true" aria-label="Introducción de El Rápido" data-intro-src="<?= htmlspecialchars(assetUrl('assets/intro/intro.html')) ?>">
  <div class="ig-stage" id="ig-stage"></div>
  <div class="ig-front" id="ig-front">
    <div class="ig-rain" aria-hidden="true"></div>
    <button type="button" class="ig-logo" id="ig-start" aria-describedby="ig-hint">
      <span class="ig-logo-in"><img src="assets/img/logo-el-rapido-inicio.png" width="360" height="120" alt="El Rápido: tocá el logo para comenzar la intro" decoding="async"></span>
      <span class="ig-ring" aria-hidden="true"></span>
    </button>
    <p class="ig-hint" id="ig-hint" aria-live="polite">Preparando la entrada…</p>
  </div>
  <div class="ig-veil" aria-hidden="true"></div>
  <div class="ig-ctl">
    <button type="button" class="ig-btn" id="ig-mute" aria-pressed="false" aria-label="Silenciar la intro" title="Sonido">
      <svg class="i-on" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M11 5 6 9H3v6h3l5 4z" fill="currentColor"/><path d="M15.5 8.5a5 5 0 0 1 0 7M18.5 5.5a9 9 0 0 1 0 13"/></svg>
      <svg class="i-off" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M11 5 6 9H3v6h3l5 4z" fill="currentColor"/><path d="m22 9-6 6M16 9l6 6"/></svg>
    </button>
    <button type="button" class="ig-btn ig-btn--txt" id="ig-skip" aria-label="Saltar la intro e ir al sitio">Saltar intro
      <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 6 7 6-7 6M14 6l7 6-7 6"/></svg>
    </button>
  </div>
</div>

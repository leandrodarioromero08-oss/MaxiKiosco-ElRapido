<?php
/* ============================================================
   CHATBOT_WIDGET.PHP — burbuja + ventana del asistente
   Se incluye desde includes/footer.php, así aparece en todas las
   páginas públicas. Estilos: assets/css/chatbot.css
   Lógica del navegador: assets/js/chatbot.js
   ============================================================ */

$chatNombre = '';
if (usuarioLogueado() && !empty($_SESSION['usuario']['nombre'])) {
  $partesNombre = preg_split('/\s+/', trim($_SESSION['usuario']['nombre']));
  $chatNombre = $partesNombre[0] ?? '';
}
?>
<!-- ======= CHATBOT ======= -->
<div class="cb" id="chatbot"
     data-nombre="<?= htmlspecialchars($chatNombre) ?>"
     data-wsp="<?= htmlspecialchars(urlWhatsapp()) ?>">

  <button type="button" class="cb-launcher" id="cb-abrir" aria-expanded="false" aria-controls="cb-panel" aria-label="Abrir el chat de ayuda">
    <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
      <path d="M21 11.5a8.4 8.4 0 0 1-9 8.4 8.6 8.6 0 0 1-3.6-.8L3 21l1.9-5.2A8.4 8.4 0 0 1 3 11.5 8.5 8.5 0 0 1 12 3a8.5 8.5 0 0 1 9 8.5Z"/>
      <path d="M8.5 11.5h.01M12 11.5h.01M15.5 11.5h.01"/>
    </svg>
    <span class="cb-launcher-texto">Ayuda</span>
  </button>

  <section class="cb-panel" id="cb-panel" role="dialog" aria-label="Asistente de El Rápido" hidden>
    <header class="cb-head">
      <img class="cb-avatar" src="assets/img/icono-el-rapido.png" alt="">
      <div class="cb-head-txt">
        <strong>Asistente El Rápido</strong>
        <span>Respuestas al instante</span>
      </div>
      <button type="button" class="cb-cerrar" id="cb-cerrar" aria-label="Cerrar el chat">
        <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true" focusable="false"><path d="M6 6l12 12M18 6L6 18"/></svg>
      </button>
    </header>

    <div class="cb-log" id="cb-log" role="log" aria-live="polite"></div>
    <div class="cb-chips" id="cb-chips"></div>

    <form class="cb-form" id="cb-form" autocomplete="off">
      <label class="cb-sr" for="cb-input">Escribí tu consulta</label>
      <input type="text" id="cb-input" maxlength="200" placeholder="Escribí tu consulta…" enterkeyhint="send">
      <button type="submit" class="cb-enviar" id="cb-enviar" aria-label="Enviar mensaje">
        <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M22 2 11 13M22 2l-7 20-4-9-9-4 20-7Z"/></svg>
      </button>
    </form>
  </section>
</div>

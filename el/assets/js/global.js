/* global.js
 * JS mínimo compartido por todo el sitio público: menú hamburguesa
 * (mobile) y dropdown de "Mi cuenta". Se carga desde includes/footer.php,
 * así que está disponible en cualquier página que incluya el footer.
 */

/* HEADER
 * Arriba de todo la barra va sólida; al bajar se le agrega .site-header--scrolled
 * y pasa a translúcida con blur (el CSS hace la transición). Se lee con rAF para
 * no ejecutar trabajo en cada evento de scroll. */
(function () {
  var header = document.getElementById('site-header');
  if (!header) return;
  var pendiente = false;
  function actualizar() {
    header.classList.toggle('site-header--scrolled', window.scrollY > 8);
    pendiente = false;
  }
  window.addEventListener('scroll', function () {
    if (pendiente) return;
    pendiente = true;
    requestAnimationFrame(actualizar);
  }, { passive: true });
  actualizar();
})();

function toggleMenu() {
  const menu = document.getElementById('mobile-menu');
  const btn = document.querySelector('.btn-hamburger');
  const open = menu.classList.toggle('open');
  btn.setAttribute('aria-expanded', open);
  btn.setAttribute('aria-label', open ? 'Cerrar menú' : 'Abrir menú'); // MEJORA: la etiqueta describe la acción
}

function toggleCuenta() {
  const menu = document.getElementById('cuenta-menu');
  const btn = document.getElementById('btn-cuenta');
  if (!menu) return;
  const open = menu.classList.toggle('open');
  btn.setAttribute('aria-expanded', open);
}

document.addEventListener('click', function (e) {
  const dropdown = document.querySelector('.cuenta-dropdown');
  if (dropdown && !dropdown.contains(e.target)) {
    const menu = document.getElementById('cuenta-menu');
    const btn = document.getElementById('btn-cuenta');
    if (menu) menu.classList.remove('open');
    if (btn) btn.setAttribute('aria-expanded', false);
  }
});

/* Accesibilidad: Escape cierra el menú de cuenta y el menú móvil, y devuelve el foco al botón que los abrió */
document.addEventListener('keydown', function (e) {
  if (e.key !== 'Escape') return;
  const menuCuenta = document.getElementById('cuenta-menu');
  const btnCuenta = document.getElementById('btn-cuenta');
  if (menuCuenta && menuCuenta.classList.contains('open')) {
    menuCuenta.classList.remove('open');
    if (btnCuenta) { btnCuenta.setAttribute('aria-expanded', 'false'); btnCuenta.focus(); }
    return;
  }
  const menuMovil = document.getElementById('mobile-menu');
  const btnMovil = document.querySelector('.btn-hamburger');
  if (menuMovil && menuMovil.classList.contains('open')) {
    menuMovil.classList.remove('open');
    if (btnMovil) { btnMovil.setAttribute('aria-expanded', 'false'); btnMovil.focus(); }
  }
});

/* Ajuste puramente visual: si el carrito está vacío (contador en "0"),
 * ocultamos el numerito y dejamos solo el ícono. No toca el cálculo
 * real del carrito, solo la presentación del badge ya renderizado. */
document.addEventListener('DOMContentLoaded', function () {
  const badge = document.getElementById('carrito-badge');
  if (badge && badge.textContent.trim() === '0') {
    badge.classList.add('carrito-badge--vacio');
  }
});

/* MODO OSCURO
 * El tema ya se aplica antes de pintar (script del <head> en includes/header.php).
 * Acá solo se maneja el botón: cambia entre claro y oscuro, lo recuerda en este
 * navegador (localStorage) y mantiene al día el texto accesible del botón. */
(function () {
  function temaActual() {
    return document.documentElement.getAttribute('data-theme') === 'dark' ? 'dark' : 'light';
  }
  function reflejarTema() {
    var oscuro = temaActual() === 'dark';
    var btn = document.getElementById('btn-tema');
    if (btn) {
      btn.setAttribute('aria-pressed', oscuro ? 'true' : 'false');
      btn.setAttribute('aria-label', oscuro ? 'Desactivar modo oscuro' : 'Activar modo oscuro');
      btn.setAttribute('title', oscuro ? 'Modo claro' : 'Modo oscuro');
    }
    var meta = document.querySelector('meta[name="theme-color"]');
    if (meta) meta.setAttribute('content', oscuro ? '#1a1a1a' : '#D91F26');
  }
  document.addEventListener('DOMContentLoaded', function () {
    reflejarTema();
    var btn = document.getElementById('btn-tema');
    if (!btn) return;
    btn.addEventListener('click', function () {
      var nuevo = temaActual() === 'dark' ? 'light' : 'dark';
      document.documentElement.setAttribute('data-theme', nuevo);
      try { localStorage.setItem('tema', nuevo); } catch (e) { /* modo privado: el cambio vale solo para esta visita */ }
      reflejarTema();
    });
  });
})();

/* Fundido automático noche ↔ día de la foto del negocio (.foto-hero--ciclo).
 * MEJORA (WCAG 2.2.2): el contenido que cambia solo durante más de 5 s ahora se puede pausar
 * (botón #fotoPausa), se pausa solo mientras el cursor o el foco están sobre la foto, y el
 * cuadro que no se ve queda con aria-hidden. Con "reducir movimiento" no arranca. */
(function () {
  var reducir = window.matchMedia && matchMedia('(prefers-reduced-motion: reduce)').matches;

  document.querySelectorAll('.foto-hero--ciclo').forEach(function (fotoHero) {
    var marcos = fotoHero.querySelectorAll('.foto-hero-marco');
    if (marcos.length < 2) return;
    var btn = fotoHero.parentNode.querySelector('#fotoPausa') || fotoHero.querySelector('#fotoPausa');

    var indiceActivo = 0;
    function pintar() {
      marcos.forEach(function (marco, i) {
        marco.classList.toggle('foto-hero-marco--activa', i === indiceActivo);
        marco.setAttribute('aria-hidden', i === indiceActivo ? 'false' : 'true');
      });
    }
    pintar();
    if (reducir) { if (btn) btn.hidden = true; return; }

    var pausadoPorUsuario = false, pausadoPorFoco = false;
    setInterval(function () {
      if (document.hidden || pausadoPorUsuario || pausadoPorFoco) return;
      indiceActivo = (indiceActivo + 1) % marcos.length;
      pintar();
    }, 5500);

    var zona = fotoHero.parentNode;
    ['mouseenter', 'focusin'].forEach(function (ev) { zona.addEventListener(ev, function () { pausadoPorFoco = true; }); });
    ['mouseleave', 'focusout'].forEach(function (ev) { zona.addEventListener(ev, function () { pausadoPorFoco = false; }); });

    if (btn) {
      btn.addEventListener('click', function () {
        pausadoPorUsuario = !pausadoPorUsuario;
        btn.setAttribute('aria-pressed', pausadoPorUsuario ? 'true' : 'false');
        btn.setAttribute('aria-label', pausadoPorUsuario ? 'Reanudar el cambio automático de foto día y noche' : 'Pausar el cambio automático de foto día y noche');
      });
    }
  });
})();

/* Atajo de teclado al login del administrador: Alt + Shift + A.
 * No actúa mientras se escribe en un campo. Si ya hay sesión de admin, login.php
 * redirige solo al panel. */
document.addEventListener('keydown', function (e) {
  if (!(e.altKey && e.shiftKey && !e.ctrlKey && !e.metaKey && e.code === 'KeyA')) return;
  var el = document.activeElement;
  if (el && (el.isContentEditable || /^(INPUT|TEXTAREA|SELECT)$/.test(el.tagName))) return;
  e.preventDefault();
  window.location.href = 'admin/login.php';
});

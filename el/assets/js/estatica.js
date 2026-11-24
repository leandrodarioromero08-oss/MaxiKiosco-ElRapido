/* estatica.js — Preguntas frecuentes, Políticas y Términos
 * 1) Índice lateral ("En esta página"): se arma con las secciones [data-toc] y resalta la que se está leyendo.
 * 2) Preguntas frecuentes: buscador en vivo y "Abrir todas / Cerrar todas".
 * Mejora progresiva: sin JavaScript todo el contenido se lee igual (el índice y el buscador no aparecen). */
(function () {
  'use strict';
  var D = document;
  var $ = function (s, r) { return (r || D).querySelector(s); };
  var $$ = function (s, r) { return Array.prototype.slice.call((r || D).querySelectorAll(s)); };

  /* ---------- 1) Índice con resaltado ---------- */
  var toc = $('#est-toc');
  if (toc) {
    var secs = $$('.est-sec[data-toc]');
    secs.forEach(function (s) {
      var li = D.createElement('li'), a = D.createElement('a');
      a.href = '#' + s.id; a.textContent = s.getAttribute('data-toc'); a.setAttribute('data-sec', s.id);
      li.appendChild(a); toc.appendChild(li);
    });
    var links = $$('a', toc), cont = toc.closest('.est-toc');
    function marcar(id) {
      links.forEach(function (a) {
        var on = a.getAttribute('data-sec') === id;
        a.classList.toggle('active', on);
        if (on) a.setAttribute('aria-current', 'true'); else a.removeAttribute('aria-current');
        if (on && cont && cont.scrollWidth > cont.clientWidth) {   // en móvil el índice es una fila con scroll: mantener visible el activo
          cont.scrollTo({ left: a.offsetLeft - 24, behavior: 'smooth' });
        }
      });
    }
    if (links.length) marcar(secs[0].id);
    if ('IntersectionObserver' in W()) {
      var visibles = {};
      var io = new IntersectionObserver(function (es) {
        es.forEach(function (e) { visibles[e.target.id] = e.isIntersecting; });
        for (var i = 0; i < secs.length; i++) { if (visibles[secs[i].id]) { marcar(secs[i].id); break; } }
      }, { rootMargin: '-25% 0px -60% 0px' });
      secs.forEach(function (s) { io.observe(s); });
    }
  }
  function W() { return window; }

  /* ---------- 2) Preguntas frecuentes ---------- */
  var lista = $('#faq-lista');
  if (lista) {
    var items = $$('.faq-item', lista), barra = $('[data-faq-barra]'), q = $('#faq-buscar'), cuenta = $('#faq-cuenta'), vacio = $('#faq-vacio'), todas = $('#faq-todas');
    if (barra) barra.hidden = false;
    var norm = function (t) { return String(t).toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, ''); };
    var textos = items.map(function (it) { return norm(it.textContent); });
    var total = items.length;

    function actualizarCuenta(n, hay) {
      cuenta.textContent = hay ? (n === 1 ? '1 pregunta coincide' : n + ' de ' + total + ' preguntas coinciden') : total + ' preguntas';
    }
    function filtrar() {
      var t = norm(q.value.trim()), n = 0;
      items.forEach(function (it, i) {
        var ok = !t || textos[i].indexOf(t) !== -1;
        it.hidden = !ok; if (ok) n++;
        if (t && ok) it.open = true;
      });
      vacio.hidden = n !== 0;
      actualizarCuenta(n, !!t);
    }
    if (q) q.addEventListener('input', filtrar);

    if (todas) todas.addEventListener('click', function () {
      var abrir = todas.getAttribute('aria-pressed') !== 'true';
      items.forEach(function (it) { if (!it.hidden) it.open = abrir; });
      todas.setAttribute('aria-pressed', abrir ? 'true' : 'false');
      todas.textContent = abrir ? 'Cerrar todas' : 'Abrir todas';
    });
  }
})();

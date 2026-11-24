/* assets/js/notificaciones.js — AVISOS AL CLIENTE (abajo a la derecha)
 * Solo se carga si el cliente tiene sesión iniciada (header.php / footer.php).
 *  - Cada ~20 s pregunta a notificaciones_api.php si hay avisos nuevos (por ejemplo "tu pedido va en camino").
 *  - Pausa la consulta cuando la pestaña está en segundo plano y consulta de inmediato al volver.
 *  - Cada aviso se muestra una sola vez: al mostrarlo se marca como visto en el servidor.
 *  - window.ElRapido.notificar(titulo, mensaje, { tipo, href, textoLink }) permite lanzar uno desde cualquier script.
 */
(function () {
  'use strict';
  var D = document, W = window;
  var INTERVALO = 20000, MAX_VISIBLES = 3, DURACION = 10000;
  var region = null, timer = null, enCurso = false, fallos = 0, detenido = false, mostradas = {};

  function esc(s) {
    return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  }
  function svg(trazos) {
    return '<svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' + trazos + '</svg>';
  }
  var ICONOS = {
    en_camino:  svg('<path d="M14 18V6a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2v11a1 1 0 0 0 1 1h2"/><path d="M15 18H9"/><path d="M19 18h2a1 1 0 0 0 1-1v-3.65a1 1 0 0 0-.22-.624l-3.48-4.35A1 1 0 0 0 17.52 8H14"/><circle cx="17" cy="18" r="2"/><circle cx="7" cy="18" r="2"/>'),
    listo:      svg('<path d="m16 16 2 2 4-4"/><path d="M21 10V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l2-1.14"/><path d="m7.5 4.27 9 5.15"/><polyline points="3.29 7 12 12 20.71 7"/><line x1="12" x2="12" y1="22" y2="12"/>'),
    preparando: svg('<circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>'),
    entregado:  svg('<path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/>'),
    cancelado:  svg('<circle cx="12" cy="12" r="10"/><path d="m15 9-6 6"/><path d="m9 9 6 6"/>'),
    info:       svg('<path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"/>')
  };
  var CERRAR = svg('<path d="M18 6 6 18"/><path d="m6 6 12 12"/>').replace('width="22" height="22"', 'width="16" height="16"');

  function asegurarRegion() {
    if (region) return region;
    region = D.createElement('div');
    region.className = 'notif-region';
    region.setAttribute('aria-live', 'polite');
    region.setAttribute('aria-atomic', 'false');
    D.body.appendChild(region);
    return region;
  }

  function reducirMovimiento() {
    return W.matchMedia && W.matchMedia('(prefers-reduced-motion: reduce)').matches;
  }

  /* Muestra una tarjeta. o: { tipo, href, textoLink } */
  function notificar(titulo, mensaje, o) {
    o = o || {};
    var tipo = ICONOS[o.tipo] ? o.tipo : 'info';
    var reg = asegurarRegion();
    if (W.ElRapidoSonido) W.ElRapidoSonido.play(tipo === 'cancelado' ? 'error' : 'notif');
    while (reg.children.length >= MAX_VISIBLES) reg.removeChild(reg.firstChild);   // la más vieja sale

    var el = D.createElement('div');
    el.className = 'notif notif--' + tipo;
    el.setAttribute('role', 'status');
    el.innerHTML =
      '<span class="notif-ico">' + ICONOS[tipo] + '</span>' +
      '<div class="notif-cuerpo">' +
        '<strong class="notif-titulo">' + esc(titulo) + '</strong>' +
        (mensaje ? '<span class="notif-txt">' + esc(mensaje) + '</span>' : '') +
        (o.href ? '<a class="notif-link" href="' + esc(o.href) + '">' + esc(o.textoLink || 'Ver pedido') + '</a>' : '') +
      '</div>' +
      '<button type="button" class="notif-x" aria-label="Cerrar aviso">' + CERRAR + '</button>';
    reg.appendChild(el);

    var t = null, cerrado = false;
    function cerrar() {
      if (cerrado) return;
      cerrado = true;
      clearTimeout(t);
      if (reducirMovimiento()) { el.remove(); return; }
      el.classList.add('notif--sale');
      setTimeout(function () { el.remove(); }, 260);
    }
    function programar(ms) { clearTimeout(t); t = setTimeout(cerrar, ms); }
    el.querySelector('.notif-x').addEventListener('click', cerrar);
    el.addEventListener('mouseenter', function () { clearTimeout(t); });   // WCAG 2.2.1: no se va mientras lo estás leyendo
    el.addEventListener('mouseleave', function () { programar(3500); });
    el.addEventListener('focusin',    function () { clearTimeout(t); });
    el.addEventListener('focusout',   function () { programar(3500); });
    programar(DURACION);
    return el;
  }

  function marcarVistas(ids) {
    if (!ids.length) return;
    var cuerpo = new URLSearchParams();
    cuerpo.append('accion', 'vistas');
    cuerpo.append('csrf_token', W.CSRF_TOKEN || '');
    ids.forEach(function (id) { cuerpo.append('ids[]', id); });
    fetch('notificaciones_api.php', { method: 'POST', credentials: 'same-origin', body: cuerpo }).catch(function () {});
  }

  function mostrarVarias(items) {
    var nuevas = [];
    items.forEach(function (it) {
      if (mostradas[it.id]) return;
      mostradas[it.id] = true;
      notificar(it.titulo, it.mensaje, {
        tipo: it.tipo,
        href: it.pedido_id ? 'pedido_detalle.php?id=' + encodeURIComponent(it.pedido_id) : '',
        textoLink: 'Ver pedido'
      });
      nuevas.push(it.id);
    });
    marcarVistas(nuevas);
  }

  function programarConsulta() {
    clearTimeout(timer);
    if (detenido) return;
    timer = setTimeout(consultar, INTERVALO * Math.min(1 + fallos, 6));   // si falla, espera cada vez más
  }

  function consultar() {
    if (detenido) return;
    if (enCurso || D.hidden) { programarConsulta(); return; }
    enCurso = true;
    fetch('notificaciones_api.php', { credentials: 'same-origin', cache: 'no-store', headers: { 'Accept': 'application/json' } })
      .then(function (r) { if (!r.ok) throw new Error('http ' + r.status); return r.json(); })
      .then(function (d) {
        fallos = 0;
        if (d && d.logueado === false) { detenido = true; return; }   // cerró sesión: no seguir preguntando
        if (d && d.items && d.items.length) mostrarVarias(d.items);
      })
      .catch(function () { fallos++; })
      .then(function () { enCurso = false; programarConsulta(); });
  }

  D.addEventListener('visibilitychange', function () {
    if (!D.hidden && !detenido) { clearTimeout(timer); consultar(); }   // al volver a la pestaña, se revisa enseguida
  });

  W.ElRapido = W.ElRapido || {};
  W.ElRapido.notificar = notificar;

  setTimeout(consultar, 1500);   // la primera consulta no compite con la carga de la página
})();

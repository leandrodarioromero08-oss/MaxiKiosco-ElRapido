/* admin/assets/js/notificaciones.js — AVISOS DEL PANEL (abajo a la derecha)
 * Se carga desde admin/Template/topbar.php, o sea en TODAS las páginas del admin.
 *  1) Pedidos nuevos: cada ~20 s pregunta a Seccion/notificaciones_api.php y, si entró un pedido,
 *     muestra un aviso. El último pedido "visto" se guarda en sessionStorage, así al cambiar de
 *     sección no se repiten ni se pierden avisos.
 *  2) Mensajes de resultado ("Estado del pedido actualizado", errores...): los bloques
 *     .alerta[data-notif] que arma el PHP se muestran como aviso en vez de ocupar la página.
 *     Sin JavaScript siguen apareciendo en su lugar de siempre.
 *  window.AdminNotif.mostrar(titulo, mensaje, { tipo: 'exito'|'error'|'pedido'|'info', accion, alAccion })
 */
(function () {
  'use strict';
  var D = document, W = window;
  var INTERVALO = 20000, MAX_VISIBLES = 4, CLAVE = 'admin_notif_ultimo_pedido';
  var region = null, timer = null, enCurso = false, fallos = 0, detenido = false;

  function esc(s) {
    return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  }
  function svg(trazos, t) {
    t = t || 20;
    return '<svg viewBox="0 0 24 24" width="' + t + '" height="' + t + '" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' + trazos + '</svg>';
  }
  var ICONOS = {
    pedido: svg('<circle cx="8" cy="21" r="1"/><circle cx="19" cy="21" r="1"/><path d="M2.05 2.05h2l2.66 12.42a2 2 0 0 0 2 1.58h9.78a2 2 0 0 0 2-1.58l1.65-7.42H5.12"/>'),
    exito:  svg('<path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/>'),
    error:  svg('<circle cx="12" cy="12" r="10"/><line x1="12" x2="12" y1="8" y2="12"/><line x1="12" x2="12.01" y1="16" y2="16"/>'),
    info:   svg('<path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"/>')
  };
  var CERRAR = svg('<path d="M18 6 6 18"/><path d="m6 6 12 12"/>', 16);

  function asegurarRegion() {
    if (region) return region;
    region = D.createElement('div');
    region.className = 'anotif-region';
    region.setAttribute('aria-live', 'polite');
    region.setAttribute('aria-atomic', 'false');
    D.body.appendChild(region);
    return region;
  }
  function reducirMovimiento() {
    return W.matchMedia && W.matchMedia('(prefers-reduced-motion: reduce)').matches;
  }

  function mostrar(titulo, mensaje, o) {
    o = o || {};
    var tipo = ICONOS[o.tipo] ? o.tipo : 'info';
    if (W.ElRapidoSonido) W.ElRapidoSonido.play(tipo === 'pedido' ? 'pedido' : (tipo === 'error' ? 'error' : (tipo === 'exito' ? 'success' : 'notif')));
    var duracion = o.duracion || (tipo === 'error' ? 12000 : (tipo === 'pedido' ? 15000 : 7000));
    var reg = asegurarRegion();
    while (reg.children.length >= MAX_VISIBLES) reg.removeChild(reg.firstChild);

    var el = D.createElement('div');
    el.className = 'anotif anotif--' + tipo;
    el.setAttribute('role', tipo === 'error' ? 'alert' : 'status');
    el.innerHTML =
      '<span class="anotif-ico">' + ICONOS[tipo] + '</span>' +
      '<div class="anotif-cuerpo">' +
        (titulo ? '<strong class="anotif-titulo">' + esc(titulo) + '</strong>' : '') +
        (mensaje ? '<span class="anotif-txt">' + esc(mensaje) + '</span>' : '') +
        (o.accion ? '<button type="button" class="anotif-accion">' + esc(o.accion) + '</button>' : '') +
      '</div>' +
      '<button type="button" class="anotif-x" aria-label="Cerrar aviso">' + CERRAR + '</button>';
    reg.appendChild(el);

    var t = null, cerrado = false;
    function cerrar() {
      if (cerrado) return;
      cerrado = true; clearTimeout(t);
      if (reducirMovimiento()) { el.remove(); return; }
      el.classList.add('anotif--sale');
      setTimeout(function () { el.remove(); }, 240);
    }
    function programar(ms) { clearTimeout(t); t = setTimeout(cerrar, ms); }
    el.querySelector('.anotif-x').addEventListener('click', cerrar);
    var b = el.querySelector('.anotif-accion');
    if (b) b.addEventListener('click', function () { cerrar(); if (o.alAccion) o.alAccion(); });
    el.addEventListener('mouseenter', function () { clearTimeout(t); });
    el.addEventListener('mouseleave', function () { programar(4000); });
    el.addEventListener('focusin',    function () { clearTimeout(t); });
    el.addEventListener('focusout',   function () { programar(4000); });
    programar(duracion);
    return el;
  }

  /* ---------- 1) Pedidos nuevos ---------- */
  function ultimoVisto() {
    try { var v = sessionStorage.getItem(CLAVE); return v === null ? -1 : parseInt(v, 10); } catch (e) { return -1; }
  }
  function guardarUltimo(n) {
    try { sessionStorage.setItem(CLAVE, String(n)); } catch (e) {}
  }
  var enPedidos = /\/pedidos\.php$/.test(W.location.pathname);

  function avisarPedidos(nuevos) {
    // Muchos juntos (admin con la pestaña dormida): uno solo, para no tapar la pantalla
    if (nuevos.length > 3) {
      mostrar(nuevos.length + ' pedidos nuevos', 'Entraron mientras no mirabas el panel.', {
        tipo: 'pedido', accion: enPedidos ? 'Actualizar la lista' : 'Ver pedidos pendientes',
        alAccion: function () { irAPedidos(); }
      });
      return;
    }
    nuevos.forEach(function (p) {
      mostrar('Nuevo pedido #' + p.id, p.cliente + ' · ' + p.total + ' · ' + p.entrega, {
        tipo: 'pedido', accion: enPedidos ? 'Actualizar la lista' : 'Ver pedido',
        alAccion: function () { irAPedidos(); }
      });
    });
  }
  function irAPedidos() {
    if (enPedidos) W.location.reload();
    else W.location.href = 'pedidos.php?estado=pendiente';
  }

  function programarConsulta() {
    clearTimeout(timer);
    if (detenido) return;
    timer = setTimeout(consultar, INTERVALO * Math.min(1 + fallos, 6));
  }
  function consultar() {
    if (detenido) return;
    if (enCurso || D.hidden) { programarConsulta(); return; }
    enCurso = true;
    fetch('notificaciones_api.php?desde=' + ultimoVisto(), { credentials: 'same-origin', cache: 'no-store', headers: { 'Accept': 'application/json' } })
      .then(function (r) {
        if (r.status === 401) { detenido = true; return null; }   // sesión vencida: la próxima navegación lo manda al login
        if (!r.ok) throw new Error('http ' + r.status);
        return r.json();
      })
      .then(function (d) {
        if (!d) return;
        fallos = 0;
        var antes = ultimoVisto();
        if (d.nuevos && d.nuevos.length && antes >= 0) avisarPedidos(d.nuevos);
        if (typeof d.ultimo_id === 'number') guardarUltimo(d.ultimo_id);
      })
      .catch(function () { fallos++; })
      .then(function () { enCurso = false; programarConsulta(); });
  }
  D.addEventListener('visibilitychange', function () {
    if (!D.hidden && !detenido) { clearTimeout(timer); consultar(); }
  });

  /* ---------- 2) Mensajes de resultado (alertas del PHP) ---------- */
  function convertirAlertas() {
    var alertas = D.querySelectorAll('.alerta[data-notif]');
    for (var i = 0; i < alertas.length; i++) {
      var a = alertas[i];
      var span = a.querySelector('span');
      var txt = (span ? span.textContent : a.textContent).trim();
      if (txt) mostrar('', txt, { tipo: a.getAttribute('data-notif') === 'error' ? 'error' : 'exito' });
      a.remove();
    }
  }

  W.AdminNotif = { mostrar: mostrar };

  function iniciar() {
    convertirAlertas();
    setTimeout(consultar, 1200);   // la primera consulta fija el punto de partida (sin avisar nada)
  }
  if (D.readyState === 'loading') D.addEventListener('DOMContentLoaded', iniciar); else iniciar();
})();

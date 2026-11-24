/* assets/js/sonidos.js — EFECTOS DE SONIDO DE LA INTERFAZ (botones, enlaces, avisos)
 * Todo sintetizado con Web Audio: no hay archivos de audio que cargar.
 *  - Un solo listener de "click" para todo el sitio (incluye lo que se dibuja por JavaScript, como el mini-carrito).
 *    Según el botón elige el sonido: clic, agregar al carrito, quitar, + / − cantidad, abrir/cerrar, activar/desactivar,
 *    confirmar. Los enlaces hacen un "tick" más suave.
 *  - Los navegadores no dejan sonar nada antes de que la persona toque la página una vez: por eso el audio se prepara
 *    en el primer toque/tecla (los avisos que lleguen antes de eso salen sin sonido).
 *  - Se puede silenciar con el botón "Sonidos" (menú del botón flotante en el sitio, parlante en la barra del admin).
 *    La elección queda guardada en este navegador (localStorage "elrapido_sonidos").
 *  - Ajustes puntuales en el HTML: data-sonido="add|confirm|remove|close|click|tick|ninguno" fuerza un sonido;
 *    data-sin-sonido en un elemento (o en un contenedor) lo deja mudo.
 *  - Para otros scripts: ElRapidoSonido.play('notif' | 'error' | 'success' | 'pedido' | ...).
 */
(function () {
  'use strict';
  var D = document, W = window;
  var AC = W.AudioContext || W.webkitAudioContext;
  var CLAVE = 'elrapido_sonidos';
  var ctx = null, master = null, salida = null, ruidoBuf = null, gesto = false, ultimo = 0, activo = true;
  /* cx / dst / nz: contexto, destino y ruido "de trabajo". Normalmente son los del audio en vivo; mientras se
     pre-renderiza un sonido apuntan a un OfflineAudioContext (ver precargar). */
  var cx = null, dst = null, nz = null;
  var buffers = {};            /* nombre -> AudioBuffer ya renderizado: reproducirlo es instantáneo */
  try { activo = localStorage.getItem(CLAVE) !== '0'; } catch (e) {}

  /* ---------- audio en vivo ---------- */
  function iniciar() {
    if (ctx || !AC) return;
    try {
      try { ctx = new AC({ latencyHint: 'interactive' }); } catch (e0) { ctx = new AC(); }
      var comp = ctx.createDynamicsCompressor();
      master = ctx.createGain(); master.gain.value = .45;      /* volumen general: bajo a propósito */
      master.connect(comp); comp.connect(ctx.destination);
      salida = comp;                                           /* los buffers ya traen el volumen aplicado */
      var n = ctx.sampleRate; ruidoBuf = ctx.createBuffer(1, n, n);
      var d = ruidoBuf.getChannelData(0); for (var i = 0; i < n; i++) d[i] = Math.random() * 2 - 1;
    } catch (e) { ctx = null; }
  }
  function env(g, t, a, pk, dur) {
    g.gain.setValueAtTime(.0001, t);
    g.gain.exponentialRampToValueAtTime(pk, t + a);
    g.gain.exponentialRampToValueAtTime(.0001, t + dur);
  }
  function tono(t, tipo, f1, f2, dur, vol) {
    var o = cx.createOscillator(), g = cx.createGain(); o.type = tipo;
    o.frequency.setValueAtTime(f1, t); o.frequency.exponentialRampToValueAtTime(f2, t + dur);
    env(g, t, Math.min(.008, dur / 3), vol, dur); o.connect(g); g.connect(dst); o.start(t); o.stop(t + dur + .03);
  }
  function ruido(t, dur, f1, f2, q, vol, tipo) {
    var s = cx.createBufferSource(); s.buffer = nz; s.loop = true;
    var f = cx.createBiquadFilter(); f.type = tipo || 'bandpass'; f.Q.value = q;
    f.frequency.setValueAtTime(f1, t); f.frequency.exponentialRampToValueAtTime(f2, t + dur);
    var g = cx.createGain(); env(g, t, Math.min(.05, dur / 3), vol, dur);
    s.connect(f); f.connect(g); g.connect(dst); s.start(t); s.stop(t + dur + .03);
  }

  var SONIDOS = {
    click:     function (t) { tono(t, 'triangle', 620, 420, .06, .16); tono(t, 'sine', 1240, 840, .03, .04); },
    tick:      function (t) { tono(t, 'sine', 520, 460, .04, .08); },
    confirm:   function (t) { tono(t, 'sine', 587, 587, .09, .14); tono(t + .07, 'sine', 880, 880, .16, .14); tono(t + .07, 'triangle', 1760, 1760, .1, .03); },
    add:       function (t) { tono(t, 'sine', 280, 640, .09, .2); tono(t + .07, 'triangle', 1047, 1040, .22, .14); tono(t + .13, 'triangle', 1319, 1310, .28, .12); },
    remove:    function (t) { tono(t, 'sine', 440, 170, .14, .18); ruido(t, .08, 1200, 300, .8, .06, 'lowpass'); },
    qtyUp:     function (t) { tono(t, 'triangle', 500, 640, .06, .15); },
    qtyDown:   function (t) { tono(t, 'triangle', 560, 430, .06, .15); },
    toggleOn:  function (t) { tono(t, 'sine', 520, 520, .05, .13); tono(t + .05, 'sine', 780, 780, .09, .13); },
    toggleOff: function (t) { tono(t, 'sine', 780, 780, .05, .13); tono(t + .05, 'sine', 520, 520, .09, .13); },
    open:      function (t) { ruido(t, .16, 300, 1800, 1.4, .12); tono(t, 'sine', 380, 560, .12, .08); },
    close:     function (t) { ruido(t, .14, 1800, 300, 1.4, .1); tono(t, 'sine', 520, 330, .1, .08); },
    error:     function (t) { tono(t, 'square', 200, 160, .1, .07); tono(t + .13, 'square', 170, 130, .14, .07); },
    success:   function (t) { [659, 784, 1047].forEach(function (f, i) { tono(t + i * .07, 'sine', f, f, .25, .12); }); },
    notif:     function (t) { [880, 1318, 1760].forEach(function (f, i) { tono(t + i * .09, 'sine', f, f, .45, .11); }); },
    pedido:    function (t) { [988, 1319, 988, 1319].forEach(function (f, i) { tono(t + i * .17, 'sine', f, f, .5, .16); tono(t + i * .17, 'triangle', f * 2, f * 2, .3, .04); }); }
  };

  /* Duración (s) de cada sonido, con un pequeño margen para la cola */
  var DURACION = { click: .14, tick: .1, confirm: .4, add: .55, remove: .35, qtyUp: .12, qtyDown: .12, toggleOn: .22, toggleOff: .22,
                   open: .32, close: .3, error: .4, success: .65, notif: .95, pedido: 1.3 };

  /* PRECARGA: cada sonido se dibuja una sola vez en un AudioBuffer con OfflineAudioContext (no necesita que la
     persona toque nada antes y corre fuera del hilo principal). Después, sonar es crear UNA fuente y empezar:
     sin armar osciladores ni filtros en el momento del clic, que era lo que producía demoras y tirones. */
  var OAC = W.OfflineAudioContext || W.webkitOfflineAudioContext;
  function renderizar(nombre) {
    return new Promise(function (ok) {
      try {
        var sr = 44100, off = new OAC(1, Math.ceil(sr * DURACION[nombre]), sr);
        var g = off.createGain(); g.gain.value = .45; g.connect(off.destination);
        var n = off.createBuffer(1, sr, sr), d = n.getChannelData(0);
        for (var i = 0; i < sr; i++) d[i] = Math.random() * 2 - 1;
        var sc = cx, sd = dst, sn = nz;
        cx = off; dst = g; nz = n;
        try { SONIDOS[nombre](.001); } finally { cx = sc; dst = sd; nz = sn; }
        var fin = function (b) { if (b) buffers[nombre] = b; ok(); };
        var pr = off.startRendering();
        if (pr && pr.then) pr.then(fin, function () { ok(); });
        else off.oncomplete = function (e) { fin(e.renderedBuffer); };
      } catch (e) { ok(); }
    });
  }
  var precargado = false;
  function precargar() {
    if (precargado || !OAC || !AC) return;
    precargado = true;
    var cola = Object.keys(SONIDOS), i = 0;
    (function siguiente() {
      if (i >= cola.length) return;
      renderizar(cola[i++]).then(function () { setTimeout(siguiente, 0); });
    })();
  }

  function play(nombre) {
    if (!activo || !gesto || !AC || D.hidden) return;
    iniciar(); if (!ctx) return;
    if (ctx.state === 'suspended') { try { ctx.resume(); } catch (e) {} }
    var ahora = Date.now(); if (ahora - ultimo < 35) return; ultimo = ahora;   /* evita doble sonido por un mismo toque */
    var f = SONIDOS[nombre]; if (!f) return;
    try {
      var b = buffers[nombre];
      if (b) {                                                  /* camino rápido: sonido ya renderizado */
        var src = ctx.createBufferSource(); src.buffer = b; src.connect(salida); src.start(0);
        return;
      }
      cx = ctx; dst = master; nz = ruidoBuf;                    /* respaldo: sintetizar en el momento */
      f(ctx.currentTime + .005);
    } catch (e) {}
  }

  /* Contexto de audio COMPARTIDO: los navegadores limitan cuántos AudioContext puede tener una página
     (Safari ~4); las animaciones de Nosotros y el carrito del hero usan este mismo en vez de crear uno cada una. */
  function contexto() { iniciar(); return ctx; }

  /* El audio se habilita con el primer gesto de la persona */
  ['pointerdown', 'keydown', 'touchend'].forEach(function (n) {
    D.addEventListener(n, function () {
      gesto = true;
      if (!activo) return;
      iniciar();
      if (ctx && ctx.state === 'suspended') { try { ctx.resume(); } catch (e) {} }
    }, true);
  });

  /* ---------- qué sonido lleva cada elemento ---------- */
  var SELECTOR = 'button, a[href], input[type=button], input[type=submit], input[type=checkbox], input[type=radio], summary, [role=button], [role=tab], [role=switch]';

  function textoDe(el) {
    var v = (el.tagName === 'INPUT') ? (el.value || '') : (el.textContent || '');
    return ((el.getAttribute('aria-label') || '') + ' ' + (el.getAttribute('title') || '') + ' ' + v)
      .toLowerCase().replace(/\s+/g, ' ').trim().slice(0, 90);
  }
  function clasificar(el) {
    var forzado = el.getAttribute('data-sonido');
    if (forzado) return forzado === 'ninguno' ? null : forzado;
    var cls = typeof el.className === 'string' ? el.className.toLowerCase() : '';
    var txt = textoDe(el), todo = cls + ' ' + txt;

    if (/qty|stepper/.test(cls)) {
      if (/\+|más|\bmas\b|aumentar|sumar|qty-mas|stepper-mas/.test(todo)) return 'qtyUp';
      if (/^[−-]$|\s[−-]\s|menos|disminuir|restar|qty-menos|stepper-menos/.test(todo)) return 'qtyDown';
    }
    if (/agregar|añadir/.test(todo)) return 'add';
    if (/cancelar.*pedido|elimin|quitar|borrar|vaciar|descartar/.test(todo)) return 'remove';
    if (/cerrar|close|cancelar/.test(todo)) return 'close';
    if (el.type === 'submit' || /finalizar|confirmar|guardar|enviar|crear|registr|ingresar|iniciar sesi|pedir|comprar|continuar|aplicar|actualizar|publicar|exportar|btn-rojo|principal|primary/.test(todo)) return 'confirm';
    if (el.tagName === 'A' && !/btn|boton/.test(cls)) return 'tick';
    return 'click';
  }

  D.addEventListener('click', function (e) {
    if (!e.isTrusted || !activo) return;                       /* solo toques reales de la persona */
    var t = e.target; if (!t || !t.closest) return;
    var el = t.closest(SELECTOR); if (!el) return;
    if (el.closest('[data-sin-sonido]')) return;
    if (el.disabled || el.getAttribute('aria-disabled') === 'true') return;

    /* Estos cambian de estado en su propio click: se mira el estado ya actualizado */
    if (el.hasAttribute('aria-expanded')) {
      setTimeout(function () { play(el.getAttribute('aria-expanded') === 'true' ? 'open' : 'close'); }, 0);
      return;
    }
    if (el.type === 'checkbox' || el.type === 'radio' || el.hasAttribute('aria-pressed') || el.getAttribute('role') === 'switch') {
      setTimeout(function () {
        var on = (el.type === 'checkbox' || el.type === 'radio') ? el.checked
               : (el.getAttribute('aria-pressed') || el.getAttribute('aria-checked')) === 'true';
        play(on ? 'toggleOn' : 'toggleOff');
      }, 0);
      return;
    }
    var tipo = clasificar(el);
    if (tipo) play(tipo);
  }, true);

  /* ---------- botón para silenciar / activar ---------- */
  function svg(trazos) {
    return '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' + trazos + '</svg>';
  }
  var ALTAVOZ = '<polygon points="11 5 6 9 2 9 2 15 6 15 11 19 11 5"/>';
  var ICO_ON  = svg(ALTAVOZ + '<path d="M15.54 8.46a5 5 0 0 1 0 7.07"/><path d="M19.07 4.93a10 10 0 0 1 0 14.14"/>');
  var ICO_OFF = svg(ALTAVOZ + '<line x1="22" x2="16" y1="9" y2="15"/><line x1="16" x2="22" y1="9" y2="15"/>');

  function pintar() {
    var els = D.querySelectorAll('[data-sonidos-toggle]');
    for (var i = 0; i < els.length; i++) {
      var b = els[i];
      b.hidden = !AC;                                           /* sin Web Audio no hay nada que silenciar */
      b.setAttribute('aria-pressed', activo ? 'true' : 'false');
      b.setAttribute('aria-label', activo ? 'Silenciar los sonidos del sitio' : 'Activar los sonidos del sitio');
      b.title = activo ? 'Sonidos activados' : 'Sonidos silenciados';
      var ico = b.querySelector('.sn-ico'); if (ico) ico.innerHTML = activo ? ICO_ON : ICO_OFF;
      var tx = b.querySelector('.sn-txt'); if (tx) tx.textContent = activo ? 'Sonidos: sí' : 'Sonidos: no';
    }
  }
  D.addEventListener('click', function (e) {
    var b = e.target.closest && e.target.closest('[data-sonidos-toggle]');
    if (!b) return;
    activo = !activo;
    try { localStorage.setItem(CLAVE, activo ? '1' : '0'); } catch (err) {}
    pintar();
    gesto = true;
    if (activo) { iniciar(); precargar(); play('toggleOn'); }                /* al activar se escucha de inmediato */
  });

  W.ElRapidoSonido = {
    play: play,
    contexto: contexto,
    activo: function () { return activo; },
    setActivo: function (v) { activo = !!v; if (activo) precargar(); try { localStorage.setItem(CLAVE, activo ? '1' : '0'); } catch (e) {} pintar(); }
  };
  pintar();
  /* Se prepara en un momento libre, sin competir con la carga de la página */
  if (activo) { if (W.requestIdleCallback) W.requestIdleCallback(precargar, { timeout: 2500 }); else setTimeout(precargar, 800); }
})();

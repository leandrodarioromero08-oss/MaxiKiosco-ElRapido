/* chatbot-guia.js — Motos de El Rápido que señalan el asistente
 * - Cada vez que se carga el inicio, sale UNA moto pequeña (un SVG parecido al logo)
 *   desde el logo de El Rápido, cruza la pantalla por un punto al azar, rápido, y
 *   termina en el botón flotante del chat.
 * - Al tocar el logo, en vez de recargar el inicio, sale otra moto por otro camino
 *   al azar. Pueden volar hasta 5 a la vez (MAX_MOTOS); mientras haya 5 en el aire,
 *   los toques extra se ignoran.
 * - Cada llegada hace latir una vez el botón del chat y suena un "vroom" al salir y
 *   un tintineo al llegar (Web Audio, sin archivos). Sin textos.
 *
 * Sonido: los navegadores bloquean el audio hasta que la persona toca o hace clic en
 * la página. Al abrir la página la moto sale en silencio si todavía no hubo
 * interacción; al tocar el logo (es un clic) sí suena.
 * Con "reducir movimiento" activado en el sistema no viajan ni suenan: solo el pulso.
 * Un clic con Ctrl/Cmd/botón del medio sigue abriendo el inicio en otra pestaña.
 * Estilos del pulso: assets/css/chatbot.css (sección GUÍA).
 */
(function () {
  'use strict';

  var MAX_MOTOS = 5;          // motos en el aire a la vez
  var ESPERA_INICIAL = 600;   // ms después de cargar la página
  var DURACION = 1150;        // ms de un recorrido (cada moto varía un poco)
  var T_COLA = 340;           // ms de vida de la estela
  var VOLUMEN = 0.9;          // 0 = mudo, 1 = normal

  var root = document.getElementById('chatbot');
  var btnViejo = document.getElementById('cb-abrir');
  var btnFab = document.getElementById('fab-toggle');
  if (!root || (!btnViejo && !btnFab)) return;

  var reducido = window.matchMedia && matchMedia('(prefers-reduced-motion: reduce)').matches;

  // La moto: un SVG propio parecido al logo (scooter de frente con espejos, faro dorado y
  // líneas de velocidad). No usa archivos de imagen; se dibuja en el canvas como cabeza del destello.
  var MOTO_SVG = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><mask id="m"><rect width="100" height="100" fill="#fff"/><path d="M50 72c3-8 8-14 14-14 6 0 8 5 5 11" fill="none" stroke="#000" stroke-width="3" stroke-linecap="round"/><path d="M57 63L44 83" fill="none" stroke="#000" stroke-width="3" stroke-linecap="round"/></mask></defs><g stroke="#FFD600" stroke-width="4.4" stroke-linecap="round" fill="none"><path d="M10 42h22"/><path d="M4 54h28"/><path d="M13 66h17"/></g><g transform="rotate(-8 60 55)"><g stroke="#E31E26" stroke-linecap="round" fill="none"><path d="M43 40h34" stroke-width="4"/><path d="M49 40l-3-9" stroke-width="2.6"/><path d="M74 40l4-8" stroke-width="2.6"/></g><circle cx="44.5" cy="26.5" r="6.2" fill="#E31E26"/><circle cx="79.5" cy="28.5" r="6.2" fill="#E31E26"/><path mask="url(#m)" d="M42 52c3-8 29-8 33 0 4 9 2 20-8 28l-8 5-12 1-5-6c-3-10-2-20 0-28Z" fill="#E31E26"/><circle cx="60" cy="39" r="7.2" fill="#FFD600"/><circle cx="60" cy="39" r="4.4" fill="#fff"/><circle cx="60" cy="39" r="2.1" fill="#FFD600"/></g></svg>';
  var moto = new Image();
  moto.src = 'data:image/svg+xml;charset=utf-8,' + encodeURIComponent(MOTO_SVG);

  /* ---------- Helpers ---------- */
  // El botón visible es el flotante único (#fab-toggle); si no está, el "Ayuda" original.
  var btn = btnFab || btnViejo;
  function destino() {
    var f = btnFab && btnFab.getBoundingClientRect();
    if (f && f.width > 0 && f.height > 0) return btnFab;
    return btnViejo || btnFab;
  }
  function visible() {
    btn = destino();
    if (root.classList.contains('cb-abierto')) return false;
    var r = btn.getBoundingClientRect();
    return r.width > 0 && r.height > 0;
  }
  function centro(el) {
    var r = el.getBoundingClientRect();
    return { x: r.left + r.width / 2, y: r.top + r.height / 2 };
  }
  function dist(a, b) { return Math.hypot(a.x - b.x, a.y - b.y); }
  function unit(x, y) { var m = Math.hypot(x, y) || 1; return { x: x / m, y: y / m }; }
  function esc(v, k) { return { x: v.x * k, y: v.y * k }; }
  function suma(a, b) { return { x: a.x + b.x, y: a.y + b.y }; }
  function resta(a, b) { return { x: a.x - b.x, y: a.y - b.y }; }
  function rotar(v, a) { var c = Math.cos(a), s = Math.sin(a); return { x: v.x * c - v.y * s, y: v.x * s + v.y * c }; }
  function bez(S, u) {
    var v = 1 - u;
    return {
      x: v*v*v*S.p0.x + 3*v*v*u*S.c1.x + 3*v*u*u*S.c2.x + u*u*u*S.p1.x,
      y: v*v*v*S.p0.y + 3*v*v*u*S.c1.y + 3*v*u*u*S.c2.y + u*u*u*S.p1.y
    };
  }
  function ease(t) { return t < 0.5 ? 4*t*t*t : 1 - Math.pow(-2*t + 2, 3) / 2; }

  /* ---------- Sonido (Web Audio): vroom al salir + tintineo al llegar ---------- */
  var ac = null;
  var NOTAS = [[988, 1319], [880, 1175], [1047, 1397], [784, 1047], [932, 1245]]; // cada moto suena distinto
  function sonido(dur, vol, parNotas) {
    if (reducido || VOLUMEN <= 0) return;
    try {
      var AC = window.AudioContext || window.webkitAudioContext;
      if (!AC) return;
      if (!ac) ac = (window.ElRapidoSonido && window.ElRapidoSonido.contexto && window.ElRapidoSonido.contexto()) || new AC();   // contexto compartido (ver sonidos.js)
      if (ac.state === 'running') { tocar(dur, vol, parNotas); return; }
      if (ac.state !== 'suspended' || !ac.resume) return;
      // Sin permiso todavía: se intenta una vez y, si no se habilita enseguida, queda en silencio
      var tarde = false;
      ac.resume().then(function () { if (!tarde && ac.state === 'running') tocar(dur, vol, parNotas); });
      setTimeout(function () { tarde = true; }, 150);
    } catch (e) { /* sin audio: no pasa nada */ }
  }
  function tocar(dur, vol, parNotas) {
    var t = ac.currentTime + 0.02, d = dur / 1000;
    var out = ac.createGain();
    out.gain.value = VOLUMEN * vol;
    out.connect(ac.destination);

    // 1) aire: ruido filtrado que sube y baja (el pico coincide con la mayor velocidad)
    var n = Math.floor(ac.sampleRate * (d + 0.2)), buf = ac.createBuffer(1, n, ac.sampleRate), da = buf.getChannelData(0);
    for (var i = 0; i < n; i++) da[i] = Math.random() * 2 - 1;
    var ruido = ac.createBufferSource(); ruido.buffer = buf;
    var bp = ac.createBiquadFilter(); bp.type = 'bandpass'; bp.Q.value = 1.1;
    bp.frequency.setValueAtTime(350, t);
    bp.frequency.exponentialRampToValueAtTime(2600, t + d * 0.5);
    bp.frequency.exponentialRampToValueAtTime(700, t + d);
    var g1 = ac.createGain();
    g1.gain.setValueAtTime(0.0001, t);
    g1.gain.exponentialRampToValueAtTime(0.22, t + d * 0.45);
    g1.gain.exponentialRampToValueAtTime(0.0001, t + d);
    ruido.connect(bp); bp.connect(g1); g1.connect(out);
    ruido.start(t); ruido.stop(t + d + 0.1);

    // 2) motor: diente de sierra grave que acelera y se aleja
    var mo = ac.createOscillator(); mo.type = 'sawtooth';
    mo.frequency.setValueAtTime(65, t);
    mo.frequency.exponentialRampToValueAtTime(230, t + d * 0.5);
    mo.frequency.exponentialRampToValueAtTime(140, t + d);
    var lp = ac.createBiquadFilter(); lp.type = 'lowpass'; lp.frequency.value = 700;
    var g2 = ac.createGain();
    g2.gain.setValueAtTime(0.0001, t);
    g2.gain.exponentialRampToValueAtTime(0.07, t + d * 0.35);
    g2.gain.exponentialRampToValueAtTime(0.0001, t + d);
    mo.connect(lp); lp.connect(g2); g2.connect(out);
    mo.start(t); mo.stop(t + d + 0.1);

    // 3) llegada: tintineo de dos notas (distinto para cada moto)
    [[parNotas[0], 0], [parNotas[1], 0.11]].forEach(function (nota) {
      var o = ac.createOscillator(); o.type = 'sine'; o.frequency.value = nota[0];
      var g = ac.createGain(), t1 = t + d + nota[1];
      g.gain.setValueAtTime(0.0001, t1);
      g.gain.exponentialRampToValueAtTime(0.14, t1 + 0.02);
      g.gain.exponentialRampToValueAtTime(0.0001, t1 + 0.5);
      o.connect(g); g.connect(out);
      o.start(t1); o.stop(t1 + 0.55);
    });
  }

  /* ---------- Motos en vuelo (un solo canvas para todas) ---------- */
  var vuelos = [];
  var esperando = 0;          // motos que esperan a que el botón termine de aparecer
  var cv = null, ctx = null, raf = 0, W = 0, H = 0, usadas = 0;

  function abrirCanvas() {
    if (cv) return true;
    var dpr = Math.min(window.devicePixelRatio || 1, 2);
    W = window.innerWidth; H = window.innerHeight;
    cv = document.createElement('canvas');
    cv.className = 'cb-guia-canvas';
    cv.width = W * dpr; cv.height = H * dpr;
    cv.setAttribute('aria-hidden', 'true');
    ctx = cv.getContext('2d');
    if (!ctx) { cv = null; return false; }
    ctx.scale(dpr, dpr);
    document.body.appendChild(cv);
    return true;
  }
  function cerrarCanvas() {
    cancelAnimationFrame(raf); raf = 0;
    if (cv && cv.parentNode) cv.parentNode.removeChild(cv);
    cv = ctx = null;
  }

  function nuevoVuelo() {
    var logo = document.querySelector('.header-logo img') || document.querySelector('.header-logo');
    var lr = logo && logo.getBoundingClientRect();
    var A = (lr && lr.width > 0) ? centro(logo) : { x: 48, y: 36 };
    var C = centro(btn);

    // Punto al azar: lejos del logo y del botón, dentro de la pantalla
    var min = Math.min(W, H) * 0.25, B = null;
    for (var i = 0; i < 14 && !B; i++) {
      var p = { x: W * (0.15 + Math.random() * 0.7), y: H * (0.25 + Math.random() * 0.5) };
      if (dist(p, A) > min && dist(p, C) > min) B = p;
    }
    if (!B) B = { x: W * (0.3 + Math.random() * 0.4), y: H * (0.4 + Math.random() * 0.2) };

    // Dos tramos suaves con la misma dirección al pasar por B; sale curvando hacia un lado al azar
    var d = unit(C.x - A.x, C.y - A.y);
    var l1 = dist(A, B), l2 = dist(B, C);
    var arr = rotar(unit(B.x - A.x, B.y - A.y), (0.3 + Math.random() * 0.5) * (Math.random() < 0.5 ? -1 : 1));
    var S1 = { p0: A, c1: suma(A, esc(arr, l1 * 0.45)), c2: resta(B, esc(d, l1 * 0.4)), p1: B };
    var S2 = { p0: B, c1: suma(B, esc(d, l2 * 0.4)), c2: resta(C, esc(unit(C.x - B.x, C.y - B.y), l2 * 0.3)), p1: C };
    return {
      S1: S1, S2: S2, corte: l1 / (l1 + l2),
      dur: DURACION * (0.85 + Math.random() * 0.4),
      t0: null, puntos: [], llego: false, finDe: 0
    };
  }

  function punto(v, t) {
    var e = ease(t);
    return e < v.corte ? bez(v.S1, e / v.corte) : bez(v.S2, (e - v.corte) / (1 - v.corte));
  }

  function pintarVuelo(v, ahora, t) {
    // estela fina: amarillo con un borde rojo muy suave
    ctx.lineCap = 'round';
    var P = v.puntos;
    for (var i = 1; i < P.length; i++) {
      var a = P[i - 1], b = P[i];
      var k0 = 1 - (ahora - b.t) / T_COLA;
      if (k0 <= 0) continue;
      ctx.strokeStyle = 'rgba(217,31,38,' + (0.28 * k0) + ')';
      ctx.lineWidth = 7 * k0;
      ctx.beginPath(); ctx.moveTo(a.x, a.y); ctx.lineTo(b.x, b.y); ctx.stroke();
      ctx.strokeStyle = 'rgba(255,214,0,' + (0.9 * k0) + ')';
      ctx.lineWidth = 3.2 * k0;
      ctx.beginPath(); ctx.moveTo(a.x, a.y); ctx.lineTo(b.x, b.y); ctx.stroke();
    }
    if (v.llego) return;
    // cabeza: la moto con un halo dorado; nace en el logo y se apaga al llegar
    var p = P[P.length - 1], q = P[Math.max(0, P.length - 4)];
    var k = Math.min(1, t * 8) * (t > 0.92 ? (1 - t) / 0.08 : 1);
    var tam = (W < 600 ? 42 : 56) * (0.55 + 0.45 * k);
    var r = tam * 0.8;
    var g = ctx.createRadialGradient(p.x, p.y, 0, p.x, p.y, r);
    g.addColorStop(0, 'rgba(255,214,0,' + (0.85 * k) + ')');
    g.addColorStop(0.55, 'rgba(255,214,0,' + (0.3 * k) + ')');
    g.addColorStop(1, 'rgba(255,214,0,0)');
    ctx.fillStyle = g;
    ctx.beginPath(); ctx.arc(p.x, p.y, r, 0, Math.PI * 2); ctx.fill();
    if (moto.complete && moto.naturalWidth) {
      var dx = p.x - q.x, dy = p.y - q.y, m = Math.hypot(dx, dy) || 1;
      var inclina = Math.max(-0.35, Math.min(0.35, (dx / m) * 0.4)); // se inclina hacia donde va
      ctx.save();
      ctx.translate(p.x, p.y);
      ctx.rotate(inclina);
      ctx.globalAlpha = k;
      ctx.shadowColor = 'rgba(0,0,0,0.25)'; ctx.shadowBlur = 6;
      ctx.drawImage(moto, -tam / 2, -tam / 2, tam, tam);
      ctx.restore();
    } else {
      ctx.fillStyle = 'rgba(255,255,255,' + k + ')';
      ctx.beginPath(); ctx.arc(p.x, p.y, 5 * k, 0, Math.PI * 2); ctx.fill();
    }
  }

  function cuadro(ts) {
    raf = 0;
    if (!ctx) return;
    ctx.clearRect(0, 0, W, H);
    for (var i = vuelos.length - 1; i >= 0; i--) {
      var v = vuelos[i];
      if (v.t0 === null) v.t0 = ts;
      var t = Math.min(1, (ts - v.t0) / v.dur);
      var p = punto(v, t);
      v.puntos.push({ x: p.x, y: p.y, t: ts });
      while (v.puntos.length > 1 && ts - v.puntos[0].t > T_COLA) v.puntos.shift();
      if (t >= 1 && !v.llego) { v.llego = true; v.finDe = ts + T_COLA; pulso(); }
      pintarVuelo(v, ts, t);
      if (v.llego && ts > v.finDe) vuelos.splice(i, 1);
    }
    if (vuelos.length) raf = requestAnimationFrame(cuadro);
    else cerrarCanvas();
  }

  /* ---------- Llegada: un pulso suave en el botón ---------- */
  function pulso() {
    if (!visible()) return;
    btn.classList.remove('cb-llegada');
    void btn.offsetWidth; // reinicia la animación si ya estaba
    btn.classList.add('cb-llegada');
    setTimeout(function () { btn.classList.remove('cb-llegada'); }, 1200);
  }

  /* ---------- Lanzar una moto ---------- */
  function lanzar() {
    if (vuelos.length + esperando >= MAX_MOTOS) return false;
    if (!visible()) return false;
    if (reducido) { pulso(); return true; }
    // En celular el botón se esconde al bajar: se muestra primero y se espera a que termine de subir
    var fab = btnFab && btnFab.closest('.fab');
    if (fab && fab.classList.contains('fab--oculto')) {
      fab.classList.remove('fab--oculto');
      esperando++; // reserva el lugar mientras espera
      setTimeout(function () { esperando--; salir(); }, 300);
      return true;
    }
    return salir();
  }
  function salir() {
    if (vuelos.length >= MAX_MOTOS || !visible() || !abrirCanvas()) return false;
    var v = nuevoVuelo();
    vuelos.push(v);
    var n = usadas++ % NOTAS.length;
    sonido(v.dur, 1 / Math.sqrt(Math.max(1, vuelos.length)), NOTAS[n]);
    if (!raf) raf = requestAnimationFrame(cuadro);
    return true;
  }
  /*__EXPORT__*/

  /* ---------- Al tocar el logo: sale una moto en vez de recargar ---------- */
  var logoLink = document.querySelector('.header-logo');
  if (logoLink) {
    logoLink.addEventListener('click', function (e) {
      if (e.defaultPrevented || e.button > 0 || e.ctrlKey || e.metaKey || e.shiftKey || e.altKey) return;
      e.preventDefault(); // ya estamos en el inicio: no hace falta recargar
      lanzar();
    });
  }

  /* ---------- Al cargar la página: sale una moto ---------- */
  function arrancar() { setTimeout(lanzar, ESPERA_INICIAL); }
  if (document.readyState === 'complete') arrancar();
  else addEventListener('load', arrancar);
})();

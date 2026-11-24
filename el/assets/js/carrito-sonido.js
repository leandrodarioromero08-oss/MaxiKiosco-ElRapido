/* carrito-sonido.js · sonido sintetizado (Web Audio, sin archivos) para el carrito del hero de Productos
   + pausa de la animación cuando el hero sale de pantalla.
   El sonido arranca apagado (los navegadores bloquean el audio automático): se activa con el botón del parlante,
   que además reinicia la animación para que audio e imagen salgan sincronizados. Se recuerda la elección. */
(function () {
  'use strict';
  var D = document, W = window, box = D.querySelector('.hero-carro'), btn = D.querySelector('.hc-sonido');
  if (!box) return;
  if ('IntersectionObserver' in W) new IntersectionObserver(function (es) { box.classList.toggle('hc-off', !es[0].isIntersecting); }).observe(box);
  if (!btn || W.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
  var AC = W.AudioContext || W.webkitAudioContext;
  if (!AC) { btn.hidden = true; return; }
  var ctx, master, noise, on = false;
  try { on = localStorage.getItem('elrapido_hc_sonido') === '1'; } catch (e) {}

  function init() {
    if (ctx) return;
    ctx = (W.ElRapidoSonido && W.ElRapidoSonido.contexto && W.ElRapidoSonido.contexto()) || new AC();   /* contexto compartido (ver sonidos.js) */
    var comp = ctx.createDynamicsCompressor(); master = ctx.createGain(); master.gain.value = .5; master.connect(comp); comp.connect(ctx.destination);
    var n = ctx.sampleRate; noise = ctx.createBuffer(1, n, n); var d = noise.getChannelData(0); for (var i = 0; i < n; i++) d[i] = Math.random() * 2 - 1;
  }
  function env(g, t, a, pk, dur) { g.gain.setValueAtTime(.0001, t); g.gain.exponentialRampToValueAtTime(pk, t + a); g.gain.exponentialRampToValueAtTime(.0001, t + dur); }
  function salida(nodo) { var p = ctx.createStereoPanner ? ctx.createStereoPanner() : null; if (p) { nodo.connect(p); p.connect(master); } else nodo.connect(master); return p; }
  function tono(t, tipo, f1, f2, dur, vol) {
    var o = ctx.createOscillator(), g = ctx.createGain(); o.type = tipo;
    o.frequency.setValueAtTime(f1, t); o.frequency.exponentialRampToValueAtTime(f2, t + dur);
    env(g, t, .01, vol, dur); o.connect(g); salida(g); o.start(t); o.stop(t + dur + .05);
  }
  function ruido(t, dur, f1, f2, q, vol, tipo, p1, p2) {
    var s = ctx.createBufferSource(); s.buffer = noise; s.loop = true;
    var f = ctx.createBiquadFilter(); f.type = tipo || 'bandpass'; f.Q.value = q; f.frequency.setValueAtTime(f1, t); f.frequency.exponentialRampToValueAtTime(f2, t + dur);
    var g = ctx.createGain(); env(g, t, Math.min(.06, dur / 3), vol, dur); s.connect(f); f.connect(g);
    var p = salida(g); if (p && p2 !== undefined) { p.pan.setValueAtTime(p1, t); p.pan.linearRampToValueAtTime(p2, t + dur); }
    s.start(t); s.stop(t + dur + .05);
  }
  function plink(t, f) { tono(t, 'triangle', f, f * .995, .35, .18); tono(t, 'sine', f * 2, f * 2, .2, .05); }
  function chime(t) { [880, 1318, 1760].forEach(function (f, i) { tono(t + i * .09, 'sine', f, f, .45, .12); }); }
  function chispas(t) { for (var i = 0; i < 6; i++) { var f = 2200 + Math.random() * 2600; tono(t + i * .07, 'sine', f, f * 1.1, .12, .06); } }
  var NOTAS = [523, 659, 784, 1047];

  /* llegada (tiempos relativos al inicio del viaje del carrito, ver hcLlega en movimiento.css) */
  function llegada() {
    var t = ctx.currentTime + .02;
    ruido(t, .85, 300, 2200, 1.2, .5, 'bandpass', .9, -.3);        /* pasa a toda velocidad, de derecha a izquierda */
    tono(t, 'sawtooth', 70, 160, .85, .08);                        /* motor */
    ruido(t + .78, .34, 2600, 700, 9, .28);                        /* chirrido de la frenada */
    tono(t + .78, 'square', 1900, 800, .32, .025);
    tono(t + 1.08, 'sine', 140, 40, .28, .6); ruido(t + 1.08, .18, 900, 200, .8, .3, 'lowpass');  /* golpe al frenar */
    tono(t + 1.3, 'sine', 110, 45, .2, .3);                        /* rebote al volver */
    NOTAS.forEach(function (f, i) { plink(t + 2.1 + i * .26, f); });  /* productos que caen */
    chime(t + 3.3);                                                /* chapa del rayo */
  }
  /* show de cada 8 s */
  function show() {
    var t = ctx.currentTime + .02;
    tono(t + .28, 'sine', 220, 520, .3, .22);
    chispas(t + .5);
    tono(t + 1.6, 'sine', 130, 45, .22, .4); tono(t + 1.6, 'sine', 330, 200, .25, .12);
    NOTAS.forEach(function (f, i) { plink(t + 1.68 + i * .07, f); });
  }
  function puede() { return on && ctx && ctx.state === 'running' && !D.hidden && !box.classList.contains('hc-off'); }
  function evento(e) {
    var c = e.target.classList; if (!c || !puede()) return;
    if (c.contains('hc-entra') && e.type === 'animationstart') llegada();
    else if (c.contains('hc-salto')) show();
  }
  box.addEventListener('animationstart', evento);
  box.addEventListener('animationiteration', evento);

  function pintar() { btn.setAttribute('aria-pressed', on); btn.setAttribute('aria-label', on ? 'Silenciar el sonido de la animación' : 'Activar el sonido de la animación'); btn.classList.toggle('on', on); }
  pintar();
  btn.addEventListener('click', function () {
    on = !on; try { localStorage.setItem('elrapido_hc_sonido', on ? '1' : '0'); } catch (e) {}
    pintar();
    if (on) { init(); ctx.resume().then(function () { var s = box.querySelector('svg'); if (s) s.replaceWith(s.cloneNode(true)); }); }   /* reinicia la animación: audio e imagen sincronizados */
  });
  if (on) {   /* elección guardada: el navegador pide un gesto para habilitar el audio */
    var ev = ['pointerdown', 'keydown', 'touchend'], g = function () { init(); ctx.resume(); ev.forEach(function (n) { D.removeEventListener(n, g, true); }); };
    ev.forEach(function (n) { D.addEventListener(n, g, true); });
  }
})();

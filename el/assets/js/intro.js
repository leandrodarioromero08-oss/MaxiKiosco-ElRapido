/* intro.js — Intro cinematográfica de El Rápido (solo en index.php)
 * Flujo: pantalla oscura con el logo → clic en el logo (suena y arranca) → logo y explosión →
 * fachada → clic en la puerta → entrada al local → se funde hacia el sitio.
 * - Se muestra UNA vez por sesión del navegador (sessionStorage "elr_intro"). El botón "Ver intro" del footer la repite.
 * - La intro pesada (assets/intro/intro.html) vive en un iframe que se precarga mientras la persona ve el logo.
 * - Arriba a la derecha: silenciar y saltar (Esc también salta).
 * Ver LEEME-INTRO.md. */
(function () {
  'use strict';
  var CLAVE = 'elr_intro';
  var raiz = document.documentElement;
  var gate = document.getElementById('intro-gate');
  if (!gate) return;

  var $ = function (id) { return document.getElementById(id); };
  var btnInicio = $('ig-start'), btnMute = $('ig-mute'), btnSkip = $('ig-skip'), hint = $('ig-hint'), stage = $('ig-stage');
  var fr = null, listo = false, iniciada = false, terminada = false, esperando = false, silencio = false, ctxSonido = null, tFin = 0;
  try { silencio = localStorage.getItem('elrapido_sonidos') === '0'; } catch (e) {}
  pintarMute();

  function pintarMute() {
    btnMute.setAttribute('aria-pressed', silencio ? 'true' : 'false');
    btnMute.setAttribute('aria-label', silencio ? 'Activar el sonido de la intro' : 'Silenciar la intro');
  }
  function api() { try { return fr && fr.contentWindow && fr.contentWindow.ElIntro; } catch (e) { return null; } }

  /* ---- precarga del iframe: cuando el navegador está libre (no compite con la carga de la portada) ---- */
  function cargar() {
    if (fr || terminada) return;
    fr = document.createElement('iframe');
    fr.title = 'Intro animada de El Rápido';
    fr.setAttribute('allow', 'autoplay');
    fr.setAttribute('aria-hidden', 'true');
    fr.tabIndex = -1;
    fr.src = gate.getAttribute('data-intro-src');
    stage.appendChild(fr);
  }
  function programarCarga() {
    var ir = function () { (window.requestIdleCallback || function (f) { setTimeout(f, 250); })(cargar, { timeout: 1500 }); };
    if (document.readyState === 'complete') ir(); else addEventListener('load', ir, { once: true });
  }

  /* ---- sonido de arranque del logo (sintetizado, ~1.3 s, sin archivos) ---- */
  function sonidoArranque() {
    if (silencio) return;
    try {
      var AC = window.AudioContext || window.webkitAudioContext; if (!AC) return;
      ctxSonido = new AC(); var c = ctxSonido, t = c.currentTime + .02;
      if (c.state === 'suspended') c.resume();
      var m = c.createGain(); m.gain.value = .55; m.connect(c.destination);
      // motor que se acelera
      var f = c.createBiquadFilter(); f.type = 'lowpass'; f.Q.value = 3; f.frequency.setValueAtTime(180, t); f.frequency.exponentialRampToValueAtTime(1400, t + .85);
      var g = c.createGain(); g.gain.setValueAtTime(0, t); g.gain.linearRampToValueAtTime(.2, t + .18); g.gain.linearRampToValueAtTime(.26, t + .6); g.gain.exponentialRampToValueAtTime(.001, t + 1.05);
      f.connect(g); g.connect(m);
      [52, 52.7].forEach(function (hz) { var o = c.createOscillator(); o.type = 'sawtooth'; o.frequency.setValueAtTime(hz, t); o.frequency.exponentialRampToValueAtTime(hz * 3.4, t + .85); o.connect(f); o.start(t); o.stop(t + 1.1); });
      // chasquido de encendido + campanita suave
      var o2 = c.createOscillator(), g2 = c.createGain(); o2.type = 'sine'; o2.frequency.setValueAtTime(1318.5, t + .5); g2.gain.setValueAtTime(0, t + .5); g2.gain.linearRampToValueAtTime(.09, t + .51); g2.gain.exponentialRampToValueAtTime(.001, t + 1.3);
      o2.connect(g2); g2.connect(m); o2.start(t + .5); o2.stop(t + 1.35);
      setTimeout(function () { try { if (c.state !== 'closed') c.close().catch(function () {}); } catch (e) {} }, 1800);
    } catch (e) {}
  }

  /* ---- arranque ---- */
  function arrancar() {
    var a = api();
    if (!a) return false;
    iniciada = true; esperando = false;
    try { a.start(silencio); } catch (e) { terminar(true); return true; }
    return true;
  }
  function alTocarLogo() {
    if (iniciada || terminada) return;
    cargar();
    sonidoArranque();
    if (listo) { gate.classList.add('ig-go'); arrancar(); return; }
    esperando = true; gate.classList.add('ig-load'); hint.textContent = 'Cargando…'; // todavía bajando: arranca sola apenas esté lista
  }

  /* ---- fin: el destello se vuelve velo crema y se disuelve sobre el sitio, que "llega" (ver intro.css) ---- */
  function terminar(saltada) {
    if (terminada) return;
    terminada = true;
    try { sessionStorage.setItem(CLAVE, '1'); } catch (e) {}
    var a = api(); if (saltada && a) { try { a.mute(true); } catch (e) {} }
    if (ctxSonido) { try { if (ctxSonido.state !== 'closed') ctxSonido.close().catch(function () {}); } catch (e) {} ctxSonido = null; }
    removeEventListener('keydown', tecla);
    clearTimeout(tFin);
    function salir() {
      raiz.classList.remove('intro-on');            // el sitio queda visible y con scroll debajo del telón
      raiz.classList.add('intro-entra');
      gate.classList.add('ig-out'); if (saltada) gate.classList.add('ig-out-fast');
      try { dispatchEvent(new Event('resize')); } catch (e) {}
      tFin = setTimeout(function () {
        if (fr) { fr.remove(); fr = null; }
        gate.hidden = true; gate.classList.remove('ig-out', 'ig-out-fast', 'ig-go', 'ig-load', 'ig-veil-on');
        raiz.classList.remove('intro-entra');
        var m = $('contenido'); if (m) try { m.focus({ preventScroll: true }); } catch (e) {}
      }, saltada ? 700 : 1750);
    }
    if (saltada) salir();
    else { gate.classList.add('ig-veil-on'); tFin = setTimeout(salir, 380); }
  }
  function tecla(e) { if (e.key === 'Escape') { e.preventDefault(); terminar(true); } }

  /* ---- mensajes del iframe (misma procedencia) ---- */
  addEventListener('message', function (e) {
    if (e.origin !== location.origin || !fr || e.source !== fr.contentWindow || !e.data) return;
    if (e.data.elintro === 'ready') {
      listo = true; gate.classList.remove('ig-load');
      hint.textContent = 'Tocá el logo para comenzar';
      if (esperando) { gate.classList.add('ig-go'); arrancar(); }
    } else if (e.data.elintro === 'fin') {
      terminar(false);
    }
  });

  /* ---- el logo se mueve con el puntero ---- */
  var caja = btnInicio.getBoundingClientRect.bind(btnInicio), pend = 0, px = 0, py = 0;
  function mover() {
    pend = 0;
    var r = caja(), mx = Math.max(-1, Math.min(1, (px - (r.left + r.width / 2)) / (innerWidth / 2))), my = Math.max(-1, Math.min(1, (py - (r.top + r.height / 2)) / (innerHeight / 2)));
    btnInicio.style.setProperty('--mx', mx.toFixed(3)); btnInicio.style.setProperty('--my', my.toFixed(3));
  }
  gate.addEventListener('pointermove', function (e) { if (iniciada) return; px = e.clientX; py = e.clientY; if (!pend) pend = requestAnimationFrame(mover); }, { passive: true });
  gate.addEventListener('pointerleave', function () { btnInicio.style.setProperty('--mx', 0); btnInicio.style.setProperty('--my', 0); });

  btnInicio.addEventListener('click', alTocarLogo);
  btnSkip.addEventListener('click', function () { terminar(true); });
  btnMute.addEventListener('click', function () {
    silencio = !silencio; pintarMute();
    var a = api(); if (a) { try { a.mute(silencio); } catch (e) {} }
  });

  /* ---- mostrar / repetir ---- */
  function mostrar() {
    terminada = false; iniciada = false; esperando = false; listo = false;
    clearTimeout(tFin);
    if (fr) { fr.remove(); fr = null; }
    gate.hidden = false; gate.classList.remove('ig-out', 'ig-out-fast', 'ig-go', 'ig-load', 'ig-veil-on'); raiz.classList.remove('intro-entra');
    gate.classList.add('ig-load'); hint.textContent = 'Preparando la entrada…';
    raiz.classList.add('intro-on');
    scrollTo(0, 0);
    addEventListener('keydown', tecla);
    cargar();
  }

  var forzada = /[?&]intro=1(&|$)/.test(location.search);
  if (forzada && history.replaceState) { try { history.replaceState(null, '', location.pathname + location.hash); } catch (e) {} }

  document.addEventListener('click', function (e) {
    var b = e.target.closest && e.target.closest('[data-intro-ver]');
    if (!b) return;
    e.preventDefault(); mostrar();
  });

  if (raiz.classList.contains('intro-on')) {
    gate.classList.add('ig-load');
    addEventListener('keydown', tecla);
    programarCarga();
  } else {
    gate.hidden = true;
  }
})();

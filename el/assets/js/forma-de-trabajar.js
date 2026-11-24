/* forma-de-trabajar.js
 * Tarjeta "Nuestra forma de trabajar" de Nosotros.
 * Al hacer clic: oculta el texto, carga (una sola vez) el SVG animado y
 * reproduce la secuencia de 6 s con sonido sintetizado. Al terminar,
 * el texto vuelve a aparecer. Sin botones visibles y sin archivos de audio.
 *
 * Respeta prefers-reduced-motion: en ese caso la tarjeta queda como está.
 */

(function () {
  'use strict';

  var RUTA_SVG = 'assets/anim/forma-de-trabajar.svg';
  var DURACION = 6000;       // ciclo completo del SVG
  var VOLUMEN = 0.5;

  var tarjeta = document.getElementById('card-forma-de-trabajar');
  if (!tarjeta) return;

  var escenario = tarjeta.querySelector('.card-escenario');
  var boton = tarjeta.querySelector('.card-play');
  if (!escenario || !boton) return;

  var prefiereMenosMovimiento = window.matchMedia('(prefers-reduced-motion: reduce)');

  if (prefiereMenosMovimiento.matches) {
    boton.hidden = true;
    return;
  }

  tarjeta.classList.add('anim-lista');
  boton.hidden = false;

  var objeto = null;
  var enCurso = false;
  var temporizadorFin = 0;
  var audioCtx = null;
  var master = null;

  /* ── Audio (Web Audio API, sin archivos externos) ───────────────── */

  function crearContexto() {
    var AudioCtx = window.AudioContext || window.webkitAudioContext;
    if (!AudioCtx) return false;

    if (!audioCtx) {
      audioCtx = (window.ElRapidoSonido && window.ElRapidoSonido.contexto && window.ElRapidoSonido.contexto()) || new AudioCtx();   // contexto compartido: Safari limita cuántos se pueden crear
      master = audioCtx.createGain();
      master.gain.value = 0.0001;

      var compresor = audioCtx.createDynamicsCompressor();
      compresor.threshold.value = -20;
      compresor.knee.value = 14;
      compresor.ratio.value = 3;
      compresor.attack.value = 0.003;
      compresor.release.value = 0.25;

      master.connect(compresor);
      compresor.connect(audioCtx.destination);
    }

    if (audioCtx.state === 'suspended') audioCtx.resume();

    var ahora = audioCtx.currentTime;
    master.gain.cancelScheduledValues(ahora);
    master.gain.setValueAtTime(Math.max(0.0001, master.gain.value), ahora);
    master.gain.exponentialRampToValueAtTime(VOLUMEN, ahora + 0.08);
    return true;
  }

  function tono(t, freq, dur, peak, tipo, freqEnd) {
    if (!audioCtx) return;
    var osc = audioCtx.createOscillator();
    var gain = audioCtx.createGain();

    osc.type = tipo || 'sine';
    osc.frequency.setValueAtTime(freq, t);
    if (freqEnd) {
      osc.frequency.exponentialRampToValueAtTime(Math.max(20, freqEnd), t + dur);
    }

    gain.gain.setValueAtTime(0.0001, t);
    gain.gain.exponentialRampToValueAtTime(Math.max(0.0002, peak), t + Math.min(0.015, dur * 0.25));
    gain.gain.exponentialRampToValueAtTime(0.0001, t + dur);

    osc.connect(gain);
    gain.connect(master);
    osc.start(t);
    osc.stop(t + dur + 0.03);
  }

  function ruido(t, dur, peak, inicio, medio, fin, q) {
    if (!audioCtx) return;

    var muestras = Math.max(1, Math.floor(audioCtx.sampleRate * dur));
    var buffer = audioCtx.createBuffer(1, muestras, audioCtx.sampleRate);
    var datos = buffer.getChannelData(0);
    for (var i = 0; i < muestras; i += 1) {
      datos[i] = Math.random() * 2 - 1;
    }

    var fuente = audioCtx.createBufferSource();
    fuente.buffer = buffer;

    var filtro = audioCtx.createBiquadFilter();
    filtro.type = 'bandpass';
    filtro.Q.value = q || 1.15;
    filtro.frequency.setValueAtTime(inicio, t);
    filtro.frequency.exponentialRampToValueAtTime(Math.max(40, medio), t + dur * 0.45);
    filtro.frequency.exponentialRampToValueAtTime(Math.max(40, fin), t + dur);

    var gain = audioCtx.createGain();
    gain.gain.setValueAtTime(0.0001, t);
    gain.gain.exponentialRampToValueAtTime(peak, t + dur * 0.18);
    gain.gain.exponentialRampToValueAtTime(0.0001, t + dur);

    fuente.connect(filtro);
    filtro.connect(gain);
    gain.connect(master);
    fuente.start(t);
    fuente.stop(t + dur + 0.02);
  }

  /* Paso: golpe seco y corto sobre el piso del local. */
  function paso(t) {
    ruido(t, 0.085, 0.055, 620, 380, 190, 0.9);
    tono(t, 128, 0.09, 0.05, 'sine', 62);
  }

  /* Bolsa de papel: roce con un pequeño golpe al apoyarla o tomarla. */
  function bolsa(t, fuerza) {
    var f = fuerza || 1;
    ruido(t, 0.2, 0.042 * f, 2600, 1900, 1200, 0.8);
    ruido(t + 0.06, 0.13, 0.026 * f, 1500, 2400, 900, 0.7);
    tono(t + 0.02, 190, 0.11, 0.045 * f, 'sine', 92);
  }

  /* Confirmación de la entrega: dos notas cálidas. */
  function listo(t) {
    tono(t, 493.88, 0.26, 0.03);
    tono(t, 659.25, 0.3, 0.07, 'triangle');
    tono(t + 0.11, 987.77, 0.42, 0.06, 'triangle');
    tono(t + 0.12, 1318.5, 0.3, 0.018);
  }

  /* Motor de la moto: barrido de izquierda a derecha con putt-putt. */
  function motor(t) {
    if (!audioCtx) return;

    var dur = 2.35;

    var gain = audioCtx.createGain();
    gain.gain.setValueAtTime(0.0001, t);
    gain.gain.exponentialRampToValueAtTime(0.05, t + 0.18);
    gain.gain.setValueAtTime(0.05, t + dur * 0.55);
    gain.gain.exponentialRampToValueAtTime(0.0001, t + dur);

    // Pequeña ondulación: el motor "late" mientras avanza.
    var lfo = audioCtx.createOscillator();
    var lfoGain = audioCtx.createGain();
    lfo.type = 'sine';
    lfo.frequency.setValueAtTime(11, t);
    lfo.frequency.linearRampToValueAtTime(14, t + dur);
    lfoGain.gain.value = 0.016;
    lfo.connect(lfoGain);
    lfoGain.connect(gain.gain);
    lfo.start(t);
    lfo.stop(t + dur + 0.05);

    var filtro = audioCtx.createBiquadFilter();
    filtro.type = 'lowpass';
    filtro.Q.value = 3.2;
    filtro.frequency.setValueAtTime(520, t);
    filtro.frequency.linearRampToValueAtTime(880, t + dur * 0.5);
    filtro.frequency.linearRampToValueAtTime(480, t + dur);

    var osc = audioCtx.createOscillator();
    osc.type = 'sawtooth';
    osc.frequency.setValueAtTime(64, t);
    osc.frequency.linearRampToValueAtTime(96, t + dur * 0.45);
    osc.frequency.linearRampToValueAtTime(70, t + dur);

    var sub = audioCtx.createOscillator();
    sub.type = 'square';
    sub.frequency.setValueAtTime(32, t);
    sub.frequency.linearRampToValueAtTime(48, t + dur * 0.45);
    sub.frequency.linearRampToValueAtTime(35, t + dur);

    var subGain = audioCtx.createGain();
    subGain.gain.value = 0.22;

    osc.connect(filtro);
    sub.connect(subGain);
    subGain.connect(filtro);
    filtro.connect(gain);
    gain.connect(master);

    osc.start(t);
    sub.start(t);
    osc.stop(t + dur + 0.05);
    sub.stop(t + dur + 0.05);

    // Aire desplazado por la moto.
    ruido(t + 0.15, 1.9, 0.02, 420, 700, 260, 0.6);
  }

  function programarSonido() {
    if (!audioCtx) return;
    if (audioCtx.state === 'suspended') audioCtx.resume();

    var base = audioCtx.currentTime + 0.04;

    // Momentos del SVG: pasos al llegar, bolsa sobre el mostrador,
    // entrega, globo "¡Listo!" y salida de la moto.
    var cues = [
      [220, 'paso'],
      [420, 'paso'],
      [620, 'paso'],
      [820, 'paso'],
      [1015, 'paso'],
      [1390, 'bolsa', 1],
      [1980, 'bolsa', 0.8],
      [2530, 'bolsa', 0.9],
      [2830, 'listo'],
      [3470, 'moto']
    ];

    for (var i = 0; i < cues.length; i += 1) {
      var t = base + cues[i][0] / 1000;
      switch (cues[i][1]) {
        case 'paso':
          paso(t);
          break;
        case 'bolsa':
          bolsa(t, cues[i][2]);
          break;
        case 'listo':
          listo(t);
          break;
        case 'moto':
          motor(t);
          break;
        default:
          break;
      }
    }
  }

  /* ── Reproducción del SVG ──────────────────────────────────────── */

  function iniciarAnimacion() {
    if (!objeto) return;

    var doc = objeto.contentDocument;
    if (!doc) {
      finalizar();
      return;
    }

    var animaciones = doc.getAnimations ? doc.getAnimations() : [];
    for (var i = 0; i < animaciones.length; i += 1) {
      try {
        animaciones[i].currentTime = 0;
        animaciones[i].play();
      } catch (error) {
        /* La animación todavía no está disponible. */
      }
    }

    programarSonido();

    window.clearTimeout(temporizadorFin);
    temporizadorFin = window.setTimeout(finalizar, DURACION + 400);
  }

  function cargarObjeto() {
    objeto = document.createElement('object');
    objeto.type = 'image/svg+xml';
    objeto.data = RUTA_SVG;
    objeto.className = 'anim-objeto';
    objeto.setAttribute('aria-hidden', 'true');
    objeto.setAttribute('tabindex', '-1');

    objeto.addEventListener('load', function () {
      var doc = objeto.contentDocument;
      if (!doc || !doc.documentElement) {
        finalizar();
        return;
      }

      // La clase hace que la secuencia se reproduzca una sola vez.
      doc.documentElement.classList.add('una-vez');

      doc.addEventListener('animationend', function (evento) {
        if (evento.animationName === 'escenaUnaVez') finalizar();
      });

      iniciarAnimacion();
    }, { once: true });

    objeto.addEventListener('error', finalizar, { once: true });

    escenario.appendChild(objeto);
  }

  function reproducir() {
    if (enCurso || prefiereMenosMovimiento.matches) return;

    enCurso = true;
    tarjeta.classList.add('reproduciendo');
    boton.blur();

    crearContexto();

    if (objeto) {
      iniciarAnimacion();
    } else {
      cargarObjeto();
      // Red de seguridad por si el SVG no llega a cargar.
      window.clearTimeout(temporizadorFin);
      temporizadorFin = window.setTimeout(finalizar, 5000);
    }
  }

  function finalizar() {
    if (!enCurso) return;

    enCurso = false;
    window.clearTimeout(temporizadorFin);
    tarjeta.classList.remove('reproduciendo');

    if (audioCtx && master) {
      var ahora = audioCtx.currentTime;
      master.gain.cancelScheduledValues(ahora);
      master.gain.setValueAtTime(Math.max(0.0001, master.gain.value), ahora);
      master.gain.exponentialRampToValueAtTime(0.0001, ahora + 0.12);
    }
  }

  boton.addEventListener('click', reproducir);

  if (prefiereMenosMovimiento.addEventListener) {
    prefiereMenosMovimiento.addEventListener('change', function (evento) {
      if (evento.matches) {
        boton.hidden = true;
        tarjeta.classList.remove('anim-lista');
        finalizar();
      } else {
        boton.hidden = false;
        tarjeta.classList.add('anim-lista');
      }
    });
  }
})();

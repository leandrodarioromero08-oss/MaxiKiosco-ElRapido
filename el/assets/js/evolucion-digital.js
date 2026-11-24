/* evolucion-digital.js
 * Tarjeta "Evolución digital" de Nosotros.
 * Al hacer clic: oculta el texto, carga (una sola vez) el SVG animado y
 * reproduce la secuencia de 6 s con sonido sintetizado. Al terminar,
 * el texto vuelve a aparecer. Sin botones visibles y sin archivos de audio.
 *
 * Respeta prefers-reduced-motion: en ese caso la tarjeta queda como está.
 */

(function () {
  'use strict';

  var RUTA_SVG = 'assets/anim/evolucion-digital.svg';
  var DURACION = 6000;       // ciclo completo del SVG
  var VOLUMEN = 0.5;

  var tarjeta = document.getElementById('card-evolucion-digital');
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

  function ruido(t, dur, peak, inicio, medio, fin) {
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
    filtro.Q.value = 1.15;
    filtro.frequency.setValueAtTime(inicio, t);
    filtro.frequency.exponentialRampToValueAtTime(medio, t + dur * 0.45);
    filtro.frequency.exponentialRampToValueAtTime(fin, t + dur);

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

  function notificacion(t, pitch) {
    tono(t, pitch, 0.1, 0.07, 'sine', pitch * 1.45);
    tono(t + 0.006, pitch * 2, 0.045, 0.016, 'triangle');
  }

  function campana(t, freq, peak) {
    tono(t, freq, 0.34, peak, 'sine');
    tono(t + 0.006, freq * 2, 0.2, peak * 0.35, 'sine');
    tono(t + 0.01, freq * 3, 0.11, peak * 0.14, 'sine');
  }

  function chimeExito(t) {
    var notas = [523.25, 659.25, 783.99, 1046.5];
    for (var i = 0; i < notas.length; i += 1) {
      var ti = t + i * 0.1;
      tono(ti, notas[i], 0.48 - i * 0.04, 0.075, 'triangle');
      tono(ti, notas[i] * 2, 0.26, 0.018, 'sine');
    }
    tono(t + 0.42, 2093, 0.32, 0.022, 'sine');
  }

  function programarSonido() {
    if (!audioCtx) return;
    if (audioCtx.state === 'suspended') audioCtx.resume();

    var base = audioCtx.currentTime + 0.04;

    var cues = [
      [390, 'noti', 880],
      [708, 'noti', 988],
      [1038, 'noti', 1046],
      [1368, 'noti', 1175],
      [1860, 'barrido'],
      [3624, 'click'],
      [4164, 'carrito'],
      [4590, 'panel'],
      [4992, 'exito']
    ];

    for (var i = 0; i < cues.length; i += 1) {
      var t = base + cues[i][0] / 1000;
      switch (cues[i][1]) {
        case 'noti':
          notificacion(t, cues[i][2]);
          break;
        case 'barrido':
          ruido(t, 0.62, 0.052, 280, 1500, 420);
          break;
        case 'click':
          tono(t, 620, 0.06, 0.1, 'triangle', 320);
          tono(t + 0.005, 1600, 0.02, 0.016, 'square');
          break;
        case 'carrito':
          campana(t, 1046.5, 0.06);
          break;
        case 'panel':
          ruido(t, 0.42, 0.04, 220, 820, 280);
          break;
        case 'exito':
          chimeExito(t);
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
        if (evento.animationName === 'telefonoEntra') finalizar();
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

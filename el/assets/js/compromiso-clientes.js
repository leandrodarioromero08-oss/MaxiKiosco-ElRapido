/* compromiso-clientes.js
 * Tarjeta "Compromiso con nuestros clientes" de Nosotros.
 * Al hacer clic: oculta el texto, carga (una sola vez) el SVG animado y
 * reproduce la secuencia de 6 s con sonido sintetizado. Al terminar,
 * el texto vuelve a aparecer. Sin botones visibles y sin archivos de audio.
 *
 * Respeta prefers-reduced-motion: en ese caso la tarjeta queda como está.
 */

(function () {
  'use strict';

  var RUTA_SVG = 'assets/anim/compromiso-clientes.svg';
  var DURACION = 6000;       // ciclo completo del SVG
  var VOLUMEN = 0.5;

  var tarjeta = document.getElementById('card-compromiso-clientes');
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

  /* Consulta del cliente: dos notas suaves que suben como una pregunta. */
  function consulta(t) {
    tono(t, 622, 0.13, 0.036, 'triangle');
    tono(t + 0.09, 740, 0.2, 0.03, 'triangle');
    ruido(t, 0.05, 0.008, 2200, 2600, 1800, 1.4);
  }

  /* Respuesta del empleado: tres notas amables y ascendentes. */
  function respuesta(t) {
    tono(t, 784, 0.16, 0.04, 'triangle');
    tono(t + 0.075, 988, 0.2, 0.042, 'triangle');
    tono(t + 0.16, 1175, 0.26, 0.02);
  }

  /* El celular se enciende: dos notas cristalinas. */
  function pantalla(t) {
    tono(t, 1046.5, 0.18, 0.028);
    tono(t + 0.06, 1568, 0.24, 0.018);
    ruido(t + 0.02, 0.07, 0.01, 3200, 3800, 2400, 1.2);
  }

  /* La tarjeta de pedido aparece: pop corto. */
  function pop(t) {
    tono(t, 520, 0.1, 0.04, 'sine', 960);
    ruido(t, 0.08, 0.02, 1600, 2600, 1400, 0.9);
  }

  /* Pedido confirmado: dos notas claras. */
  function confirmado(t) {
    tono(t, 880, 0.1, 0.03);
    tono(t + 0.055, 1320, 0.16, 0.022);
  }

  /* Bolsa de papel: roce con un pequeño golpe al tomarla o entregarla. */
  function bolsa(t, fuerza) {
    var f = fuerza || 1;
    ruido(t, 0.2, 0.038 * f, 2600, 1900, 1200, 0.8);
    ruido(t + 0.06, 0.13, 0.022 * f, 1500, 2400, 900, 0.7);
    tono(t + 0.02, 190, 0.11, 0.04 * f, 'sine', 92);
  }

  /* Corazón con tilde: acorde cálido y luminoso. */
  function corazon(t) {
    tono(t, 523.25, 0.34, 0.045, 'triangle');
    tono(t + 0.06, 659.25, 0.4, 0.05, 'triangle');
    tono(t + 0.12, 783.99, 0.48, 0.05, 'triangle');
    tono(t + 0.16, 1046.5, 0.5, 0.022);
  }

  /* Destellos: campanitas altas y breves. */
  function destello(t, fuerza) {
    var f = fuerza || 1;
    tono(t, 1568, 0.22, 0.016 * f);
    tono(t + 0.05, 2093, 0.3, 0.012 * f);
    ruido(t, 0.08, 0.006 * f, 4200, 4600, 3200, 1.3);
  }

  function programarSonido() {
    if (!audioCtx) return;
    if (audioCtx.state === 'suspended') audioCtx.resume();

    var base = audioCtx.currentTime + 0.04;

    // Momentos del SVG: consulta y respuesta en el mostrador, celular y
    // tarjeta online, entrega de la bolsa, corazón y destellos finales.
    var cues = [
      [240, 'consulta'],
      [960, 'respuesta'],
      [2160, 'pantalla'],
      [2340, 'pop'],
      [3420, 'confirmado'],
      [4200, 'bolsa', 0.8],
      [4560, 'bolsa', 0.55],
      [4740, 'bolsa', 1],
      [5040, 'corazon'],
      [5160, 'destello', 1],
      [5400, 'destello', 0.6]
    ];

    for (var i = 0; i < cues.length; i += 1) {
      var t = base + cues[i][0] / 1000;
      switch (cues[i][1]) {
        case 'consulta':
          consulta(t);
          break;
        case 'respuesta':
          respuesta(t);
          break;
        case 'pantalla':
          pantalla(t);
          break;
        case 'pop':
          pop(t);
          break;
        case 'confirmado':
          confirmado(t);
          break;
        case 'bolsa':
          bolsa(t, cues[i][2]);
          break;
        case 'corazon':
          corazon(t);
          break;
        case 'destello':
          destello(t, cues[i][2]);
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

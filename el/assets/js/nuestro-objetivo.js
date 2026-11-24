/* nuestro-objetivo.js
 * Tarjeta "Nuestro objetivo" de Nosotros.
 * Al hacer clic: oculta el texto, carga (una sola vez) el SVG animado y
 * reproduce la secuencia de 6 s con sonido sintetizado. Al terminar,
 * el texto vuelve a aparecer. Sin botones visibles y sin archivos de audio.
 *
 * Respeta prefers-reduced-motion: en ese caso la tarjeta queda como está.
 */

(function () {
  'use strict';

  var RUTA_SVG = 'assets/anim/nuestro-objetivo.svg';
  var DURACION = 6000;       // ciclo completo del SVG
  var VOLUMEN = 0.5;

  var tarjeta = document.getElementById('card-nuestro-objetivo');
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

  /* Ficha de pedido: toque seco de papel sobre la lista. */
  function ficha(t, n) {
    var base = 430 + n * 42;
    ruido(t, 0.075, 0.032, 2400 + n * 140, 1700, 1100, 1.1);
    tono(t, base, 0.09, 0.032, 'triangle', base * 0.68);
  }

  /* Pedido aprobado: dos notas cortas y claras. */
  function check(t) {
    tono(t, 880, 0.1, 0.028, 'sine');
    tono(t + 0.055, 1320, 0.15, 0.02, 'sine');
  }

  /* Lista completa: acorde cálido ascendente. */
  function ordenado(t) {
    tono(t, 523.25, 0.22, 0.035, 'triangle');
    tono(t + 0.07, 659.25, 0.28, 0.045, 'triangle');
    tono(t + 0.14, 783.99, 0.36, 0.05, 'triangle');
    tono(t + 0.15, 1046.5, 0.3, 0.018);
  }

  /* Reloj: tic-tac que acelera con el giro y frena con las agujas. */
  function tictac(t) {
    var marcas = [0, 0.12, 0.24, 0.36, 0.48, 0.60, 0.72, 0.84, 0.96, 1.08, 1.19, 1.29, 1.38, 1.46, 1.53, 1.60, 1.72];
    for (var i = 0; i < marcas.length; i += 1) {
      var when = marcas[i];
      var agudo = i % 2 === 0;
      ruido(t + when, 0.022, 0.011, agudo ? 2600 : 2000, 2200, 1600, 1.6);
      tono(t + when, agudo ? 2100 : 1560, 0.026, 0.008, 'square', agudo ? 1500 : 1150);
    }
  }

  /* Chip "MENOS ESPERA": pequeño pop. */
  function chip(t) {
    tono(t, 520, 0.1, 0.04, 'sine', 960);
    ruido(t, 0.08, 0.02, 1600, 2600, 1400, 0.9);
  }

  /* Las agujas se detienen: campanita suave. */
  function campana(t) {
    tono(t, 1046.5, 0.5, 0.045, 'triangle');
    tono(t + 0.02, 1568, 0.6, 0.028);
    tono(t + 0.04, 2093, 0.4, 0.012);
  }

  /* Bolsa de papel: roce con un pequeño golpe al tomarla o entregarla. */
  function bolsa(t, fuerza) {
    var f = fuerza || 1;
    ruido(t, 0.2, 0.04 * f, 2600, 1900, 1200, 0.8);
    ruido(t + 0.06, 0.13, 0.024 * f, 1500, 2400, 900, 0.7);
    tono(t + 0.02, 190, 0.11, 0.042 * f, 'sine', 92);
  }

  /* "¡Gracias!": tres notas amables. */
  function gracias(t) {
    tono(t, 659.25, 0.26, 0.05, 'triangle');
    tono(t + 0.1, 880, 0.3, 0.055, 'triangle');
    tono(t + 0.21, 1318.5, 0.42, 0.025);
    tono(t + 0.02, 493.88, 0.24, 0.025);
  }

  /* Sello "ENTREGADO": golpe bajo y corto. */
  function sello(t) {
    ruido(t, 0.11, 0.05, 900, 520, 220, 0.8);
    tono(t, 150, 0.14, 0.06, 'sine', 70);
    tono(t + 0.03, 660, 0.12, 0.02, 'triangle');
  }

  function programarSonido() {
    if (!audioCtx) return;
    if (audioCtx.state === 'suspended') audioCtx.resume();

    var base = audioCtx.currentTime + 0.04;

    // Momentos del SVG: pedidos que se acomodan, reloj que gira y
    // se detiene, entrega de la bolsa, globo y etiqueta "ENTREGADO".
    var cues = [
      [690, 'ficha', 1],
      [870, 'ficha', 2],
      [1020, 'ficha', 3],
      [1170, 'ficha', 4],
      [1380, 'check'],
      [1560, 'ordenado'],
      [1980, 'tictac'],
      [3360, 'chip'],
      [3540, 'campana'],
      [4050, 'bolsa', 0.9],
      [4770, 'bolsa', 1],
      [5010, 'gracias'],
      [5250, 'sello']
    ];

    for (var i = 0; i < cues.length; i += 1) {
      var t = base + cues[i][0] / 1000;
      switch (cues[i][1]) {
        case 'ficha':
          ficha(t, cues[i][2]);
          break;
        case 'check':
          check(t);
          break;
        case 'ordenado':
          ordenado(t);
          break;
        case 'tictac':
          tictac(t);
          break;
        case 'chip':
          chip(t);
          break;
        case 'campana':
          campana(t);
          break;
        case 'bolsa':
          bolsa(t, cues[i][2]);
          break;
        case 'gracias':
          gracias(t);
          break;
        case 'sello':
          sello(t);
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

/* chatbot.js — Asistente de El Rápido
 * Maneja la ventana del chat: abrir/cerrar, enviar mensajes a
 * chatbot.php y pintar las respuestas. La conversación se guarda
 * en sessionStorage para que no se pierda al cambiar de página
 * (se borra sola cuando se cierra la pestaña).
 */
(function () {
  'use strict';

  var root = document.getElementById('chatbot');
  if (!root) return;

  var btnAbrir  = document.getElementById('cb-abrir');
  var btnCerrar = document.getElementById('cb-cerrar');
  var panel     = document.getElementById('cb-panel');
  var log       = document.getElementById('cb-log');
  var chips     = document.getElementById('cb-chips');
  var form      = document.getElementById('cb-form');
  var input     = document.getElementById('cb-input');
  var btnEnviar = document.getElementById('cb-enviar');

  var CLAVE = 'elrapido_chat_v1';
  var MAX_HISTORIAL = 30;
  var SUGERENCIAS_INICIALES = ['Promos de hoy', '¿Están abiertos?', '¿Hacen delivery?', 'Medios de pago', 'Mi pedido'];

  var historial = [];   // [{ r: 'yo', t: 'texto' } | { r: 'bot', d: respuesta }]
  var abierto = false;
  var pendiente = false;

  /* ---------- Guardado ---------- */
  function leer() {
    try {
      var d = JSON.parse(sessionStorage.getItem(CLAVE) || 'null');
      if (d && Array.isArray(d.h)) {
        historial = d.h;
        abierto = !!d.a;
      }
    } catch (e) { /* sin storage: el chat funciona igual, solo no recuerda */ }
  }

  function guardar() {
    try {
      sessionStorage.setItem(CLAVE, JSON.stringify({ h: historial.slice(-MAX_HISTORIAL), a: abierto }));
    } catch (e) { /* idem */ }
  }

  /* ---------- Helpers de DOM ---------- */
  function el(tag, clase, texto) {
    var e = document.createElement(tag);
    if (clase) e.className = clase;
    if (texto !== undefined && texto !== null) e.textContent = texto;
    return e;
  }

  function bajar() { log.scrollTop = log.scrollHeight; }

  // Solo aceptamos links del sitio (algo.php, assets/...) o http(s)
  function urlSegura(u) {
    return typeof u === 'string' && /^(https?:\/\/|[a-z0-9_\-]+\.php|assets\/)/i.test(u);
  }

  function pintarUsuario(texto) {
    log.appendChild(el('div', 'cb-msg cb-yo', texto));
    bajar();
  }

  function pintarBot(d) {
    var bloque = el('div', 'cb-bloque');
    bloque.appendChild(el('div', 'cb-msg cb-bot', d.texto || ''));

    if (Array.isArray(d.tarjetas) && d.tarjetas.length) {
      var lista = el('ul', 'cb-tarjetas');
      d.tarjetas.forEach(function (t) {
        var li = el('li', 'cb-tarjeta');
        var izq = el('div', 'cb-t-izq');
        izq.appendChild(el('span', 'cb-t-titulo', t.titulo || ''));
        if (t.detalle) izq.appendChild(el('span', 'cb-t-detalle', t.detalle));
        li.appendChild(izq);
        if (t.precio) li.appendChild(el('span', 'cb-t-precio', t.precio));
        lista.appendChild(li);
      });
      bloque.appendChild(lista);
    }

    if (Array.isArray(d.enlaces) && d.enlaces.length) {
      var fila = el('div', 'cb-enlaces');
      d.enlaces.forEach(function (e) {
        if (!urlSegura(e.url)) return;
        var a = el('a', 'cb-enlace' + (e.tipo === 'wsp' ? ' cb-enlace-wsp' : ''), e.texto || 'Abrir');
        a.href = e.url;
        if (/^https?:/i.test(e.url)) {
          a.target = '_blank';
          a.rel = 'noopener';
        }
        fila.appendChild(a);
      });
      bloque.appendChild(fila);
    }

    log.appendChild(bloque);
    bajar();
  }

  function pintarChips(lista) {
    chips.textContent = '';
    (lista || []).forEach(function (texto) {
      var b = el('button', 'cb-chip', texto);
      b.type = 'button';
      b.addEventListener('click', function () { enviar(texto); });
      chips.appendChild(b);
    });
  }

  function escribiendo() {
    var e = el('div', 'cb-msg cb-bot cb-escribiendo');
    e.setAttribute('aria-label', 'Escribiendo');
    e.appendChild(el('i'));
    e.appendChild(el('i'));
    e.appendChild(el('i'));
    log.appendChild(e);
    bajar();
    return e;
  }

  /* ---------- Abrir / cerrar ---------- */
  function abrir(conFoco) {
    panel.hidden = false;
    root.classList.add('cb-abierto');
    btnAbrir.setAttribute('aria-expanded', 'true');
    document.documentElement.classList.add('cb-bloqueado');
    abierto = true;
    guardar();
    bajar();
    if (conFoco) setTimeout(function () { input.focus(); }, 60);
  }

  function cerrar() {
    panel.hidden = true;
    root.classList.remove('cb-abierto');
    btnAbrir.setAttribute('aria-expanded', 'false');
    document.documentElement.classList.remove('cb-bloqueado');
    abierto = false;
    guardar();
    btnAbrir.focus();
  }

  /* ---------- Envío ---------- */
  function responder(d) {
    historial.push({ r: 'bot', d: d });
    pintarBot(d);
    pintarChips(d.sugerencias);
    guardar();
  }

  function respuestaError() {
    return {
      texto: 'No pude responderte en este momento. Probá de nuevo o escribinos por WhatsApp.',
      enlaces: [{ texto: 'Hablar por WhatsApp', url: root.getAttribute('data-wsp') || '', tipo: 'wsp' }],
      sugerencias: [],
      tarjetas: []
    };
  }

  function enviar(texto) {
    texto = (texto || '').trim();
    if (!texto || pendiente) return;

    pendiente = true;
    btnEnviar.disabled = true;
    input.value = '';
    chips.textContent = '';
    historial.push({ r: 'yo', t: texto });
    pintarUsuario(texto);
    guardar();

    var espera = escribiendo();
    var ctrl = ('AbortController' in window) ? new AbortController() : null;
    var corte = setTimeout(function () { if (ctrl) ctrl.abort(); }, 12000);

    fetch('chatbot.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: 'mensaje=' + encodeURIComponent(texto),
      credentials: 'same-origin',
      signal: ctrl ? ctrl.signal : undefined
    })
      .then(function (r) {
        if (!r.ok) throw new Error('http ' + r.status);
        return r.json();
      })
      .catch(function () { return respuestaError(); })
      .then(function (d) {
        clearTimeout(corte);
        espera.remove();
        pendiente = false;
        btnEnviar.disabled = false;
        responder(d);
        if (abierto) input.focus();
      });
  }

  /* ---------- Inicio ---------- */
  function saludoInicial() {
    var nombre = root.getAttribute('data-nombre');
    return {
      texto: '¡Hola' + (nombre ? ', ' + nombre : '') + '! Soy el asistente de El Rápido. Te ayudo con horarios, delivery, promos, precios y el estado de tu pedido.',
      enlaces: [],
      tarjetas: [],
      sugerencias: SUGERENCIAS_INICIALES
    };
  }

  function iniciar() {
    leer();
    if (!historial.length) {
      historial.push({ r: 'bot', d: saludoInicial() });
    }

    var ultimoBot = null;
    historial.forEach(function (item) {
      if (item.r === 'yo') {
        pintarUsuario(item.t);
      } else if (item.d) {
        pintarBot(item.d);
        ultimoBot = item.d;
      }
    });
    // Solo dejamos las sugerencias si el último mensaje es del bot
    var ultimo = historial[historial.length - 1];
    pintarChips(ultimo && ultimo.r === 'bot' && ultimoBot ? ultimoBot.sugerencias : []);

    if (abierto) abrir(false);

    btnAbrir.addEventListener('click', function () { abrir(true); });
    btnCerrar.addEventListener('click', cerrar);
    form.addEventListener('submit', function (ev) {
      ev.preventDefault();
      enviar(input.value);
    });
    document.addEventListener('keydown', function (ev) {
      if (ev.key === 'Escape' && abierto) cerrar();
    });
  }

  iniciar();
})();

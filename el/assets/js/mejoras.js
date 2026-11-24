/* ============================================================
   MEJORAS.JS — El Rápido Maxi Kiosco
   Capa de mejoras de interacción. Va DESPUÉS de global.js / reveal.js / productos.js.
   Mejora progresiva: si este archivo (o GSAP) no carga, el sitio sigue funcionando como antes.
   Bloques:
     1. Utilidades            4. Toast
     2. API del carrito       5. Mini-carrito (drawer) y barra móvil
     3. Tarjetas (stepper)    6. Botón flotante único     7. GSAP (M01–M10)   8. Mapa
   ============================================================ */
(function () {
  'use strict';
  var D = document, W = window;
  var HAY_GSAP = typeof W.gsap !== 'undefined';
  var $ = function (s, r) { return (r || D).querySelector(s); };
  var $$ = function (s, r) { return Array.prototype.slice.call((r || D).querySelectorAll(s)); };

  /* ---------- 1. Utilidades ---------- */
  function esc(t) { return String(t == null ? '' : t).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }
  function fmt(n) {
    var d = Math.abs(n - Math.round(n)) < 0.005 ? 0 : 2;
    return '$' + Number(n).toLocaleString('es-AR', { minimumFractionDigits: d, maximumFractionDigits: d });
  }
  function reducirMovimiento() { return W.matchMedia && W.matchMedia('(prefers-reduced-motion: reduce)').matches; }
  function plural(n, s, p) { return n + ' ' + (n === 1 ? s : p); }
  var ICO = {
    check: '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" class="icono-svg" aria-hidden="true"><path d="M20 6 9 17l-5-5"/></svg>',
    alerta: '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" class="icono-svg" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 8v5M12 16h.01"/></svg>',
    bolsa: '<svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="icono-svg" aria-hidden="true"><path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4Z"/><path d="M3 6h18M16 10a4 4 0 0 1-8 0"/></svg>',
    flecha: '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icono-svg" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg>'
  };

  /* ---------- 2. API del carrito (carrito_api.php) ----------
     Un único punto de contacto con el servidor. Respuesta esperada:
     { ok, items:[{id,nombre,emoji,imagen,precio,cantidad,subtotal,stock}], count, total, mensaje }  */
  var estado = { count: 0, total: 0, items: [] };
  var API = 'carrito_api.php';

  function llamar(accion, datos) {
    var fd = new FormData();
    fd.append('accion', accion);
    fd.append('csrf_token', W.CSRF_TOKEN || '');
    Object.keys(datos || {}).forEach(function (k) { fd.append(k, datos[k]); });
    return fetch(API, { method: 'POST', body: fd, credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
      .then(function (r) {
        return r.json().catch(function () { return {}; }).then(function (j) {
          if (r.status === 401 && j.login) { W.location.href = 'login.php?volver=' + encodeURIComponent((W.location.pathname.split('/').pop() || 'index.php') + W.location.search); throw new Error('login'); }
          if (!r.ok || !j.ok) { var e = new Error(j.error || 'No pudimos actualizar el carrito. Intentá de nuevo.'); if (j.items) e.estado = j; throw e; }  // 409: el servidor ajustó el carrito (stock)
          return j;
        });
      });
  }

  /* Refleja el estado del carrito en TODA la interfaz: header, tarjetas, barra móvil y mini-carrito. */
  function sincronizar(j, anuncio) {
    var antes = estado.count;
    estado = { count: j.count, total: j.total, items: j.items || [] };

    var badge = $('#carrito-badge'), tot = $('#carrito-total'), btn = $('#btn-carrito');
    if (badge) {
      badge.textContent = estado.count;
      badge.classList.toggle('carrito-badge--vacio', estado.count === 0);
      if (estado.count !== antes && estado.count > 0 && !reducirMovimiento()) {
        badge.classList.remove('is-bump'); void badge.offsetWidth; badge.classList.add('is-bump');
      }
    }
    if (tot) tot.textContent = fmt(estado.total);
    if (btn) btn.setAttribute('aria-label', 'Ver carrito (' + plural(estado.count, 'producto', 'productos') + ')');

    var cant = {}; estado.items.forEach(function (i) { if (!i.promo) cant[i.id] = i; });   // las promos tienen ids propios
    $$('.product-card[data-producto-id]').forEach(function (card) {
      var f = $('.card-actions--ajax', card); if (!f) return;
      var it = cant[card.dataset.productoId], q = it ? it.cantidad : 0, stock = parseInt(f.dataset.stock || card.dataset.stock || '0', 10);
      var estabaEn = f.classList.contains('en-carrito');
      f.classList.toggle('en-carrito', q > 0);
      var num = $('.qty-num', f); if (num) num.textContent = q || 1;
      var mas = $('.qty-mas', f); if (mas) mas.disabled = !!stock && q >= stock;
      f.removeAttribute('aria-busy');
      if (q > 0 && !estabaEn) pop($('.qty-control', f));
    });

    var bar = $('#cart-bar');
    if (bar) {
      var n = $('#cart-bar-n'), t = $('#cart-bar-total');
      if (n) n.textContent = estado.count; if (t) t.textContent = fmt(estado.total);
      D.body.classList.toggle('has-cartbar', estado.count > 0 && !!$('.products-area'));
    }
    if (drawerAbierto()) pintarDrawer();

    var st = $('#carrito-status');
    if (st && anuncio) { st.textContent = ''; setTimeout(function () { st.textContent = anuncio + ' Carrito: ' + plural(estado.count, 'producto', 'productos') + ', ' + fmt(estado.total) + '.'; }, 50); }
  }

  function pop(el) {
    if (!el || !HAY_GSAP || reducirMovimiento()) return;
    W.gsap.fromTo(el, { scale: .9 }, { scale: 1, duration: .35, ease: 'back.out(2.2)', clearProps: 'transform' });
  }

  /* ---------- 3. Tarjetas: "Agregar" agrega, sin recargar la página ---------- */
  function nombreDe(card) { var n = $('.product-name', card); return n ? n.textContent.trim() : 'Producto'; }

  D.addEventListener('submit', function (e) {
    var f = e.target.closest ? e.target.closest('.card-actions--ajax') : null;
    if (!f) return;
    e.preventDefault();
    if (f.getAttribute('aria-busy') === 'true') return;
    var card = f.closest('.product-card'), id = card.dataset.productoId, nombre = nombreDe(card);
    f.setAttribute('aria-busy', 'true');
    llamar('agregar', { producto_id: id, cantidad: 1 }).then(function (j) {
      sincronizar(j, nombre + ' agregado.');
      toast(nombre + ' agregado', { accion: 'Ver carrito', alAccion: abrirDrawer });
    }).catch(function (err) {
      f.removeAttribute('aria-busy');
      if (err.estado) sincronizar(err.estado);
      if (err.message !== 'login') toast(err.message, { error: true });
    });
  });

  D.addEventListener('click', function (e) {
    var b = e.target.closest ? e.target.closest('.card-actions--ajax .qty-btn') : null;
    if (!b || b.disabled) return;
    var f = b.closest('.card-actions--ajax'), card = f.closest('.product-card');
    if (f.getAttribute('aria-busy') === 'true') return;
    var actual = parseInt($('.qty-num', f).textContent, 10) || 1;
    var nueva = b.classList.contains('qty-mas') ? actual + 1 : actual - 1;
    var nombre = nombreDe(card);
    f.setAttribute('aria-busy', 'true');
    llamar('cambiar', { producto_id: card.dataset.productoId, cantidad: nueva }).then(function (j) {
      sincronizar(j, nueva > 0 ? nombre + ': ' + plural(nueva, 'unidad', 'unidades') + '.' : nombre + ' quitado.');
      if (nueva === 0) { var ag = $('.btn-agregar', f); if (ag) ag.focus(); }   // el foco no se pierde al desaparecer el stepper
    }).catch(function (err) { f.removeAttribute('aria-busy'); if (err.estado) sincronizar(err.estado); if (err.message !== 'login') toast(err.message, { error: true }); });
  });

  /* ---------- 4. Toast ---------- */
  var toastTimer = null;
  function toast(msg, o) {
    o = o || {};
    if (o.error && W.ElRapidoSonido) W.ElRapidoSonido.play('error');
    var reg = $('#toast-region'); if (!reg) return;
    reg.innerHTML = '';
    var el = D.createElement('div');
    el.className = 'toast' + (o.error ? ' toast--error' : '');
    el.innerHTML = (o.error ? ICO.alerta : ICO.check) + '<span class="toast-txt">' + esc(msg) + '</span>' +
      (o.accion ? '<button type="button" class="toast-accion">' + esc(o.accion) + '</button>' : '');
    reg.appendChild(el);
    var b = $('.toast-accion', el);
    if (b) b.addEventListener('click', function () { cerrar(); if (o.alAccion) o.alAccion(); });
    if (HAY_GSAP && !reducirMovimiento()) W.gsap.fromTo(el, { y: 24, opacity: 0, scale: .96 }, { y: 0, opacity: 1, scale: 1, duration: .35, ease: 'power3.out' });
    function cerrar() {
      clearTimeout(toastTimer);
      if (!el.parentNode) return;
      if (HAY_GSAP && !reducirMovimiento()) W.gsap.to(el, { y: 12, opacity: 0, duration: .25, ease: 'power2.in', onComplete: function () { el.remove(); } });
      else el.remove();
    }
    clearTimeout(toastTimer);
    toastTimer = setTimeout(cerrar, o.error ? 7000 : 5000);     // WCAG 2.2.1: tiempo suficiente; se frena con mouse/foco
    el.addEventListener('mouseenter', function () { clearTimeout(toastTimer); });
    el.addEventListener('mouseleave', function () { toastTimer = setTimeout(cerrar, 2500); });
    el.addEventListener('focusin', function () { clearTimeout(toastTimer); });
  }

  /* ---------- 5. Mini-carrito (drawer) ---------- */
  var drawer = $('#drawer'), overlay = $('#drawer-overlay'), opener = null;
  function drawerAbierto() { return !!drawer && drawer.classList.contains('is-open'); }

  function pintarDrawer() {
    var body = $('#drawer-body'), foot = $('#drawer-foot'), cnt = $('#drawer-count');
    if (!body) return;
    if (cnt) cnt.textContent = estado.count ? '(' + plural(estado.count, 'producto', 'productos') + ')' : '';
    if (!estado.items.length) {
      body.innerHTML = '<div class="drawer-vacio">' + ICO.bolsa + '<strong>Tu carrito está vacío</strong>Sumá productos del catálogo y los ves acá.</div>';
      foot.innerHTML = '<a href="productos.php" class="drawer-cta">Ver productos ' + ICO.flecha + '</a>';
      return;
    }
    body.innerHTML = estado.items.map(function (i) {
      var img = i.imagen ? '<img src="' + esc(i.imagen) + '" alt="" width="56" height="56" style="object-fit:contain">' : esc(i.emoji || '🛒');
      var stockMax = i.stock && i.cantidad >= i.stock;
      return '<div class="drawer-item" data-id="' + esc(i.id) + '">' +
        '<div class="drawer-item-img" aria-hidden="true">' + img + '</div>' +
        '<div><div class="drawer-item-nombre">' + esc(i.nombre) + '</div><div class="drawer-item-precio">' + fmt(i.precio) + ' c/u</div></div>' +
        '<div class="drawer-item-subtotal">' + fmt(i.subtotal) + '</div>' +
        (i.promo ? '<div class="drawer-item-ctrl"><span class="drawer-item-precio">' + plural(i.cantidad, 'promo', 'promos') + '</span><a class="drawer-quitar" href="carrito.php">Editar en el carrito</a></div></div>' :
        '<div class="drawer-item-ctrl"><div class="qty-control" role="group" aria-label="Cantidad de ' + esc(i.nombre) + '">' +
        '<button type="button" class="qty-btn" data-delta="-1" aria-label="Quitar una unidad de ' + esc(i.nombre) + '">−</button>' +
        '<span class="qty-num" aria-live="polite">' + i.cantidad + '</span>' +
        '<button type="button" class="qty-btn" data-delta="1" aria-label="Sumar una unidad de ' + esc(i.nombre) + '"' + (stockMax ? ' disabled' : '') + '>+</button></div>' +
        '<button type="button" class="drawer-quitar" data-quitar aria-label="Quitar ' + esc(i.nombre) + ' del carrito">Quitar</button></div></div>');
    }).join('');
    foot.innerHTML = '<div class="drawer-total"><span>Total</span><strong>' + fmt(estado.total) + '</strong></div>' +
      '<p class="drawer-nota">El envío (delivery o retiro) se elige en el siguiente paso.</p>' +
      '<a href="carrito.php" class="drawer-cta">Ir a pagar ' + ICO.flecha + '</a>' +
      '<button type="button" class="drawer-seguir" data-cerrar-drawer>Seguir comprando</button>';
  }

  function abrirDrawer(ev) {
    if (!drawer) return;
    if (ev && ev.preventDefault) ev.preventDefault();
    opener = D.activeElement;
    if (estado.items.length || !estado.count) pintarDrawer();   // pinta ya con lo último que sabemos…
    else { $('#drawer-body').innerHTML = '<div class="drawer-vacio">Cargando tu carrito…</div>'; $('#drawer-foot').innerHTML = ''; }
    llamar('resumen', {}).then(function (j) { sincronizar(j); }).catch(function () { /* se queda con lo último */ });
    drawer.classList.add('is-open'); overlay.classList.add('is-open'); D.body.classList.add('drawer-abierto');
    var fab = $('#fab'); if (fab) fab.classList.remove('is-open');
    setTimeout(function () { var c = $('#drawer-cerrar'); if (c) c.focus(); }, 30);
  }
  function cerrarDrawer() {
    if (!drawerAbierto()) return;
    drawer.classList.remove('is-open'); overlay.classList.remove('is-open'); D.body.classList.remove('drawer-abierto');
    if (opener && opener.focus && D.contains(opener)) opener.focus();
  }

  if (drawer) {
    overlay.addEventListener('click', cerrarDrawer);
    drawer.addEventListener('click', function (e) {
      if (e.target.closest('#drawer-cerrar, [data-cerrar-drawer]')) return cerrarDrawer();
      var item = e.target.closest('.drawer-item'); if (!item) return;
      var id = item.dataset.id, it = estado.items.filter(function (x) { return String(x.id) === String(id); })[0]; if (!it) return;
      var d = e.target.closest('[data-delta]'), q = e.target.closest('[data-quitar]');
      if (!d && !q) return;
      var nueva = q ? 0 : it.cantidad + parseInt(d.dataset.delta, 10);
      llamar('cambiar', { producto_id: id, cantidad: nueva }).then(function (j) {
        sincronizar(j, nueva > 0 ? it.nombre + ': ' + plural(nueva, 'unidad', 'unidades') + '.' : it.nombre + ' quitado.');
        if (nueva === 0) { var c = $('.drawer-item .qty-btn, .drawer-cta', drawer); if (c) c.focus(); }
      }).catch(function (err) { if (err.estado) sincronizar(err.estado); if (err.message !== 'login') toast(err.message, { error: true }); });
    });
    D.addEventListener('keydown', function (e) {
      if (!drawerAbierto()) return;
      if (e.key === 'Escape') { e.preventDefault(); return cerrarDrawer(); }
      if (e.key !== 'Tab') return;                     // trampa de foco: el fondo no se puede alcanzar con Tab
      var f = $$('button:not([disabled]), a[href]', drawer).filter(function (x) { return x.offsetParent !== null; });
      if (!f.length) return;
      var p = f[0], u = f[f.length - 1];
      if (e.shiftKey && D.activeElement === p) { e.preventDefault(); u.focus(); }
      else if (!e.shiftKey && D.activeElement === u) { e.preventDefault(); p.focus(); }
    });
  }
  // El botón del header sigue siendo un enlace a carrito.php (sin JS funciona igual); con JS abre el mini-carrito.
  var enCarrito = /carrito\.php$/.test(W.location.pathname);
  var hb = $('#btn-carrito');
  if (hb && drawer && !enCarrito) hb.addEventListener('click', function (e) { if (e.ctrlKey || e.metaKey || e.shiftKey || e.button) return; abrirDrawer(e); });
  var cb = $('#cart-bar'); if (cb) cb.addEventListener('click', abrirDrawer);

  // Estado inicial desde lo que ya dibujó el servidor (sin pedir nada extra)
  (function () {
    var b = $('#carrito-badge'), t = $('#carrito-total');
    estado.count = b ? parseInt(b.textContent, 10) || 0 : 0;
    estado.total = parseFloat(t ? t.textContent.replace(/\./g, '').replace(/[^\d,]/g, '').replace(',', '.') : '0') || 0;
    var n = $('#cart-bar-n'), tt = $('#cart-bar-total');
    if (n) n.textContent = estado.count; if (tt) tt.textContent = fmt(estado.total);
    if ($('.products-area') && estado.count > 0) D.body.classList.add('has-cartbar');
  })();

  /* ---------- 6. Botón flotante único (ayuda + WhatsApp) ---------- */
  (function () {
    var fab = $('#fab'), tg = $('#fab-toggle'); if (!fab || !tg) return;
    D.documentElement.classList.add('fab-on');   // oculta los dos botones originales (sin JS siguen visibles)
    function abrir(v) { fab.classList.toggle('is-open', v); tg.setAttribute('aria-expanded', v ? 'true' : 'false'); }
    tg.addEventListener('click', function () { abrir(!fab.classList.contains('is-open')); });
    D.addEventListener('click', function (e) { if (!fab.contains(e.target)) abrir(false); });
    D.addEventListener('keydown', function (e) { if (e.key === 'Escape' && fab.classList.contains('is-open')) { abrir(false); tg.focus(); } });
    var chat = $('#fab-chat');
    if (chat) chat.addEventListener('click', function () { abrir(false); var l = $('#cb-abrir'); if (l) l.click(); });
    var cerrarChat = $('#cb-cerrar'); if (cerrarChat) cerrarChat.addEventListener('click', function () { setTimeout(function () { tg.focus(); }, 0); });

    // En móvil se esconde al bajar (libera los botones de las tarjetas) y vuelve al subir o al enfocarlo.
    var ultimo = W.scrollY, tick = false;
    W.addEventListener('scroll', function () {
      if (tick) return; tick = true;
      requestAnimationFrame(function () {
        var y = W.scrollY, movil = W.matchMedia('(max-width: 720px)').matches;
        if (movil && !fab.classList.contains('is-open')) {
          if (y > ultimo + 6 && y > 240) fab.classList.add('fab--oculto');
          else if (y < ultimo - 6 || y < 240) fab.classList.remove('fab--oculto');
        } else fab.classList.remove('fab--oculto');
        ultimo = y; tick = false;
      });
    }, { passive: true });
    fab.addEventListener('focusin', function () { fab.classList.remove('fab--oculto'); });
  })();

  /* ---------- 8. Mapa con fachada (el iframe de Google se carga solo al pedirlo) ---------- */
  $$('[data-mapa-cargar]').forEach(function (b) {
    b.addEventListener('click', function () {
      var cont = b.closest('.mapa-embed'), src = cont && cont.dataset.src;
      if (!src) { toast('En el sitio real acá se carga el mapa de Google.'); return; }
      var ifr = D.createElement('iframe');
      ifr.src = src; ifr.title = 'Mapa con la ubicación de El Rápido'; ifr.loading = 'lazy'; ifr.referrerPolicy = 'no-referrer-when-downgrade';
      ifr.style.cssText = 'position:absolute;inset:0;width:100%;height:100%;border:0';
      cont.appendChild(ifr); b.remove();
    });
  });

  /* ---------- 7. GSAP (M01–M10) ----------
     Presets del skill ui-ux-pro-max (search.py --domain gsap): Stagger List, Scroll Reveal, Hover micro, parallax-subtle.
     Lo marcado "criterio propio" no tiene preset en el skill. Todo corre dentro de gsap.matchMedia():
     con "reducir movimiento" activado nada se anima y el contenido queda visible y estático. */
  function iniciarGsap() {
    var gsap = W.gsap;
    if (W.ScrollTrigger) gsap.registerPlugin(W.ScrollTrigger);
    D.documentElement.classList.add('gsap-on');
    var mm = gsap.matchMedia();

    mm.add('(prefers-reduced-motion: no-preference)', function () {
      /* M01 · Entrada del hero (Stagger List · standard): una sola línea de tiempo en vez de 2 animaciones CSS sueltas */
      var hero = $('.hero-texto');
      if (hero) {
        var partes = $$('.hero-kicker, .hero-titulo-chica, .hero-titulo-grande, .hero-subtitulo, .hero-botones > *, .hero-estado, .hero-datos li', hero);
        gsap.timeline({ defaults: { ease: 'power3.out' } })
          .from(partes, { y: 22, opacity: 0, duration: .7, stagger: .07, clearProps: 'transform,opacity' })
          .from('.hero-imagen', { y: 28, opacity: 0, duration: .9, clearProps: 'transform,opacity' }, 0.1);
      }

      /* M02 · Parallax suave de la foto (parallax-subtle): solo en pantallas grandes, ±4 % */
      if (W.ScrollTrigger && $('.foto-hero') && W.matchMedia('(min-width: 901px)').matches) {
        gsap.to('.foto-hero', { yPercent: -4, ease: 'none', scrollTrigger: { trigger: '.hero', start: 'top top', end: 'bottom top', scrub: .6 } });
      }

      /* M03 · Botón magnético (Hover micro · magnetic): solo con mouse, desplazamiento máx. 6 px */
      if (W.matchMedia('(hover: hover) and (pointer: fine)').matches) {
        $$('.btn-wsp').forEach(function (btn) {
          var qx = gsap.quickTo(btn, 'x', { duration: .4, ease: 'power3.out' }), qy = gsap.quickTo(btn, 'y', { duration: .4, ease: 'power3.out' });
          btn.addEventListener('pointermove', function (e) {
            var r = btn.getBoundingClientRect();
            qx(((e.clientX - r.left) / r.width - .5) * 12); qy(((e.clientY - r.top) / r.height - .5) * 8);
          });
          btn.addEventListener('pointerleave', function () { qx(0); qy(0); });
        });
      }

      /* M04 · Contador "+5 años" (criterio propio): cuenta al verse el chip */
      var num = $('.chip-num');
      if (num && /\d/.test(num.textContent) && W.ScrollTrigger) {
        var meta = parseInt(num.textContent.replace(/\D/g, ''), 10), obj = { v: 0 };
        num.textContent = '+0';
        W.ScrollTrigger.create({ trigger: num, start: 'top 95%', once: true, onEnter: function () {
          gsap.to(obj, { v: meta, duration: 1.1, ease: 'power2.out', onUpdate: function () { num.textContent = '+' + Math.round(obj.v); }, onComplete: function () { num.textContent = '+' + meta; } });
        } });
      }

      /* M05 · Revelado al hacer scroll (Scroll Reveal · standard): reemplaza a reveal.js, un solo mecanismo */
      var rev = $$('[data-reveal]');
      if (rev.length && W.ScrollTrigger) {
        rev.forEach(function (el) { el.classList.remove('reveal-oculto'); });
        gsap.set(rev, { opacity: 0, y: 24 });
        W.ScrollTrigger.batch(rev, {
          start: 'top 90%', once: true, batchMax: 6,
          onEnter: function (b) { gsap.to(b, { opacity: 1, y: 0, duration: .6, ease: 'power2.out', stagger: .08, overwrite: true, clearProps: 'transform,opacity' }); }
        });
        // Red de seguridad: si algo no se reveló (p. ej. contenido agregado después), se muestra igual
        setTimeout(function () { rev.forEach(function (el) { if (getComputedStyle(el).opacity === '0' && el.getBoundingClientRect().top < W.innerHeight) gsap.set(el, { opacity: 1, y: 0, clearProps: 'transform,opacity' }); }); }, 2500);
      }

      /* M06 · Categorías (Hover micro · subtle): el círculo sube y rota apenas */
      if (W.matchMedia('(hover: hover)').matches) {
        $$('.categoria-item').forEach(function (it) {
          var c = $('.categoria-circulo', it); if (!c) return;
          it.addEventListener('pointerenter', function () { gsap.to(c, { y: -4, rotate: -4, duration: .3, ease: 'power2.out' }); });
          it.addEventListener('pointerleave', function () { gsap.to(c, { y: 0, rotate: 0, duration: .35, ease: 'power2.out' }); });
        });
      }
    });
  }
  if (HAY_GSAP) iniciarGsap();

  /* API pública mínima (útil para otras páginas y para la preview) */
  W.ElRapido = { toast: toast, abrirCarrito: abrirDrawer, cerrarCarrito: cerrarDrawer, carrito: { sincronizar: sincronizar, llamar: llamar } };
})();

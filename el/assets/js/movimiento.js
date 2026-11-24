/* movimiento.js · capa de animaciones. No cambia fotos ni contenido; convive con mejoras.js (GSAP). */
(function () {
  'use strict';
  var D = document, W = window;
  var RM = W.matchMedia('(prefers-reduced-motion: reduce)').matches;
  var $ = function (s, c) { return (c || D).querySelector(s); };
  var $$ = function (s, c) { return [].slice.call((c || D).querySelectorAll(s)); };
  function ready(fn) { D.readyState === 'loading' ? D.addEventListener('DOMContentLoaded', fn) : fn(); }

  ready(function () {
    /* Pill del menú lateral (también sin movimiento reducido: es feedback, no decoración) */
    var lista = $('.cat-list');
    if (lista) {
      var pill = D.createElement('div'); pill.className = 'mv-pill'; pill.setAttribute('aria-hidden', 'true'); lista.insertBefore(pill, lista.firstChild);
      var act = $('a.active', lista); act = act && act.closest('li');
      var mover = function (li) { if (li) { pill.style.height = li.offsetHeight + 'px'; pill.style.translate = '0 ' + li.offsetTop + 'px'; } };
      mover(act);
      $$('li', lista).forEach(function (li) {
        li.addEventListener('pointerenter', function () { mover(li); });
        li.addEventListener('focusin', function () { mover(li); });
      });
      lista.addEventListener('pointerleave', function () { mover(act); });
    }
    if (RM) return;
    D.documentElement.classList.add('mv-js');
    /* Los títulos animados esperan a que cargue la tipografía (máx. 1,5 s) para no mostrar letras de reemplazo */
    var okFuentes = function () { D.documentElement.classList.add('mv-fonts'); };
    if (D.fonts && D.fonts.ready) { D.fonts.ready.then(okFuentes); setTimeout(okFuentes, 1500); } else okFuentes();

    /* Barra de progreso de lectura */
    var hd = $('#site-header'), barra;
    if (hd) { barra = D.createElement('div'); barra.className = 'mv-prog'; barra.setAttribute('aria-hidden', 'true'); hd.appendChild(barra); }

    /* Título del hero de Inicio, letra por letra */
    var tg = $('.hero-titulo-grande');
    if (tg) {
      var h1 = tg.closest('h1'); if (h1) h1.setAttribute('aria-label', h1.textContent.replace(/\s+/g, ' ').trim());
      var k = 0;
      (function partir(nodo) {
        [].slice.call(nodo.childNodes).forEach(function (n) {
          if (n.nodeType === 3) {
            var f = D.createDocumentFragment();
            n.textContent.split('').forEach(function (ch) {
              if (ch === ' ') { f.appendChild(D.createTextNode(' ')); return; }
              var s = D.createElement('span'); s.className = 'mv-l'; s.setAttribute('aria-hidden', 'true'); s.style.setProperty('--k', k++); s.textContent = ch; f.appendChild(s);
            });
            nodo.replaceChild(f, n);
          } else if (n.nodeType === 1) partir(n);
        });
      })(tg);
    }
    /* Título del hero de Productos: sube desde una máscara */
    var ht = $('.hero-text h1');
    if (ht && !tg) ht.innerHTML = '<span class="mv-mask"><span>' + ht.innerHTML + '</span></span>';

    $$('.categoria-item').forEach(function (c, i) { c.style.setProperty('--n', i); });

    /* Entradas: tarjetas de producto (en cascada diagonal) y tarjetas de Nosotros */
    var io = 'IntersectionObserver' in W ? new IntersectionObserver(function (es) {
      es.forEach(function (e) { if (e.isIntersecting) { e.target.classList.add('mv-in'); io.unobserve(e.target); } });
    }, { threshold: .15 }) : null;
    if (io) {
      $$('.products-grid .product-card, .rail .product-card').forEach(function (c, i) { c.style.setProperty('--mv-i', (i % 4) + Math.floor(i / 4)); c.classList.add('mv-pre'); io.observe(c); });
      $$('.info-card').forEach(function (c) { c.classList.add('mv-pre'); $$('.check-list li', c).forEach(function (li, j) { li.style.setProperty('--j', j); }); $$('.check-list .check-ico', c).forEach(function (ic, j) { ic.style.setProperty('--j', j); }); io.observe(c); });
    }


    /* Ruta "Cómo pedir": se dibuja con el scroll y la moto la recorre */
    var pasos = $('.pasos'), NS = 'http://www.w3.org/2000/svg', rf, base, mv, L = 0;
    function trazar() {
      if (!pasos) return;
      var lis = $$('.paso', pasos); if (lis.length < 3 || W.innerWidth <= 900) return;
      var r0 = pasos.getBoundingClientRect(), p = lis.map(function (l) { var r = l.getBoundingClientRect(); return { x: r.left - r0.left + r.width / 2, y: r.top - r0.top + r.height / 2 }; });
      var d = 'M' + p[0].x + ' ' + p[0].y, i, dx;
      for (i = 1; i < 3; i++) { dx = (p[i].x - p[i - 1].x) / 2; d += ' C' + (p[i - 1].x + dx) + ' ' + p[i - 1].y + ' ' + (p[i].x - dx) + ' ' + p[i].y + ' ' + p[i].x + ' ' + p[i].y; }
      if (!rf) {
        var s = D.createElementNS(NS, 'svg'); s.setAttribute('class', 'mv-ruta'); s.setAttribute('aria-hidden', 'true');
        base = D.createElementNS(NS, 'path'); base.setAttribute('fill', 'none'); base.setAttribute('stroke', 'var(--crema-linea)'); base.setAttribute('stroke-width', '4'); base.setAttribute('stroke-dasharray', '2 12'); base.setAttribute('stroke-linecap', 'round');
        rf = D.createElementNS(NS, 'path'); rf.setAttribute('fill', 'none'); rf.setAttribute('stroke', 'var(--rojo)'); rf.setAttribute('stroke-width', '4'); rf.setAttribute('stroke-linecap', 'round');
        mv = D.createElementNS(NS, 'g'); mv.innerHTML = '<circle r="15" fill="#FFD600" stroke="#D91F26" stroke-width="3"/><path d="M-6 3h9M-3-4l4 7M3 3l-2-7h5" fill="none" stroke="#D91F26" stroke-width="2" stroke-linecap="round"/>';
        s.appendChild(base); s.appendChild(rf); s.appendChild(mv); pasos.insertBefore(s, pasos.firstChild);
      }
      base.setAttribute('d', d); rf.setAttribute('d', d); L = rf.getTotalLength(); rf.style.strokeDasharray = L;
    }
    /* Alto desplazable: se mide solo cuando cambia el tamaño de la página (antes se leía scrollHeight en CADA
       cuadro de scroll, lo que obliga al navegador a recalcular el layout si hubo algún cambio pendiente). */
    var maxScroll = 0, altoVentana = W.innerHeight;
    function medir() { altoVentana = W.innerHeight; maxScroll = D.documentElement.scrollHeight - altoVentana; }
    medir();
    if ('ResizeObserver' in W) new ResizeObserver(function () { medir(); }).observe(D.documentElement);
    W.addEventListener('load', medir);
    function cuadro() {
      var H = altoVentana, m = maxScroll;
      if (barra) barra.style.transform = 'scaleX(' + (m > 0 ? Math.min(1, W.scrollY / m) : 0) + ')';
      if (pasos && pasosVis) {
        var r = pasos.getBoundingClientRect(), p = Math.min(1, Math.max(0, (H * .8 - r.top) / (r.height + H * .2)));
        if (L && rf) { rf.style.strokeDashoffset = L * (1 - p); var q = rf.getPointAtLength(L * p); mv.setAttribute('transform', 'translate(' + q.x + ' ' + q.y + ')'); }
        $$('.paso', pasos).forEach(function (e, i) { e.classList.toggle('mv-on', p > .12 + i * .36); });
      }
    }
    var pasosVis = true;   /* la ruta solo se recalcula mientras está cerca de la pantalla */
    if (pasos && 'IntersectionObserver' in W) new IntersectionObserver(function (es) { pasosVis = es[0].isIntersecting; }, { rootMargin: '200px' }).observe(pasos);
    var tk = 0;
    W.addEventListener('scroll', function () { if (!tk) { tk = 1; requestAnimationFrame(function () { tk = 0; cuadro(); }); } }, { passive: true });
    var rt; W.addEventListener('resize', function () { medir(); clearTimeout(rt); rt = setTimeout(function () { trazar(); cuadro(); }, 120); });
    trazar(); cuadro(); if (D.fonts && D.fonts.ready) D.fonts.ready.then(function () { trazar(); cuadro(); });

    /* Inclinación 3D de las tarjetas de producto (solo con mouse; una lectura de layout por cuadro, no por evento) */
    if (W.matchMedia('(hover: hover) and (pointer: fine)').matches) {
      var ult = null, raf = 0;
      D.addEventListener('pointermove', function (e) {
        var c = e.target.closest && e.target.closest('.product-card'); if (!c || !c.classList.contains('mv-in')) return;
        ult = { c: c, x: e.clientX, y: e.clientY };
        if (!raf) raf = requestAnimationFrame(function () {
          raf = 0; var r = ult.c.getBoundingClientRect(), x = (ult.x - r.left) / r.width - .5, y = (ult.y - r.top) / r.height - .5;
          ult.c.style.transform = 'perspective(800px) rotateY(' + x * 9 + 'deg) rotateX(' + -y * 9 + 'deg) translateY(-4px)';
        });
      }, { passive: true });
      D.addEventListener('pointerout', function (e) { var c = e.target.closest && e.target.closest('.product-card'); if (c) c.style.transform = ''; });
    }

    /* Al agregar: una bolita vuela en arco hasta el carrito; el favorito suelta partículas */
    D.addEventListener('click', function (e) {
      var t = e.target.closest && e.target.closest('.btn-agregar:not([disabled]), .qty-mas:not([disabled]), .fav-btn');
      if (!t) return;
      if (t.classList.contains('fav-btn')) {
        setTimeout(function () {
          if (t.getAttribute('aria-pressed') !== 'true') return;
          var r = t.getBoundingClientRect();
          for (var i = 0; i < 7; i++) {
            var d = D.createElement('i'); d.className = 'mv-burst'; d.style.left = r.left + r.width / 2 - 4 + 'px'; d.style.top = r.top + r.height / 2 - 4 + 'px'; D.body.appendChild(d);
            var g = i / 7 * 6.28; d.animate([{ transform: 'none', opacity: 1 }, { transform: 'translate(' + Math.cos(g) * 34 + 'px,' + Math.sin(g) * 34 + 'px) scale(0)', opacity: 0 }], { duration: 600, easing: 'cubic-bezier(.2,.8,.3,1)' }).onfinish = (function (n) { return function () { n.remove(); }; })(d);
          }
        }, 80);
        return;
      }
      var carro = $('.btn-carrito'); if (!carro || !W.USUARIO_LOGUEADO) return;
      var s = t.getBoundingClientRect(), c = carro.getBoundingClientRect(), f = D.createElement('i'); f.className = 'mv-fly'; D.body.appendChild(f);
      var dx = c.left + c.width / 2 - s.left - s.width / 2, dy = c.top + c.height / 2 - s.top - s.height / 2;
      f.style.left = s.left + s.width / 2 - 13 + 'px'; f.style.top = s.top + s.height / 2 - 13 + 'px';
      f.animate([{ transform: 'translate(0,0) scale(1)' }, { transform: 'translate(' + dx * .5 + 'px,' + (dy * .5 - 90) + 'px) scale(1.2)', offset: .45 }, { transform: 'translate(' + dx + 'px,' + dy + 'px) scale(.4)' }], { duration: 780, easing: 'cubic-bezier(.5,0,.4,1)' }).onfinish = function () { f.remove(); };
    });
  });
})();

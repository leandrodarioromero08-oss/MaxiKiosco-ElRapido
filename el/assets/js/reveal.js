/* reveal.js
 * Revela con un fundido + leve desplazamiento los elementos marcados con
 * [data-reveal] a medida que entran en pantalla. Lo usan index.php,
 * nosotros.php y productos.php (antes esta lógica estaba copiada en
 * global.js, nosotros.js y productos.js).
 * Progressive enhancement: el contenido arranca visible y recién acá se
 * oculta para animarlo; si el script no corre, nada queda invisible.
 * Respeta "reducir movimiento".
 */
(function () {
  var elementos = document.querySelectorAll('[data-reveal]');
  if (!elementos.length) return;
  if (window.matchMedia('(prefers-reduced-motion: reduce)').matches || !('IntersectionObserver' in window)) return;

  elementos.forEach(function (el) { el.classList.add('reveal-oculto'); });

  var observer = new IntersectionObserver(function (entries) {
    entries.forEach(function (entry) {
      if (entry.isIntersecting) {
        entry.target.classList.remove('reveal-oculto');
        observer.unobserve(entry.target);
      }
    });
  }, { threshold: 0.12, rootMargin: '0px 0px -40px 0px' });

  elementos.forEach(function (el) { observer.observe(el); });
})();

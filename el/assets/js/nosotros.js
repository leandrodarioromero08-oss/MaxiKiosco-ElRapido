/* nosotros.js
 * Anima el número "+5" del hero contando desde 0 (el revelado en scroll
 * vive en reveal.js). Respeta prefers-reduced-motion; sin JS queda el
 * número final ya escrito en el HTML.
 */

(function () {
  var prefiereMenosMovimiento = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  /* --- Contador animado (ej: 0 -> 5) --- */
  var contadores = document.querySelectorAll('[data-contar-hasta]');
  if (!contadores.length) return;

  // El HTML trae el número final (lo leen buscadores y lectores de pantalla); solo si se va a animar
  // se parte desde 0, y recién cuando el bloque entra en pantalla.
  if (!prefiereMenosMovimiento) {
    contadores.forEach(function (el) { if (!isNaN(parseInt(el.dataset.contarHasta, 10))) el.textContent = '0'; });
  }

  function animarContador(el) {
    var destino = parseInt(el.dataset.contarHasta, 10);
    if (isNaN(destino)) return; // data-contar-hasta no numérico: se deja el texto original
    if (prefiereMenosMovimiento) {
      el.textContent = destino;
      return;
    }
    var duracionMs = 900;
    var inicio = null;
    function paso(timestamp) {
      if (inicio === null) inicio = timestamp;
      var avance = Math.min((timestamp - inicio) / duracionMs, 1);
      el.textContent = Math.floor(avance * destino);
      if (avance < 1) {
        requestAnimationFrame(paso);
      } else {
        el.textContent = destino;
      }
    }
    requestAnimationFrame(paso);
  }

  if ('IntersectionObserver' in window) {
    var observerContador = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (entry.isIntersecting) {
          animarContador(entry.target);
          observerContador.unobserve(entry.target);
        }
      });
    }, { threshold: 0.5 });
    contadores.forEach(function (el) { observerContador.observe(el); });
  } else {
    contadores.forEach(animarContador);
  }
})();

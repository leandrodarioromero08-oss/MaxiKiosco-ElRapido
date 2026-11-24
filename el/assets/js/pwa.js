/* assets/js/pwa.js — MODO APP: registra el service worker y maneja el botón "Instalar app".
 * Lo cargan el sitio (includes/footer.php) y el panel admin (admin/Template/topbar.php y admin/login.php).
 *  - Registra sw.js (queda en la carpeta del sitio, así que cubre público y admin).
 *  - Android / Chrome / Edge: guarda el aviso de instalación del navegador y muestra el botón [data-pwa-instalar];
 *    al tocarlo se abre el cuadro "Instalar app".
 *  - iPhone / iPad (Safari no tiene ese cuadro): el mismo botón explica cómo agregarla a la pantalla de inicio.
 *  - Si la app ya está instalada y abierta como app, el botón no aparece.
 * Requiere HTTPS (o localhost): en cualquier otra dirección http el navegador no permite instalar.
 */
(function () {
  'use strict';
  var W = window, D = document, N = W.navigator;
  var yo = D.currentScript || D.querySelector('script[src$="pwa.js"]');

  /* ---------- service worker ---------- */
  if ('serviceWorker' in N && W.isSecureContext && yo && yo.src) {
    W.addEventListener('load', function () {
      var sw = new URL('../../sw.js', yo.src).href;           // assets/js/pwa.js → sw.js en la raíz del sitio
      N.serviceWorker.register(sw).catch(function (e) { if (W.console) console.warn('[el-rapido] service worker:', e); });
    });
  }

  /* ---------- botón instalar ---------- */
  var standalone = (W.matchMedia && W.matchMedia('(display-mode: standalone)').matches) || N.standalone === true;
  var esIOS = /iphone|ipad|ipod/i.test(N.userAgent) || (N.platform === 'MacIntel' && N.maxTouchPoints > 1);
  var diferido = null;

  function botones() { return D.querySelectorAll('[data-pwa-instalar]'); }
  function mostrar(v) { var bs = botones(); for (var i = 0; i < bs.length; i++) bs[i].hidden = !v; }
  function avisar(msg) {
    if (W.ElRapido && W.ElRapido.toast) W.ElRapido.toast(msg);
    else if (W.AdminNotif) W.AdminNotif.mostrar('', msg, { tipo: 'info', duracion: 12000 });
  }

  W.addEventListener('beforeinstallprompt', function (e) {
    e.preventDefault();                                       // el botón propio lo dispara cuando la persona quiere
    diferido = e;
    if (!standalone) mostrar(true);
  });
  W.addEventListener('appinstalled', function () {
    diferido = null; mostrar(false);
    avisar('¡Listo! El Rápido quedó instalado en tu dispositivo.');
  });

  D.addEventListener('click', function (e) {
    var b = e.target.closest && e.target.closest('[data-pwa-instalar]');
    if (!b) return;
    if (diferido) {
      var p = diferido; diferido = null;
      p.prompt();
      p.userChoice.then(function (r) { if (r && r.outcome !== 'accepted') mostrar(false); });   // dijo que no: no insistir en esta visita
    } else if (esIOS) {
      avisar('En iPhone o iPad: tocá el botón Compartir de Safari y elegí «Agregar a pantalla de inicio».');
    }
  });

  if (standalone) mostrar(false);
  else if (esIOS) mostrar(true);
})();

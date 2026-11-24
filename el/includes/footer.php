</main>

<!-- ======= BANNER BENEFICIOS ======= -->
<section class="beneficios-banner" aria-label="Beneficios de comprar en El Rápido">
  <div class="beneficios-inner">
    <div class="beneficio-item">
      <span class="beneficio-icono"><?= icono('rayo', '', 22) ?></span>
      <div>
        <div class="beneficio-titulo">Rápido</div>
        <div class="beneficio-desc">Pedí y recibí en minutos</div>
      </div>
    </div>
    <div class="beneficio-item">
      <span class="beneficio-icono"><?= icono('escudo-check', '', 22) ?></span>
      <div>
        <div class="beneficio-titulo">Seguro</div>
        <div class="beneficio-desc">Tus compras siempre protegidas</div>
      </div>
    </div>
    <div class="beneficio-item">
      <span class="beneficio-icono"><?= icono('etiqueta', '', 22) ?></span>
      <div>
        <div class="beneficio-titulo">Ofertas</div>
        <div class="beneficio-desc">Promos exclusivas todos los días</div>
      </div>
    </div>
    <div class="beneficio-item">
      <span class="beneficio-icono"><?= icono('telefono', '', 22) ?></span>
      <div>
        <div class="beneficio-titulo">Atención</div>
        <div class="beneficio-desc">Siempre disponibles por WhatsApp</div>
      </div>
    </div>
  </div>
</section>

<!-- ======= FOOTER ======= -->
<footer class="site-footer">
  <div class="footer-inner">

    <!-- Col 1 - Marca -->
    <div>
      <div class="footer-logo-text"><img src="assets/img/logo-el-rapido-inicio.png" alt="El Rápido" loading="lazy" decoding="async"></div>
      <p class="footer-desc">Tu kiosco de confianza.<br>Rápido, cerca y siempre<br>con las mejores ofertas.</p>
      <div class="footer-socials">
        <a href="<?= htmlspecialchars(urlWhatsapp()) ?>" class="footer-social" target="_blank" rel="noopener" aria-label="Escribinos por WhatsApp (se abre en una pestaña nueva)"><?= icono('mensaje', '', 16) ?></a>
        <a href="<?= htmlspecialchars(INSTAGRAM_URL) ?>" class="footer-social" target="_blank" rel="noopener" aria-label="Seguinos en Instagram (se abre en una pestaña nueva)"><?= icono('instagram', '', 16) ?></a>
        <a href="<?= htmlspecialchars(FACEBOOK_URL) ?>" class="footer-social" target="_blank" rel="noopener" aria-label="Seguinos en Facebook (se abre en una pestaña nueva)"><?= icono('facebook', '', 16) ?></a>
      </div>
    </div>

    <!-- Col 2 - Navegación -->
    <div>
      <h2 class="footer-col-titulo">Navegación</h2>
      <nav class="footer-links" aria-label="Navegación del sitio">
        <a href="index.php">Inicio</a>
        <a href="productos.php">Productos</a>
        <a href="carrito.php">Carrito</a>
        <?php if ($logueado): ?>
          <a href="mi-cuenta.php?seccion=resumen">Mi cuenta</a>
          <a href="mi-cuenta.php?seccion=pedidos">Mis pedidos</a>
        <?php else: ?>
          <a href="login.php">Iniciar sesión</a>
          <a href="registro.php">Crear cuenta</a>
        <?php endif; ?>
      </nav>
    </div>

    <!-- Col 3 - Ayuda -->
    <div>
      <h2 class="footer-col-titulo">Ayuda</h2>
      <nav class="footer-links" aria-label="Ayuda">
        <a href="preguntas-frecuentes.php">Preguntas frecuentes</a>
        <a href="politicas.php">Políticas de cambio</a>
        <a href="terminos.php">Términos y condiciones</a>
      </nav>
    </div>

    <!-- Col 4 - Nosotros -->
    <div>
      <h2 class="footer-col-titulo">Nosotros</h2>
      <nav class="footer-links" aria-label="Sobre El Rápido">
        <a href="nosotros.php">Quiénes somos</a>
        <a href="<?= htmlspecialchars(urlWhatsapp(WP_MSG_TRABAJO)) ?>" target="_blank" rel="noopener">Trabajá con nosotros<span class="sr-only"> (se abre en una pestaña nueva)</span></a>
      </nav>
    </div>

    <!-- Col 5 - Contacto -->
    <div>
      <h2 class="footer-col-titulo">Contacto</h2>
      <div class="footer-contacto">
        <a href="tel:<?= htmlspecialchars(preg_replace('/\s+/', '', NEGOCIO_TELEFONO)) ?>" class="footer-contacto-item">
          <span><?= icono('telefono', '', 15) ?></span>
          <span><?= htmlspecialchars(NEGOCIO_TELEFONO) ?></span>
        </a>
        <a href="<?= htmlspecialchars(NEGOCIO_MAPS_URL) ?>" target="_blank" rel="noopener" class="footer-contacto-item">
          <span><?= icono('ubicacion', '', 15) ?></span>
          <span><?= htmlspecialchars(NEGOCIO_DIRECCION) ?><br>Monte Caseros, Corrientes<span class="sr-only"> (se abre en una pestaña nueva)</span></span>
        </a>
        <div class="footer-contacto-item">
          <span><?= icono('reloj', '', 15) ?></span>
          <span>Todos los días, <?= htmlspecialchars(NEGOCIO_HORARIO) ?></span>
        </div>
        <a href="mailto:<?= htmlspecialchars(SITE_EMAIL) ?>" class="footer-contacto-item">
          <span><?= icono('correo', '', 15) ?></span>
          <span><?= htmlspecialchars(SITE_EMAIL) ?></span>
        </a>
      </div>
    </div>

  </div>

  <div class="footer-copy">
    © <?= date('Y') ?> <?= htmlspecialchars(NEGOCIO_NOMBRE) ?>. Todos los derechos reservados.
    <span class="footer-copy-sep" aria-hidden="true">·</span>
    <a href="index.php?intro=1" class="footer-intro" data-intro-ver><svg viewBox="0 0 24 24" width="13" height="13" fill="currentColor" aria-hidden="true"><path d="M8 5.5v13a1 1 0 0 0 1.5.86l10.5-6.5a1 1 0 0 0 0-1.72L9.5 4.64A1 1 0 0 0 8 5.5Z"/></svg> Ver intro</a>
    <span class="footer-copy-sep" aria-hidden="true">·</span>
    <a href="<?= !empty($barraAdmin) ? 'admin/Seccion/inicio.php' : 'admin/login.php' ?>" class="footer-admin" rel="nofollow"><?= !empty($barraAdmin) ? 'Ir al panel' : 'Acceso administración' ?></a>
  </div>
</footer>


<?php include __DIR__ . '/chatbot_widget.php'; ?>

<!-- ======= WHATSAPP FLOTANTE ======= -->
<a href="<?= htmlspecialchars(urlWhatsapp()) ?>" class="wsp-flotante" target="_blank" rel="noopener" aria-label="Contactar por WhatsApp (se abre en una pestaña nueva)">
  <svg class="wsp-icono" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false">
    <path d="M12.004 2c-5.523 0-10 4.477-10 10 0 1.77.462 3.492 1.34 5.01L2 22l5.13-1.318A9.96 9.96 0 0 0 12.004 22c5.523 0 10-4.477 10-10s-4.477-10-10-10Zm0 18.09a8.06 8.06 0 0 1-4.115-1.126l-.295-.176-3.043.782.81-2.965-.192-.304A8.09 8.09 0 1 1 12.004 20.09Z"/>
    <path d="M17.34 14.16c-.294-.147-1.74-.858-2.01-.957-.27-.098-.466-.147-.663.148-.196.294-.76.957-.932 1.153-.171.196-.343.22-.637.073-.294-.147-1.241-.457-2.364-1.458-.874-.78-1.464-1.744-1.635-2.038-.171-.294-.018-.453.13-.6.133-.132.294-.343.441-.515.147-.171.196-.294.294-.49.098-.196.049-.368-.024-.515-.073-.147-.663-1.596-.908-2.185-.239-.575-.482-.497-.663-.506a12.66 12.66 0 0 0-.564-.01c-.196 0-.515.073-.784.368-.27.294-1.03 1.006-1.03 2.454s1.055 2.847 1.201 3.043c.147.196 2.076 3.17 5.03 4.444.703.303 1.251.484 1.678.62.705.224 1.347.192 1.855.117.566-.085 1.74-.712 1.985-1.4.245-.688.245-1.278.171-1.4-.073-.123-.269-.196-.564-.343Z"/>
  </svg>
</a>


<!-- ======= MEJORAS UI: botón flotante único, aviso, mini-carrito y barra de carrito (assets/js/mejoras.js) =======
     Sin JavaScript no se ve nada de esto y siguen los dos botones originales (chat + WhatsApp). -->
<div class="fab" id="fab">
  <div class="fab-menu" id="fab-menu">
    <button type="button" class="fab-item" id="fab-chat"><span class="fab-item-ico"><?= icono('mensaje', '', 20) ?></span>Chat de ayuda</button>
    <button type="button" class="fab-item" data-sonidos-toggle data-sin-sonido aria-pressed="true" hidden><span class="fab-item-ico sn-ico"></span><span class="sn-txt">Sonidos</span></button>
    <button type="button" class="fab-item" id="pwa-instalar" data-pwa-instalar hidden><span class="fab-item-ico"><svg class="icono-svg" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" x2="12" y1="15" y2="3"/></svg></span>Instalar app</button>
    <a href="<?= htmlspecialchars(urlWhatsapp()) ?>" class="fab-item fab-item--wsp" target="_blank" rel="noopener">
      <span class="fab-item-ico"><svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false"><path d="M12.004 2c-5.523 0-10 4.477-10 10 0 1.77.462 3.492 1.34 5.01L2 22l5.13-1.318A9.96 9.96 0 0 0 12.004 22c5.523 0 10-4.477 10-10s-4.477-10-10-10Zm0 18.09a8.06 8.06 0 0 1-4.115-1.126l-.295-.176-3.043.782.81-2.965-.192-.304A8.09 8.09 0 1 1 12.004 20.09Z"/><path d="M17.34 14.16c-.294-.147-1.74-.858-2.01-.957-.27-.098-.466-.147-.663.148-.196.294-.76.957-.932 1.153-.171.196-.343.22-.637.073-.294-.147-1.241-.457-2.364-1.458-.874-.78-1.464-1.744-1.635-2.038-.171-.294-.018-.453.13-.6.133-.132.294-.343.441-.515.147-.171.196-.294.294-.49.098-.196.049-.368-.024-.515-.073-.147-.663-1.596-.908-2.185-.239-.575-.482-.497-.663-.506a12.66 12.66 0 0 0-.564-.01c-.196 0-.515.073-.784.368-.27.294-1.03 1.006-1.03 2.454s1.055 2.847 1.201 3.043c.147.196 2.076 3.17 5.03 4.444.703.303 1.251.484 1.678.62.705.224 1.347.192 1.855.117.566-.085 1.74-.712 1.985-1.4.245-.688.245-1.278.171-1.4-.073-.123-.269-.196-.564-.343Z"/></svg></span>
      WhatsApp<span class="sr-only"> (se abre en una pestaña nueva)</span>
    </a>
  </div>
  <button type="button" class="fab-toggle" id="fab-toggle" aria-expanded="false" aria-controls="fab-menu" aria-label="Ayuda y contacto">
    <span class="fab-ico-abrir"><?= icono('mensaje', 'fab-ico', 24) ?></span><span class="fab-ico-cerrar"><?= icono('x', 'fab-ico', 22) ?></span>
  </button>
</div>
<div class="toast-region" id="toast-region" aria-live="polite" aria-atomic="false"></div>
<div class="drawer-overlay" id="drawer-overlay"></div>
<aside class="drawer" id="drawer" role="dialog" aria-modal="true" aria-labelledby="drawer-titulo" tabindex="-1">
  <div class="drawer-head">
    <h2 id="drawer-titulo">Tu carrito <span class="drawer-count" id="drawer-count"></span></h2>
    <button type="button" class="drawer-cerrar" id="drawer-cerrar" aria-label="Cerrar el carrito"><?= icono('x', '', 20) ?></button>
  </div>
  <div class="drawer-body" id="drawer-body"></div>
  <div class="drawer-foot" id="drawer-foot"></div>
</aside>
<button type="button" class="cart-bar" id="cart-bar" aria-haspopup="dialog" aria-controls="drawer">
  <span class="cart-bar-izq"><?= icono('carrito', '', 20) ?><span>Ver carrito</span><span class="cart-bar-n" id="cart-bar-n">0</span></span>
  <span class="cart-bar-der"><span id="cart-bar-total">$0</span><?= icono('flecha-der', '', 16) ?></span>
</button>


<!-- ======= JS MÍNIMO (solo menú hamburger) ======= -->
<script src="<?= htmlspecialchars(assetUrl('assets/js/global.js')) ?>" defer></script>
<script src="<?= htmlspecialchars(assetUrl('assets/js/sonidos.js')) ?>" defer></script>
<script src="<?= htmlspecialchars(assetUrl('assets/js/pwa.js')) ?>" defer></script>
<?php if (!empty($pageIntro)): ?><script src="<?= htmlspecialchars(assetUrl('assets/js/intro.js')) ?>" defer></script><?php endif; ?>
<script src="<?= htmlspecialchars(assetUrl('assets/js/chatbot.js')) ?>" defer></script>
<?php if (!empty($pageChatGuia)): ?><script src="<?= htmlspecialchars(assetUrl('assets/js/chatbot-guia.js')) ?>" defer></script><?php endif; ?>
<script>window.CSRF_TOKEN = window.CSRF_TOKEN || <?= json_encode(csrfToken()) ?>;</script>
<?php // GSAP es opcional: si están los archivos en assets/js/vendor/ se usan; si no, el sitio funciona igual con reveal.js + CSS ?>
<?php if (is_file(__DIR__ . '/../assets/js/vendor/gsap.min.js') && is_file(__DIR__ . '/../assets/js/vendor/ScrollTrigger.min.js')): ?>
<script src="<?= htmlspecialchars(assetUrl('assets/js/vendor/gsap.min.js')) ?>" defer></script>
<script src="<?= htmlspecialchars(assetUrl('assets/js/vendor/ScrollTrigger.min.js')) ?>" defer></script>
<?php endif; ?>
<script src="<?= htmlspecialchars(assetUrl('assets/js/mejoras.js')) ?>" defer></script>
<script src="<?= htmlspecialchars(assetUrl('assets/js/movimiento.js')) ?>" defer></script>
<?php // Avisos al cliente (pedido en camino, etc.): solo con sesión iniciada ?>
<?php if (!empty($logueado)): ?><script src="<?= htmlspecialchars(assetUrl('assets/js/notificaciones.js')) ?>" defer></script><?php endif; ?>

</body>
</html>

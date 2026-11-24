  <!-- ==========================================
       CONTENIDO PRINCIPAL
       ========================================== -->
  <div class="main-content">

    <!-- TOPBAR — igual en todas las páginas -->
    <header class="topbar">
      <!-- Colapsa/expande el sidebar a solo íconos (útil en pantallas chicas
           o notebooks): togglearSidebarAdmin() vive en admin/assets/js/layout.js,
           cargado una sola vez acá porque el topbar se incluye en todas las páginas. -->
      <button class="topbar-menu-btn" type="button" aria-label="Contraer/expandir menú" onclick="togglearSidebarAdmin()"><?= icono('menu', '', 20) ?></button>

      <div class="topbar-right">
        <button type="button" class="topbar-instalar" data-pwa-instalar hidden><svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" x2="12" y1="15" y2="3"/></svg><span>Instalar app</span></button>
        <button type="button" class="topbar-sonido" data-sonidos-toggle data-sin-sonido aria-pressed="true" hidden><span class="sn-ico"></span></button>
        <div class="topbar-user" tabindex="0" onclick="togglearMenuUsuario(event)" onkeydown="if(event.key==='Enter'||event.key===' '){event.preventDefault();togglearMenuUsuario(event);}">
          <div class="avatar"><?= icono('usuario', '', 18) ?></div>
          <div class="user-info">
            <div class="user-name"><?= htmlspecialchars(adminActual()['nombre'] ?? 'Administrador') ?></div>
            <div class="user-role">Admin</div>
          </div>
          <span class="topbar-user-chevron"><?= icono('chevron-abajo', '', 14) ?></span>

          <div class="topbar-user-menu" id="topbar-user-menu">
            <a href="../logout.php"><?= icono('salir', '', 15) ?> Cerrar sesión</a>
          </div>
        </div>
      </div>
    </header>

    <script src="../assets/js/layout.js" defer></script>
    <script src="../assets/js/sonidos.js" defer></script>
    <script src="../../assets/js/pwa.js" defer></script>
    <script src="../assets/js/notificaciones.js" defer></script>
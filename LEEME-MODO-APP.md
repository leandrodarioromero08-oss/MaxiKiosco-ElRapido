# Modo app (PWA): instalar El Rápido como aplicación

Hay DOS apps instalables, cada una con su ícono y su acceso directo:
- **El Rápido** (clientes): abre la página pública en ventana propia, sin barra del navegador.
  Accesos rápidos al mantener apretado el ícono: Productos, Mi carrito, Mi cuenta.
- **Admin El Rápido** (para vos): abre el panel directo en Seccion/inicio.php.
  Accesos rápidos: Pedidos pendientes, Productos.

## Cómo se instala
- **Computadora (Chrome / Edge):** aparece el botón "Instalar app" (menú del botón flotante en el sitio, barra superior en el admin)
  y también el ícono de instalar en la barra de direcciones. Al instalar, Chrome/Edge pueden crear el acceso directo en el
  escritorio y en el menú Inicio (en el cuadro de instalación / en chrome://apps → clic derecho → "Crear acceso directo").
- **Android (Chrome):** botón "Instalar app", o menú ⋮ → "Instalar aplicación". Queda en la pantalla de inicio.
- **iPhone / iPad (Safari):** Safari no tiene cuadro de instalación. El botón "Instalar app" explica el paso:
  Compartir → "Agregar a pantalla de inicio".

## IMPORTANTE: HTTPS
Los navegadores solo permiten instalar y usar service workers en **https://** o en **localhost**.
- En Laragon con `http://localhost/...` funciona en la compu.
- Desde el celular en la red local (`http://192.168.x.x`) NO se puede instalar. Para probar en el celu hace falta HTTPS
  (un túnel tipo Cloudflare Tunnel / ngrok, o el hosting real con SSL).
- En el servidor real: definir `SITE_URL` con https en `includes/config.local.php`.

## Qué hace el service worker (sw.js)
- Sin internet, al abrir o navegar una página muestra `offline.html` ("Sin conexión" + Reintentar).
- Guarda hasta 80 imágenes (productos, logos) para que carguen rápido.
- CSS/JS propios: siempre se piden primero a la red, así los cambios que hagas se ven al instante.
- NO guarda páginas HTML, ni toca el admin, ni los POST, ni `*_api.php` (carrito y avisos siguen funcionando igual).
- Si cambiás imágenes y querés que todos las renueven: subí `VERSION` al inicio de `sw.js` (v1 → v2).

## Archivos
Nuevos: `el/manifest.webmanifest`, `el/admin/manifest.webmanifest`, `el/sw.js`, `el/offline.html`, `el/assets/js/pwa.js`,
`el/assets/img/icono-el-rapido-192.png`, `-512.png`, `-maskable-512.png`, `apple-touch-icon.png`.
Modificados: `el/includes/header.php`, `el/includes/footer.php`, `el/admin/Template/sidebar.php`, `el/admin/Template/topbar.php`,
`el/admin/login.php`, `el/admin/assets/CSS/notificaciones.css`, `el/.htaccess`.

## Íconos
Salen del ícono actual (128 px) agrandado, así que se ven un poco suaves en 512 px.
Si tenés el logo en tamaño grande (o en SVG), reemplazá los 4 PNG nuevos con el mismo nombre y listo.

# Notificaciones (abajo a la derecha)

## Antes de probar: correr la migración
Importar `el/database/migracion_006_notificaciones.sql` en la base (phpMyAdmin / HeidiSQL de Laragon).
Crea la tabla `notificaciones`. Se puede correr más de una vez sin problema.
Si no se corre, el sitio funciona igual: simplemente no se guardan avisos al cliente (queda un renglón en el log de errores).

## Qué hace

**Cliente (página pública, solo con sesión iniciada)**
- Cuando el admin cambia el estado de un pedido a *Preparando*, *Listo para retirar*, *En camino*, *Entregado* o *Cancelado*, se guarda un aviso.
- La página lo consulta cada ~20 s (y apenas el cliente vuelve a la pestaña) y lo muestra abajo a la derecha, con botón "Ver pedido".
- Cada aviso se muestra una sola vez. Si el cliente no estaba en la página, lo ve cuando entra (se conservan 2 días).
- Se cierra solo a los 10 s, se frena si el cliente pasa el mouse, y tiene botón para cerrar. Máximo 3 a la vez.

**Admin**
- Aviso "Nuevo pedido #N · cliente · total · retiro/delivery" cuando entra un pedido (consulta cada ~20 s desde cualquier sección del panel). Si ya está en Pedidos, el botón dice "Actualizar la lista".
- Los mensajes de resultado de Pedidos ("Estado actualizado", errores) ahora salen como aviso abajo a la derecha en vez de ocupar la página.
- La consulta automática NO renueva la sesión: el cierre por 2 h de inactividad sigue funcionando.

## Archivos
Nuevos: `notificaciones_api.php`, `assets/js/notificaciones.js`, `assets/css/notificaciones.css`,
`admin/Seccion/notificaciones_api.php`, `admin/assets/js/notificaciones.js`, `admin/assets/CSS/notificaciones.css`,
`database/migracion_006_notificaciones.sql`.
Modificados: `admin/Config/funciones.php` (cambiarEstadoPedido crea el aviso), `admin/Config/auth.php`, `admin/Seccion/pedidos.php`,
`admin/Template/sidebar.php`, `admin/Template/topbar.php`, `includes/header.php`, `includes/footer.php`, `database/el_rapido.sql`.

## Para lanzar un aviso desde otro script
- Público: `ElRapido.notificar('Título', 'Mensaje', { tipo: 'en_camino', href: 'pedido_detalle.php?id=5' })`
- Admin: `AdminNotif.mostrar('Título', 'Mensaje', { tipo: 'exito' })`  (tipos: exito, error, pedido, info)

## Cambiar tiempos o textos
- Frecuencia de consulta: `INTERVALO` al inicio de ambos `notificaciones.js` (milisegundos).
- Textos que ve el cliente: función `textoNotificacionPedido()` en `admin/Config/funciones.php`.

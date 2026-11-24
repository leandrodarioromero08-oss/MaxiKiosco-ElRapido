# Efectos de sonido

Sin archivos de audio: todo se sintetiza en el navegador (Web Audio), igual que el sonido del carrito del hero.
Un solo script (`el/assets/js/sonidos.js`, y su copia en `el/admin/assets/js/sonidos.js`) escucha los clics de todo el sitio,
incluso de lo que se dibuja por JavaScript (mini-carrito, modales).

## Qué suena
| Acción | Sonido |
|---|---|
| Botón común | clic suave |
| Enlaces | "tick" más bajito |
| Agregar / Agregar todo | pop ascendente + dos notas |
| Eliminar / Quitar / Vaciar / Cancelar pedido | tono descendente |
| + / − de cantidad | tono corto que sube / baja |
| Abrir / cerrar (botón flotante, menús, modales, ✕, Cancelar) | "swish" que sube / baja |
| Activar / desactivar (modo oscuro, favorito, casillas) | dos notas que suben / bajan |
| Confirmar (Finalizar compra, Guardar, Ingresar, Crear cuenta…) | dos notas ascendentes |
| Aviso al cliente (pedido en camino, etc.) | campanita de 3 notas |
| Error | doble zumbido grave |
| Admin: pedido nuevo | timbre de 4 notas |

## Silenciar
- Sitio: botón "Sonidos: sí/no" en el menú del botón flotante (abajo a la derecha).
- Admin: parlante en la barra superior.
- La elección se guarda en el navegador (`elrapido_sonidos`). Por defecto está activado, con volumen bajo.

## Limitación del navegador
Chrome/Safari no dejan sonar nada hasta que la persona toca la página una vez. Un aviso que llegue antes de ese primer toque sale sin sonido.

## Ajustes en el HTML
- `data-sonido="add|confirm|remove|close|click|tick|ninguno"` en un botón fuerza su sonido.
- `data-sin-sonido` en un elemento (o contenedor) lo deja mudo.
- Desde otro script: `ElRapidoSonido.play('notif')` (nombres en la tabla `SONIDOS` del archivo).
- Volumen general: `master.gain.value` (0.45) en `sonidos.js`.

## Archivos
Nuevos: `el/assets/js/sonidos.js`, `el/admin/assets/js/sonidos.js`.
Modificados: `el/includes/footer.php`, `el/assets/js/mejoras.js` (sonido de error), `el/assets/js/notificaciones.js`,
`el/assets/css/mejoras.css`, `el/admin/Template/topbar.php`, `el/admin/assets/js/notificaciones.js`, `el/admin/assets/CSS/notificaciones.css`.

# El Rápido · mejoras UI/UX (versión con header OpenCode)

Proyecto completo con las mejoras aplicadas. Copiá la carpeta `el/` sobre tu sitio (hacé una copia de seguridad antes).

## Header
Se tomó el diseño del header de `elrapido-OpenCode`: pastillas en el menú (activo en rojo suave), botones con borde fino, tamaños de logo y botones sin cambios (barra de 68 px, logo de 60 px, botones de 40 px) y barra translúcida con blur al scrollear (clase `site-header--scrolled`, la pone `global.js`). Los cambios están en `assets/css/global.css`, `assets/css/modo-oscuro.css` y `assets/js/global.js`.

## Resto de las mejoras
- `assets/css/mejoras.css` (se carga al final desde `includes/header.php`), `assets/css/tarjeta-producto.css`, `assets/js/mejoras.js`
- `carrito_api.php` + `includes/tarjeta_producto.php`: agregar al carrito sin recargar (usa `includes/carrito.php`; valida sesión, CSRF y stock en el servidor; `carrito_agregar.php` queda como respaldo sin JavaScript)
- `includes/footer.php`: botón flotante único, aviso, mini-carrito y barra de carrito
- `index.php` / `productos.php`: datos de entrega, pausa de la foto, "Lo más pedido", "Cómo pedir", mapa con fachada, chips de categorías en móvil, títulos h3
- `assets/js/vendor/`: GSAP 3.15.0 (opcional; borrá la carpeta si no lo querés usar)

## Límites
- El PHP pasa `php -l` pero no se ejecutó contra una base de datos real: probá el carrito antes de publicar.
- De OpenCode se tomó solo el header; no se incluyeron sus cambios de `productos.css`, `productos.php` ni el reemplazo de fotos faltantes.
- Los botones del header miden 40 px (como en OpenCode), por debajo de los 44 px recomendados para táctil.
- Revisá los términos de GSAP (gsap.com/standard-license) antes de publicar.

## Hero de Nosotros
Se quitó el recuadro oscuro que estaba detrás de la foto (`renderFotoHeroCiclo(false)` en `nosotros.php`) y las rayas de fondo ahora se desvanecen hacia abajo, igual que en Inicio (`assets/css/nosotros.css`). Login, registro, recuperar y restablecer siguen usando el recuadro: si lo querés quitar también ahí, cambiá `renderFotoHeroCiclo()` por `renderFotoHeroCiclo(false)` en esos archivos.

## Páginas del footer rediseñadas (Preguntas frecuentes, Políticas, Términos)
- Se quitó la barra negra del título: ahora hay hero crema con rayas difuminadas (como Inicio/Nosotros), migas, título con acento rojo/amarillo y pestañas para pasar entre las 3 páginas.
- Preguntas frecuentes: buscador en vivo, "Abrir todas", acordeón numerado. Políticas/Términos: índice lateral con resaltado de sección (chips fijos en celular), tarjetas con ícono y botón de WhatsApp al final.
- Archivos: includes/estatica_hero.php, assets/css/estatica.css, assets/js/estatica.js, las 3 páginas .php y modo-oscuro.css.

# Capa de movimiento (Inicio, Productos, Nosotros)
Archivos nuevos: `el/assets/css/movimiento.css` y `el/assets/js/movimiento.js`. Se cargan al final de header.php / footer.php.
Sin cambios en index.php. Las fotos del hero no se tocan.
- Inicio: título letra por letra, rayas en movimiento, categorías con rebote, ruta con moto en "Cómo pedir".
- Productos: carrito SVG fantástico en el hero (llega a toda velocidad, se pasa, frena dejando marcas y vuelve a su lugar, productos que caen, órbita, ondas y un show cada 8 s), título con máscara, píldora que sigue al cursor, cascada de tarjetas, inclinación 3D, bolita que vuela al carrito, favoritos con partículas.
- Nosotros: tarjeta "Compromiso con nuestros clientes" animada (ver LEEME-ANIMACION-COMPROMISO.md), arreglo para celular, bici y pin animados, tarjetas desde los costados, marcador amarillo, checks que se dibujan.
- Todas: barra de progreso en el header y transición suave entre páginas (View Transitions, Chrome/Edge/Safari recientes).
Reducir movimiento del sistema: todo queda estático. Para quitarlo: borrar los dos archivos, las 2 líneas de header/footer.

## Sonido del carrito, optimización y arreglo del hero de Inicio
- `el/assets/js/carrito-sonido.js` (nuevo): sonido sintetizado con Web Audio (sin archivos) sincronizado con la animación del carrito: pasada, chirrido de frenada, golpe, rebote, productos que caen, chapa del rayo y el "show" cada 8 s. Arranca apagado (los navegadores bloquean el audio automático): botón de parlante abajo a la derecha del hero de Productos; al activarlo reinicia la animación para que salgan sincronizados. La elección se recuerda (localStorage `elrapido_hc_sonido`).
- Hero de Inicio: la foto había desaparecido porque la regla del hero de Productos (`.hero .hero-inner {display:flex}`) también le pegaba a Inicio. Ahora está acotada a `.hero--productos`.
- Optimización: la animación del carrito se pausa cuando el hero sale de pantalla; se quitó el selector universal `svg *`; la inclinación 3D usa una lectura de layout por cuadro; el redimensionado se agrupa (debounce) y la ruta de "Cómo pedir" solo se recalcula cuando está cerca de la pantalla.

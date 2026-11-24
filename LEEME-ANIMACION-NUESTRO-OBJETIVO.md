# Animación "Nuestro objetivo" · integración en Nosotros

La tarjeta **Nuestro objetivo** de `el/nosotros.php` reproduce la animación al hacer clic: el
texto desaparece, entra el SVG con sonido y, al terminar los 6 segundos, el texto vuelve a
aparecer. No hay botones visibles. Funciona igual que las tarjetas **Nuestra forma de trabajar**
y **Evolución digital**.

## Archivos

| Archivo | Función |
|---|---|
| `el/assets/anim/nuestro-objetivo.svg` | Ilustración de la escena continua (pedidos, reloj y entrega). |
| `el/assets/anim/nuestro-objetivo.css` | Timeline de 6 s y modo de reproducción única (`.una-vez`). |
| `el/assets/css/nosotros-animacion.css` | Estados de las tarjetas animadas (ya existía). |
| `el/assets/js/nuestro-objetivo.js` | Carga diferida, reproducción, audio y retorno del texto. |

## Cambios en archivos existentes

- `el/nosotros.php`
  - la tarjeta pasa a `info-card info-card--anim` con `id="card-nuestro-objetivo"`
  - envuelve su contenido en `.card-contenido`
  - agrega `.card-escenario`, el botón invisible `.card-play` y la pista
  - incluye `assets/js/nuestro-objetivo.js` con `defer`
- `el/assets/css/nosotros-animacion.css`
  - solo se actualizó el comentario de cabecera: los estilos son compartidos por las tres tarjetas.

## Comportamiento

- **Clic en cualquier parte de la tarjeta** (o Enter/Espacio con teclado): reproduce la
  animación una sola vez, con sonido.
- El SVG se descarga **recién en el primer clic** (unos 43 KB entre SVG y CSS) y queda en
  caché para las siguientes reproducciones.
- El sonido se sintetiza con Web Audio API: no hay archivos de audio. Incluye las cuatro
  fichas de pedido, el check de confirmación, el acorde de la lista completa, el tic-tac del
  reloj, el pop del chip “MENOS ESPERA”, la campana al detenerse las agujas, el roce de la
  bolsa al tomarla y entregarla, el “¡Gracias!” y el sello “ENTREGADO”. Se programa completo
  al iniciar y dura lo mismo que la animación.
- Al terminar, la clase `.reproduciendo` se quita y el texto reaparece.
- La tarjeta conserva su altura mientras dura la animación para no mover el resto de la
  página, y el SVG se adapta manteniendo la proporción `720 / 460`.
- Con `prefers-reduced-motion: reduce` no se reproduce: la tarjeta queda como texto normal,
  sin pista ni botón.
- Si el navegador no permite audio, la animación igual se reproduce en silencio.

## Secuencia

| Tiempo | Momento |
|---|---|
| 0 – 2,6 s | **Pedidos en orden**: cuatro pedidos se acomodan en la lista y se confirman con un check. |
| 2 – 3,8 s | **Menos espera**: el reloj gira y se detiene, con arcos de velocidad y el chip “MENOS ESPERA”. |
| 3,9 – 6 s | **Atención cercana**: entran el cliente y el vendedor, se entrega la bolsa, aparece el globo “¡Gracias!” y la etiqueta “ENTREGADO”. |

## Cómo desactivarlo

1. En `el/nosotros.php`, quitar `<script src="assets/js/nuestro-objetivo.js" defer></script>`.
2. Devolver la tarjeta a su marcado original (sin `.info-card--anim`, `.card-contenido`,
   `.card-escenario` ni `.card-play`).
3. Borrar `el/assets/js/nuestro-objetivo.js`, `el/assets/anim/nuestro-objetivo.svg` y
   `el/assets/anim/nuestro-objetivo.css`.
4. Si ninguna otra tarjeta usa la animación, quitar también `$pageCssExtra = ['nosotros-animacion.css'];`
   y `el/assets/css/nosotros-animacion.css`.

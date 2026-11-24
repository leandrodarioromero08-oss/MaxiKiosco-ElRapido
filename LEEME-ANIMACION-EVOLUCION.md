# Animación "Evolución digital" · integración en Nosotros

La tarjeta **Evolución digital** de `el/nosotros.php` reproduce la animación al hacer clic:
el texto desaparece, entra el SVG con sonido y, al terminar los 6 segundos, el texto vuelve
a aparecer. No hay botones visibles.

## Archivos

| Archivo | Función |
|---|---|
| `el/assets/anim/evolucion-digital.svg` | Ilustración y estructura de las tres escenas. |
| `el/assets/anim/evolucion-digital.css` | Timeline de 6 s y modo de reproducción única (`.una-vez`). |
| `el/assets/css/nosotros-animacion.css` | Estados de la tarjeta: texto oculto, escenario y pista. |
| `el/assets/js/evolucion-digital.js` | Carga diferida, reproducción, audio y retorno del texto. |

## Cambios en archivos existentes

- `el/nosotros.php`
  - agrega `$pageCssExtra = ['nosotros-animacion.css'];`
  - envuelve el contenido de la tarjeta en `.card-contenido`
  - agrega `.card-escenario`, el botón invisible `.card-play` y la pista
  - incluye `assets/js/evolucion-digital.js` con `defer`

## Comportamiento

- **Clic en cualquier parte de la tarjeta** (o Enter/Espacio con teclado): reproduce la
  animación una sola vez, con sonido.
- El SVG se descarga **recién en el primer clic** (unos 56 KB entre SVG y CSS) y queda en
  caché para las siguientes reproducciones.
- El sonido se sintetiza con Web Audio API: no hay archivos de audio. Se programa completo
  al iniciar y dura lo mismo que la animación.
- Al terminar, la clase `.reproduciendo` se quita y el texto reaparece.
- La tarjeta conserva su altura mientras dura la animación para no mover el resto de la
  página.
- Con `prefers-reduced-motion: reduce` no se reproduce: la tarjeta queda como texto normal.
- Si el navegador no permite audio, la animación igual se reproduce en silencio.

## Cómo desactivarlo

1. En `el/nosotros.php`, quitar `$pageCssExtra = ['nosotros-animacion.css'];`.
2. Quitar `<script src="assets/js/evolucion-digital.js" defer></script>`.
3. Devolver la tarjeta a su marcado original (sin `.card-contenido`, `.card-escenario` ni
   `.card-play`).
4. Borrar `el/assets/css/nosotros-animacion.css`, `el/assets/js/evolucion-digital.js` y la
   carpeta `el/assets/anim/`.

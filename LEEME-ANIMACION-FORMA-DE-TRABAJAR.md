# Animación "Nuestra forma de trabajar" · integración en Nosotros

La tarjeta **Nuestra forma de trabajar** de `el/nosotros.php` reproduce la animación al hacer
clic: el texto desaparece, entra el SVG con sonido y, al terminar los 6 segundos, el texto
vuelve a aparecer. No hay botones visibles. Funciona igual que la tarjeta **Evolución digital**.

## Archivos

| Archivo | Función |
|---|---|
| `el/assets/anim/forma-de-trabajar.svg` | Ilustración de la escena continua (mostrador, entrega y delivery). |
| `el/assets/anim/forma-de-trabajar.css` | Timeline de 6 s y modo de reproducción única (`.una-vez`). |
| `el/assets/css/nosotros-animacion.css` | Estados de las tarjetas animadas (ya existía). |
| `el/assets/js/forma-de-trabajar.js` | Carga diferida, reproducción, audio y retorno del texto. |

## Cambios en archivos existentes

- `el/nosotros.php`
  - la tarjeta pasa a `info-card info-card--anim` con `id="card-forma-de-trabajar"`
  - envuelve su contenido en `.card-contenido`
  - agrega `.card-escenario`, el botón invisible `.card-play` y la pista
  - incluye `assets/js/forma-de-trabajar.js` con `defer`
- `el/assets/css/nosotros-animacion.css`
  - solo se actualizó el comentario de cabecera: los estilos son compartidos por las dos tarjetas.

## Comportamiento

- **Clic en cualquier parte de la tarjeta** (o Enter/Espacio con teclado): reproduce la
  animación una sola vez, con sonido.
- El SVG se descarga **recién en el primer clic** (unos 43 KB entre SVG y CSS) y queda en
  caché para las siguientes reproducciones.
- El sonido se sintetiza con Web Audio API: no hay archivos de audio. Incluye pasos al llegar,
  roce de bolsa al apoyarla, tomarla y entregarla, las dos notas del “¡Listo!” y el motor de la
  moto. Se programa completo al iniciar y dura lo mismo que la animación.
- Al terminar, la clase `.reproduciendo` se quita y el texto reaparece.
- La tarjeta conserva su altura mientras dura la animación para no mover el resto de la
  página, y el SVG se adapta manteniendo la proporción `720 / 460`.
- Con `prefers-reduced-motion: reduce` no se reproduce: la tarjeta queda como texto normal,
  sin pista ni botón.
- Si el navegador no permite audio, la animación igual se reproduce en silencio.

## Secuencia

| Tiempo | Momento |
|---|---|
| 0 – 1,5 s | **Te recibimos**: el cliente llega caminando y deja la bolsa en el mostrador. |
| 1,5 – 3,4 s | **Te atendemos**: el vendedor toma la bolsa, se la entrega y aparece “¡Listo!”. |
| 3,4 – 6 s | **Te llevamos**: la moto de delivery cruza la escena y sale de cuadro. |

## Cómo desactivarlo

1. En `el/nosotros.php`, quitar `<script src="assets/js/forma-de-trabajar.js" defer></script>`.
2. Devolver la tarjeta a su marcado original (sin `.info-card--anim`, `.card-contenido`,
   `.card-escenario` ni `.card-play`).
3. Borrar `el/assets/js/forma-de-trabajar.js`, `el/assets/anim/forma-de-trabajar.svg` y
   `el/assets/anim/forma-de-trabajar.css`.
4. Si ninguna otra tarjeta usa la animación, quitar también `$pageCssExtra = ['nosotros-animacion.css'];`
   y `el/assets/css/nosotros-animacion.css`.

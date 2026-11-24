# Animación "Compromiso con nuestros clientes" · integración en Nosotros

La tarjeta **Compromiso con nuestros clientes** de `el/nosotros.php` reproduce la animación
al hacer clic: el texto desaparece, entra el SVG con sonido y, al terminar los 6 segundos,
el texto vuelve a aparecer. No hay botones visibles. Funciona igual que las tarjetas
**Nuestra forma de trabajar**, **Evolución digital** y **Nuestro objetivo**.

## Archivos

| Archivo | Función |
|---|---|
| `el/assets/anim/compromiso-clientes.svg` | Ilustración de la escena continua (atención, compra online y entrega). |
| `el/assets/anim/compromiso-clientes.css` | Timeline de 6 s y modo de reproducción única (`.una-vez`). |
| `el/assets/css/nosotros-animacion.css` | Estados de las tarjetas animadas (ya existía). |
| `el/assets/js/compromiso-clientes.js` | Carga diferida, reproducción, audio y retorno del texto. |

## Cambios en archivos existentes

- `el/nosotros.php`
  - la tarjeta pasa a `info-card info-card--anim` con `id="card-compromiso-clientes"`
  - envuelve su contenido en `.card-contenido`
  - agrega `.card-escenario`, el botón invisible `.card-play` y la pista
  - cambia el ícono de la tarjeta a `corazon`, acorde al cierre de la animación
  - incluye `assets/js/compromiso-clientes.js` con `defer`
- `el/assets/css/nosotros-animacion.css`
  - solo se actualizó el comentario de cabecera: los estilos son compartidos por las cuatro tarjetas.

## Comportamiento

- **Clic en cualquier parte de la tarjeta** (o Enter/Espacio con teclado): reproduce la
  animación una sola vez, con sonido.
- El SVG se descarga **recién en el primer clic** (unos 44 KB entre SVG y CSS) y queda en
  caché para las siguientes reproducciones.
- El sonido se sintetiza con Web Audio API: no hay archivos de audio. Incluye la consulta del
  cliente, la respuesta del empleado, el encendido del celular, el pop de la tarjeta de pedido,
  la confirmación online, el roce de la bolsa al tomarla y entregarla, el acorde del corazón
  con tilde y los destellos finales. Se programa completo al iniciar y dura lo mismo que la
  animación.
- Al terminar, la clase `.reproduciendo` se quita y el texto reaparece.
- La tarjeta conserva su altura mientras dura la animación para no mover el resto de la
  página, y el SVG se adapta manteniendo la proporción `720 / 460`.
- Con `prefers-reduced-motion: reduce` no se reproduce: la tarjeta queda como texto normal,
  sin pista ni botón.
- Si el navegador no permite audio, la animación igual se reproduce en silencio.

## Secuencia

| Tiempo | Momento |
|---|---|
| 0 – 2,2 s | **Atención cercana**: el cliente consulta en el mostrador y el empleado responde con un tilde. |
| 2,2 – 3,8 s | **Adaptación**: el cliente levanta el celular, aparecen el chip “ONLINE”, el wifi y la tarjeta de pedido que confirma la compra. |
| 4,1 – 5,4 s | **Compromiso**: el empleado entrega la bolsa, el cliente la recibe y aparece el corazón con el tilde y los destellos. |
| 5,4 – 6 s | Cierre suave de la escena. |

## Cómo desactivarlo

1. En `el/nosotros.php`, quitar `<script src="assets/js/compromiso-clientes.js" defer></script>`.
2. Devolver la tarjeta a su marcado original (sin `.info-card--anim`, `.card-contenido`,
   `.card-escenario` ni `.card-play`).
3. Borrar `el/assets/js/compromiso-clientes.js`, `el/assets/anim/compromiso-clientes.svg` y
   `el/assets/anim/compromiso-clientes.css`.
4. Si ninguna otra tarjeta usa la animación, quitar también `$pageCssExtra = ['nosotros-animacion.css'];`
   y `el/assets/css/nosotros-animacion.css`.

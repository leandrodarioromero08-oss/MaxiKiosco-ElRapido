# Auditoría de rendimiento

## Qué se cambió
| Área | Antes | Ahora |
|---|---|---|
| CSS | ~10 hojas que bloqueaban el pintado, sin caché | 1 paquete minificado (`assets/css/_b-*.css`, se genera solo). 199 KB → 146 KB, ~18 KB con gzip |
| Caché CSS/JS | se revalidaba en cada visita | URL con `?v=<fecha>` + caché de 1 año; al editar un archivo la URL cambia sola |
| Fuentes Google | bloqueaban el render (`display=block`) | carga no bloqueante, `display=swap` |
| Fotos hero | una sola versión de 1122 px | `srcset` con versión de 640 px para móvil (~73 KB en vez de ~170 KB) |
| Sonidos | se sintetizaban en cada clic | 15 sonidos pre-renderizados en AudioBuffers; sonar = 1 fuente |
| AudioContext | hasta 5 por página en Nosotros (Safari admite ~4) | 1 compartido (`ElRapidoSonido.contexto()`) |
| Animaciones | `mvDrift` repintaba el hero y `mvBrillo` recalculaba el layout en cada cuadro | pasan a `transform`/`translate` (GPU) |
| `transition: all` | 5 botones | propiedades concretas |
| Scroll | `movimiento.js` leía `scrollHeight` en cada cuadro | se mide solo al cambiar el tamaño |
| Service worker | CSS/JS siempre por red | versionados desde caché; `VERSION` = v2 |
| `.htaccess` | — | caché inmutable para recursos versionados, más tipos comprimidos |

## Archivos nuevos
`includes/assets.php`, `assets/img/hero-foto-640.*`, `hero-foto-2-640.*`.

## Requisitos / notas
- `assets/css/` debe ser escribible por el servidor. Si no lo es, el sitio carga las hojas sueltas (igual de bien, sin el paquete).
- Los `_b-*.css` son archivos generados: no hace falta subirlos ni versionarlos.
- Los cambios en `.htaccess` usan solo mod_rewrite y mod_headers dentro de `<IfModule>`.
- Los sonidos se precargan al estar libre el navegador; el primer toque de la persona sigue siendo necesario para que el navegador permita audio.

## Cómo se verificó
PHP 8.3 + MariaDB + Apache 2.4 reales: 10 páginas públicas, registro, carrito (API), cuenta y 7 secciones del admin responden 200 sin warnings. CSS minificado comparado regla por regla contra el original (17 hojas, 0 diferencias). Encabezados de caché y gzip comprobados en Apache. 10 páginas ejecutadas con jsdom e interacciones: 0 errores de JS. Sonidos: 15/15 renderizados con cola completa. Lógica del service worker probada con un `caches` simulado.
**No se pudo probar en un navegador real** (fluidez de animaciones, audio real, Safari/iOS).

## Buscador de productos (productos.php)
Barra de búsqueda sobre el listado público. Busca por palabras (en cualquier orden) en el nombre del producto y en el de su categoría, ignora
mayúsculas y tildes, y se combina con la categoría, el orden y la paginación. Funciona sin JavaScript (formulario GET `?q=`); el JS agrega el botón ✕,
evita búsquedas vacías y el atajo `/`. Los resultados salen `noindex`. Archivos: `includes/funciones.php`, `productos.php`, `assets/css/productos.css`,
`assets/js/productos.js`. Sin cambios en la base de datos.

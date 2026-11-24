# Intro cinematográfica

Pantalla oscura con el logo → clic en el logo (suena y arranca) → celular con notificaciones → logo y explosión → fachada de noche → clic en la puerta → entrada al local → se funde hacia el sitio.

## Reglas
- Solo en `index.php`. Se muestra **una vez por sesión del navegador** (`sessionStorage.elr_intro`).
- **Saltar intro** y **silencio** arriba a la derecha, desde la pantalla del logo hasta el final. `Esc` también salta.
- Botón **Ver intro** en el footer de todas las páginas (en otras páginas lleva a `index.php?intro=1`).
- Con "reducir movimiento" activado en el dispositivo no arranca sola (el botón del footer sí la muestra).
- Si el cliente tiene los sonidos del sitio apagados (`elrapido_sonidos = 0`), la intro empieza en silencio.

## Archivos
| Archivo | Qué es |
|---|---|
| `includes/intro_gate.php` | Pantalla del logo y botones |
| `assets/css/intro.css` | Estilos de la pantalla y del botón del footer (entra en el paquete CSS) |
| `assets/js/intro.js` | Lógica: precarga, sonido del logo, saltar, silencio, repetir |
| `assets/intro/intro.html`, `b01.html`, `img/` | La animación (se carga en un iframe) |
| `index.php`, `includes/header.php`, `includes/footer.php` | Enganches (`$pageIntro`, clase `intro-on`, botón del footer) |
| `sw.js` | Excluye `assets/intro/` (VERSION v3) |

## Rendimiento
- La animación pesa ~1,9 MB (WebP) y **no se descarga con la portada**: el iframe se precarga cuando el navegador está libre, mientras se ve el logo. Si se toca antes de terminar, muestra "Cargando…" y arranca sola.
- Una vez que termina o se salta, el iframe se elimina (libera memoria, audio y animaciones).
- Calidad adaptable: si el equipo no da abasto, la animación apaga efectos secundarios sola.
- Solo se anima `transform` y `opacity`; el degradado del suelo está dentro de la imagen (sin máscaras CSS por cuadro).

## Notas
- No se pudo probar con audio real ni en iPhone/Safari: probar con auriculares y en un celular.
- En celular vertical la animación se ve como una franja 16:9 centrada.
- Los textos de la animación ("El Rápido Maxikiosco") están dentro de `assets/intro/intro.html`.

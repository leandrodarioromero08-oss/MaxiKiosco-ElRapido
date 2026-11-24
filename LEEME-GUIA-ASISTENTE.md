# Motos de El Rápido que señalan el asistente (inicio)

Una moto pequeña dibujada en SVG (parecida a la del logo) con un destello dorado sale del logo
del header, cruza la pantalla por un punto al azar, rápido, y llega al botón flotante del chat,
que da un único pulso suave. No hay textos.

## Cuándo sale
- **Cada vez que se carga o recarga el inicio**: sale una moto.
- **Al tocar el logo de El Rápido**: en vez de recargar el inicio, sale otra moto por otro
  camino al azar. Pueden volar **hasta 5 a la vez**; con 5 en el aire, los toques extra se
  ignoran. (Ctrl/Cmd + clic o clic del medio sigue abriendo el inicio en otra pestaña.)
- En las demás páginas el logo funciona como siempre (lleva al inicio).

## Sonido
Sintetizado con Web Audio (no hay archivos): un "vroom" corto al salir y un tintineo al llegar
(cada moto suena con un par de notas distinto). Los navegadores bloquean el audio hasta que la
persona toca o hace clic en la página: al abrir el inicio, la moto sale en silencio si todavía
no hubo interacción; al tocar el logo (que es un clic) sí suena. Con "reducir movimiento" del
sistema no viajan ni suenan: solo el pulso.

## Archivos
- `el/assets/js/chatbot-guia.js` (nuevo): motos, recorrido en canvas, sonido y pulso. La moto es
  el SVG `MOTO_SVG` que está dentro del archivo (se puede editar ahí).
- `el/assets/css/chatbot.css`: sección "GUÍA" al final.
- `el/includes/footer.php`: carga el script solo si la página define `$pageChatGuia`.
- `el/index.php`: define `$pageChatGuia = true;`.

## Ajustes (arriba de chatbot-guia.js)
`MAX_MOTOS`, `ESPERA_INICIAL`, `DURACION` (velocidad), `T_COLA` (largo de la estela),
`VOLUMEN` (0 = mudo).

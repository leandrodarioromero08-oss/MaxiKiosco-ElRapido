/* carrito-modal-cs.js
 * Modal que aparece después de confirmar un pedido, preguntando si se
 * quiere guardar como "Compra Semanal". Se carga solo cuando ese modal
 * existe en la página (ver carrito.php).
 */

// IIFE: función que se ejecuta sola. Evita crear variables globales.
(function () {
  // Elementos del modal (select de destino, contenedor y campo de nombre).
  // Si no había listas previas, selectDestino es null.
  const selectDestino = document.getElementById('modal-cs-destino');
  const nombreWrap = document.getElementById('modal-cs-nombre-wrap');
  const inputNombre = document.getElementById('modal-cs-nombre');

  // Muestra el campo "nombre" solo si se va a crear una lista NUEVA
  // (valor vacío del select). Si se elige reemplazar una existente, se oculta.
  function actualizarVisibilidadNombre() {
    if (!selectDestino) return; // no había listas previas: siempre se crea una nueva
    if (selectDestino.value === '') {
      nombreWrap.style.display = '';
    } else {
      nombreWrap.style.display = 'none';
    }
  }

  // Al cambiar el select se actualiza la visibilidad; y una vez al cargar.
  if (selectDestino) {
    selectDestino.addEventListener('change', actualizarVisibilidadNombre);
    actualizarVisibilidadNombre();
  }

  // Accesibilidad: al aparecer el modal el foco entra en él, y Tab no sale hacia la página de atrás.
  const overlay = document.getElementById('modal-compra-semanal');
  const caja = overlay ? overlay.querySelector('[role="dialog"]') : null;
  // Al aparecer el modal el foco entra en él...
  if (caja) {
    caja.focus();
    // ...y se atrapa la tecla Tab: el foco circula solo entre los elementos
    // del modal (focus trap), sin salir hacia la página de atrás.
    overlay.addEventListener('keydown', function (e) {
      if (e.key !== 'Tab') return;
      // Elementos enfocables del modal (botones, campos, select).
      const enfocables = caja.querySelectorAll('button:not([disabled]), input:not([type="hidden"]):not([disabled]), select:not([disabled])');
      // Se descartan los ocultos (offsetParent === null).
      const visibles = Array.prototype.filter.call(enfocables, function (el) { return el.offsetParent !== null; });
      if (!visibles.length) return;
      // Primer y último elemento: al pasar el último, Tab vuelve al primero
      // (y Shift+Tab desde el primero va al último).
      const primero = visibles[0], ultimo = visibles[visibles.length - 1];
      if (e.shiftKey && (document.activeElement === primero || document.activeElement === caja)) { e.preventDefault(); ultimo.focus(); }
      else if (!e.shiftKey && document.activeElement === ultimo) { e.preventDefault(); primero.focus(); }
    });
  }

  // Mostramos el campo de nombre recién cuando el usuario decide guardar,
  // para que "No, gracias" siga siendo un clic único y directo.
  // Validación al enviar "Guardar": si se crea una lista nueva y el nombre
  // está vacío, se frena el envío y se enfoca el campo.
  document.getElementById('form-guardar-cs').addEventListener('submit', function (e) {
    const creandoNueva = !selectDestino || selectDestino.value === '';
    if (creandoNueva && inputNombre.value.trim() === '') {
      e.preventDefault();
      inputNombre.focus();
      inputNombre.placeholder = 'Escribí un nombre para tu lista, ej: Compra de los viernes';
    }
  });
})();

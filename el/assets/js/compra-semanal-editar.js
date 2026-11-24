/* compra-semanal-editar.js
 * Lógica de la pantalla "Editar Compra Semanal": botones +/- de cantidad
 * por producto, contador de seleccionados y validación antes de enviar
 * el formulario (tiene que haber al menos un producto con cantidad > 0).
 */

(function () {
  const items = document.querySelectorAll('[data-producto-item]');
  const contador = document.getElementById('contador-seleccionados');

  function contarSeleccionados() {
    let n = 0;
    items.forEach(function (item) {
      const input = item.querySelector('[data-input]');
      if (parseInt(input.value, 10) > 0) n++;
    });
    contador.textContent = n;
  }

  items.forEach(function (item) {
    const input = item.querySelector('[data-input]');
    // max="0" significa sin stock (no "sin límite"); solo si no hay atributo max no se limita
    const maxAttr = input.getAttribute('max');
    const max = maxAttr === null ? Infinity : parseInt(maxAttr, 10);

    function actualizar() {
      let val = parseInt(input.value, 10) || 0;
      if (val < 0) val = 0;
      if (val > max) val = max;
      input.value = val;
      item.classList.toggle('seleccionado', val > 0);
      contarSeleccionados();
    }

    item.querySelector('[data-sumar]').addEventListener('click', function () {
      input.value = (parseInt(input.value, 10) || 0) + 1;
      actualizar();
    });
    item.querySelector('[data-restar]').addEventListener('click', function () {
      input.value = (parseInt(input.value, 10) || 0) - 1;
      actualizar();
    });
    input.addEventListener('change', actualizar);
    actualizar(); // normaliza cantidades guardadas que superen el stock actual
  });

  contarSeleccionados();

  document.getElementById('form-editar-lista').addEventListener('submit', function (e) {
    let algunoSeleccionado = false;
    items.forEach(function (item) {
      if (parseInt(item.querySelector('[data-input]').value, 10) > 0) algunoSeleccionado = true;
    });
    if (!algunoSeleccionado) {
      e.preventDefault();
      alert('Elegí al menos un producto con cantidad mayor a 0.');
    }
  });
})();
